<?php
/**
 * COmanage Registry Provisioning Targets Table
 *
 * Portions licensed to the University Corporation for Advanced Internet
 * Development, Inc. ("UCAID") under one or more contributor license agreements.
 * See the NOTICE file distributed with this work for additional information
 * regarding copyright ownership.
 *
 * UCAID licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @link          https://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use App\Lib\Enum\ProvisionerModeEnum;
use App\Lib\Enum\ProvisioningContextEnum;
use App\Lib\Enum\ProvisioningStatusEnum;
use App\Lib\Util\StringUtilities;

class ProvisioningTargetsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PluggableModelTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Orderable');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('ProvisioningGroups')
         ->setClassName('Groups')
         ->setForeignKey('provisioning_group_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('provisioning_group');
    
    $this->hasMany('ProvisioningHistoryRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->setPluginRelations();
    
    $this->setDisplayField('description');
    
    $this->setPrimaryLink(['co_id', 'group_id', 'person_id']);
    $this->setRequiresCO(true);
    $this->setAllowUnkeyedPrimaryLink(['status']);

    $this->setAutoViewVars([
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'provisioner'
      ],
      'provisioningGroups' => [
        'type'  => 'select',
        'model' => 'ProvisioningGroups'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'ProvisionerModeEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'configure' =>  ['platformAdmin', 'coAdmin'],
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
        'status' =>   ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Invoke provisioning. This function is intended to be called via ProvisionableTrait.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  mixed                        $data         Provisioning object data, eg as returned by Table::get()
   * @param  ProvisioningEligibilityEnum  $eligibility  Provisioning eligibility
   * @param  ProvisioningContextEnum      $context      Provisioning context
   * @param  int                          $id           Provisioning Target ID, or null to provision all targets
   */

  public function provision(
    mixed $data,
    string $eligibility,
    string $context,
    ?int $id=null
  ) {
    // Convert the primary data object to the primary provisioned object name
    // (eg: People or Cous)
    $provisionedModel = StringUtilities::entityToClassName($data);
    
    $query = $this->find()
                  ->where([
                    'ProvisioningTargets.co_id'      => $data->co_id,
// XXX how do we know which mode's worth of provisioners we want?
                    'ProvisioningTargets.status <>'  => ProvisionerModeEnum::Disabled
                  ]);
    
    if($id) {
      $query = $query->where(['ProvisioningTargets.id' => $id]);
    }

    $targets = $query->order(['ProvisioningTargets.ordr' => 'ASC'])
                     ->contain($this->getPluginRelations())
                     ->all();
    
    foreach($targets as $t) {
      // Compare our $context against the target's $status. There are three possible
      // contexts, with their corresponding provisionable statuses:
      // Automatic:   Immediate, Queue, QuueOnError
      // Enrollment:  Enrollment, Immediate, Queue, QueueOnError
      // Manual:      Enrollment, Immediate, Manual, Queue, QueueOnError
// XXX do we need ARs or PARs for this? add appropriate logging along with ARs

      switch($context) {
        case ProvisioningContextEnum::Automatic:
          if(!in_array($t->status, [
            ProvisionerModeEnum::Immediate,
            ProvisionerModeEnum::Queue,
            ProvisionerModeEnum::QueueOnError
          ])) {
            $this->llog('trace', "Skipping Provisioning Target " . $t->id . " with mode " . $t->status . " (automatic context)", $t->id);
            continue 2;
          }
          break;
        case ProvisioningContextEnum::Enrollment:
          if($t->status == ProvisionerModeEnum::Manual) {
            $this->llog('trace', "Skipping Provisioning Target " . $t->id . " with mode " . $t->status . " (enrollment context)", $t->id);
            continue 2;
          }
          break;
        case ProvisioningContextEnum::Manual:
          // Manual provisioning is permitted regardless of target status
          break;
      }

      $pluginModel = StringUtilities::pluginModel($t->plugin);
      // The model in underscore format, eg file_provisioner
      $uPluginModel = Inflector::underscore(Inflector::singularize($pluginModel));

      // Does this plugin support this model?
      if(!$this->$pluginModel->isProvisionableModel($provisionedModel)) {
        $this->llog('trace', "Skipping $provisionedModel for $pluginModel (not supported)", $t->id);
        continue;
      }

      try {
        $this->llog('trace', "Provisioning $provisionedModel for $pluginModel (context: $context)", $t->id);

        $result = $this->$pluginModel->provision($t->$uPluginModel, $provisionedModel, $data, $eligibility);

        $this->alog('trace', $result);

        $this->ProvisioningHistoryRecords->record(
          provisioningTargetId: $t->id,
          comment: $result['comment'],
          status: $result['status'],
          subjectModel: $provisionedModel,
          subjectId: $data->id
        );
      }
      catch(\Exception $e) {
        $this->llog('error', "Provisioning failure: " . $e->getMessage());
        
        $this->ProvisioningHistoryRecords->record(
          provisioningTargetId: $t->id,
          comment: $e->getMessage(),
          status: ProvisioningStatusEnum::NotProvisioned,
          subjectModel: $provisionedModel,
          subjectId: $data->id
        );
      }
    }
  }

  /**
   * Obtain provisioning status. (Either $groupId or $personId must be requested.)
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int  $coId     CO ID
   * @param  int  $groupId  Group ID
   * @param  int  $personId Person ID
   */

  public function status(int $coId, int $groupId=null, int $personId=null): array {
    $ret = [];

    // Start by pulling the set of active provisioning targets

    $targets = $this->find()
                    ->where([
                      'ProvisioningTargets.co_id'      => $coId,
                      'ProvisioningTargets.status <>'  => ProvisionerModeEnum::Disabled
                    ])
                    ->all();

    if(!empty($targets)) {
      foreach($targets as $t) {
        // For each target, get the status of the target for the requested subject.
        // If the plugin implements a status() function we'll call it, otherwise
        // we'll get the status from ProvisioningHistory.

        $pluginModel = StringUtilities::pluginModel($t->plugin);

        if(method_exists($this->$pluginModel, 'status')) {
          // XXX define interface and call (implement with SqlProvisioner)
          throw new \RuntimeException('NOT IMPLEMENTED');
        } else {
          $subjectFK = null;
          $subjectID = null;

          if(!empty($personId)) {
            $subjectFK = 'person_id';
            $subjectID = $personId;
          } elseif(!empty($groupId)) {
            $subjectFK = 'group_id';
            $subjectID = $groupId;
          } else {
            throw new \InvalidArgumentException("NOT IMPKEMENTED");
          }
          
          $rec = $this->ProvisioningHistoryRecords->find()
                                                  ->where([
                                                    'provisioning_target_id' => $t->id,
                                                    $subjectFK => $subjectID
                                                  ])
                                                  ->order(['id' => 'DESC'])
                                                  ->first();
          
          if(!empty($rec)) {
            $ret[] = [
              'target'      => $t,
              'status'      => $rec->status,
              'comment'     => $rec->comment,
              // XXX where does identifier come from?
              //'identifier'  => '?',
              'timestamp'   => $rec->created
            ];
          } else {
            $ret[] = [
              'target'      => $t,
              'status'      => ProvisioningStatusEnum::NotProvisioned,
              'comment'     => __d('enumeration', 'ProvisioningStatusEnum.'.ProvisioningStatusEnum::NotProvisioned)
            ];
          }
        }
      }
    }

    return $ret;
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');
    
    $this->registerStringValidation($validator, $schema, 'description', false);
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', ProvisionerModeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'plugin', true);

    $validator->add('provisioning_group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('provisioning_group_id');

    $validator->add('retry_interval', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('retry_interval');

    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');
    
    return $validator; 
  }
}