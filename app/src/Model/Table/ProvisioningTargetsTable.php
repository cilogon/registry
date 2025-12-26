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
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Util\StringUtilities;
use App\Model\Entity\Job;

class ProvisioningTargetsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\ClonableTrait;
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
    $this->addBehavior('Clonable');
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
    $this->setAllowLookupPrimaryLink(['provision', 'reprovision']);
    $this->setAllowUnkeyedPrimaryLink(['status']);

    $this->setAutoViewVars([
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'provisioning_target'
      ],
      'provisioningGroups' => [
        'type'  => 'select',
        'model' => 'Groups'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'ProvisionerModeEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'configure' =>    ['platformAdmin', 'coAdmin'],
        'delete' =>       ['platformAdmin', 'coAdmin'],
        'edit' =>         ['platformAdmin', 'coAdmin'],
        // Used by ApiV2Controller
        'provision' =>    ['platformAdmin', 'coAdmin'],
        'reprovision' =>  ['platformAdmin', 'coAdmin'],
        'view' =>         ['platformAdmin', 'coAdmin']
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
   * Define business rules.
   *
   * @since  COmanage Registry v5.2.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-GMR-6 The same UUID cannot be assigned to multiple objects within the same CO.
    $rules->add([$this, 'ruleUuidUnique'],
                'uuidUnique',
                ['errorField' => 'uuid']);

    return $rules;
  }

  /**
   * Invoke provisioning. This function is intended to be called via ProvisionableTrait.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  mixed                        $data         Provisioning object data, eg as returned by Table::get()
   * @param  ProvisioningEligibilityEnum  $eligibility  Provisioning eligibility
   * @param  ProvisioningContextEnum      $context      Provisioning context
   * @param  int                          $id           Provisioning Target ID, or null to provision all targets
   * @param  Job                          $job          If called from a Job, the current Job entity
   */

  public function provision(
    mixed  $data,
    string $eligibility,
    string $context,
    ?int   $id=null,
    ?Job   $job=null
  ) {
    // Convert the primary data object to the primary provisioned object name
    // (eg: People or Cous)
    $provisionedModel = StringUtilities::entityToClassName($data);
    
    $query = $this->find()
                  ->where([
                    'ProvisioningTargets.co_id'      => $data->co_id,
                    'ProvisioningTargets.status <>'  => ProvisionerModeEnum::Disabled
                  ]);
    
    if($id) {
      $query = $query->where(['ProvisioningTargets.id' => $id]);
    }

    $targets = $query->orderBy(['ProvisioningTargets.ordr' => 'ASC'])
                     ->contain($this->getPluginRelations())
                     ->all();
    
    foreach($targets as $t) {
      // Compare our $context against the target's $status. There are four possible
      // contexts, with their corresponding provisionable statuses:
      // Automatic:   Immediate, Queue, QuueOnError
      // Enrollment:  Enrollment, Immediate, Queue, QueueOnError
      // Manual:      Enrollment, Immediate, Manual, Queue, QueueOnError
      // Queue:       Enrollment, Immediate, Manual, Queue, QueueOnError

      switch($context) {
        case ProvisioningContextEnum::Automatic:
          if(!in_array($t->status, [
            ProvisionerModeEnum::Immediate,
            ProvisionerModeEnum::Queue,
            ProvisionerModeEnum::QueueOnError
          ])) {
            $this->llog('trace', "Skipping Provisioning Target " . $t->id . " with mode " . $t->status . " (automatic context)", $t->id);
            // Note "continue 2" is correct here, since continue acts like a break within a switch
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
        case ProvisioningContextEnum::Queue:
          // Queue provisioning is permitted regardless of target status
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

      $this->llog('trace', "Provisioning $provisionedModel for $pluginModel (context: $context)", $t->id);
        
      $requeue = false;

      // We immediately run the requested Job, unless the Provisioner is in Queue mode
      // _and_ the Provisioning Context is _not_ Queue (which would indicate we are processing
      // the queue, so we shouldn't immedately requeue the job)
      if($t->status != ProvisionerModeEnum::Queue
          || $context == ProvisioningContextEnum::Queue) {
        try {
          $result = $this->$pluginModel->provision($t, $provisionedModel, $data, $eligibility);

          $this->alog('trace', $result);

          // The plugin can report failure by throwing an Exception, which we catch below.
          // Otherwise, the plugin can return Provisioned (success), NotProvisioned (also
          // success, eg the result of a delete operation), or Unknown (error). The only
          // situation we do not requeue is \InvalidArgumentException.

          if($result['status'] == ProvisioningStatusEnum::Unknown) {
            $requeue = $result['comment'];
          }

          $this->ProvisioningHistoryRecords->record(
            provisioningTargetId: $t->id,
            comment: $result['comment'],
            status: $result['status'],
            subjectModel: $provisionedModel,
            subjectId: $data->id
          );

          if(!empty($result['identifier']) && in_array($provisionedModel, ['People', 'Groups'])) {
            // We check for Provisioning Keys when provisioning People or Groups.
            // Other models (Services, etc) could support Provisioning Keys,
            // just currently they don't.
            $this->llog('trace', "Obtained Provisioning Key " . $result['identifier'] . " for $pluginModel", $t->id);

            // Upsert the identifier
            $Identifiers = TableRegistry::getTableLocator()->get('Identifiers');

            $typeId = $Identifiers->Types->getTypeId(
              coId: $data->co_id,
              attribute: 'Identifiers.type',
              // Although we now call these "Provisioning Keys", we reuse the database value from v4
              value: 'provisioningtarget'
            );

            $pkey = [
              'type_id' => $typeId,
              'identifier' => $result['identifier'],
              'status' => SuspendableStatusEnum::Active,
              'provisioning_target_id' => $t->id,
              'login' => false,
              'frozen' => false
            ];

            $whereClause = [
              'type_id' => $typeId
            ];

            if($provisionedModel == 'Group') {
              $pkey['group_id'] = $data->id;
              $whereClause['group_id'] = $data->id;
            } else {
              $pkey['person_id'] = $data->id;
              $whereClause['person_id'] = $data->id;
            }

            if(!$Identifiers->upsert($pkey, $whereClause)) {
              // We successfully provisioned, but for some reason we failed to store
              // the Provisioning Key. We shouldn't throw an Exception because that
              // will mask the fact that the target is provisioned, so we'll just log
              // an error.

              $this->llog('error', "Provisioning successfully completed, but failed to store Provisioning Key " . $result['identifier']);
            }
          }
        }
        catch(\InvalidArgumentException $e) {
          // The plugin has determined its configuration is invalid, so we do not requeue.

          $this->llog('error', "Provisioning failure due to invalid configuration: " . $e->getMessage());

          $this->ProvisioningHistoryRecords->record(
            provisioningTargetId: $t->id,
            comment: $e->getMessage(),
            status: ProvisioningStatusEnum::Unknown,
            subjectModel: $provisionedModel,
            subjectId: $data->id
          );
        }
        catch(\Exception $e) {
          $requeue = $e->getMessage();

          $this->llog('error', "Provisioning failure: " . $e->getMessage());

          $this->ProvisioningHistoryRecords->record(
            provisioningTargetId: $t->id,
            comment: $e->getMessage(),
            status: ProvisioningStatusEnum::Unknown,
            subjectModel: $provisionedModel,
            subjectId: $data->id
          );
        }
      }

      if(($t->status == ProvisionerModeEnum::QueueOnError && $requeue)
          || ($t->status == ProvisionerModeEnum::Queue && $context != ProvisioningContextEnum::Queue)) {
        // We either failed or are in Queue mode, so queue the job for later processing.
        // The max retry limit is implemented by register(), we'll just catch the
        // Exception and log it if register() fails,

        if($t->max_retry > 0) {
          try {
            $Jobs = TableRegistry::getTableLocator()->get("Jobs");

            $rqjob = $Jobs->register(
              coId:             $t->co_id,
              plugin:           'CoreJob.ProvisionerJob',
              parameters:       [
                'model' => $provisionedModel,
                'provisioning_target_id' => $t->id,
                // entities is a comma separated string of $provisionedModel subject IDs
                'entities' => $data->id
              ],
              registerSummary:  
                $requeue 
                ? __d('result', 'ProvisioningTargets.queued.error.ok', [$t->description, $t->id, $requeue])
                : __d('result', 'ProvisioningTargets.queued.queue.ok', [$t->description, $t->id]),
              synchronous:      false,
              // When requeueing we need to allow concurrent jobs because the
              // job we're replacing (as defined by having the same CO, plugin,
              // and parameters) hasn't technically finished yet.
              concurrent:       true,
              delay:            $t->retry_interval,
              // Provisioning Jobs should not automatically requeue on success
              requeueInterval:  null,
              retryInterval:    $t->retry_interval,
              maxRetry:         $t->max_retry,
              requeuedFrom:     $job ? $job->id : null,
              retryCount:       !empty($job->retry_count) ? $job->retry_count + 1 : 1
            );

            $this->llog('trace', "Requeued provisioning request as Job " . $rqjob->id);
          }
          catch(\Exception $e) {
            // This will be an OverflowException is max_retry was reached

            $this->llog('error', "Could not requeue provisioning request: " . $e->getMessage());
          }
        } else {
          $this->llog('trace', "Not requeueing provisioning request because max_retry is not set");
        }

        if($requeue) {
          // If we got an error message from the original provisioning request
          // (regardless of whether or not we then queued the job for processing)
          // we want to throw that error back up the stack so ProvisionerJob can
          // record it correctly.

          throw new \RuntimeException($requeue);
        }
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

  public function status(int $coId, ?int $groupId=null, ?int $personId=null): array {
    $ret = [];

    // Start by pulling the set of active provisioning targets

    $targets = $this->find()
                    ->where([
                      'ProvisioningTargets.co_id'      => $coId,
                      'ProvisioningTargets.status <>'  => ProvisionerModeEnum::Disabled
                    ])
                    ->contain($this->getPluginRelations())
                    ->all();

    if(!empty($targets)) {
      foreach($targets as $t) {
        // For each target, get the status of the target for the requested subject.
        // We'll also look for a Provisioning Key for the target.

        $Identifiers = TableRegistry::getTableLocator()->get('Identifiers');

        $typeId = $Identifiers->Types->getTypeId(
          coId: $coId,
          attribute: 'Identifiers.type',
          // Although we now call these "Provisioning Keys", we reuse the database value from v4
          value: 'provisioningtarget'
        );

        $targetField = $groupId ? 'group_id' : 'person_id';
        $targetId = $groupId ?? $personId;

        $pkey = $Identifiers->find()
                            ->where([
                              'type_id' => $typeId,
                              'provisioning_target_id' => $t->id,
                              $targetField => $targetId,
                              'status' => SuspendableStatusEnum::Active
                            ])
                            ->first();

        // If the plugin implements a status() function we'll call it, otherwise
        // we'll get the status from ProvisioningHistory.

        $PluginModel = TableRegistry::getTableLocator()->get($t->plugin);

        if(method_exists($PluginModel, 'status')) {
          try {
            $status = $PluginModel->status(cfg: $t, groupId: $groupId, personId: $personId);
            
            $ret[] = [
              'target'      => $t,
              'status'      => $status['status'],
              'comment'     => $status['comment'],
              'timestamp'   => $status['timestamp'],
              'identifier'  => $pkey ? $pkey->identifier : null
            ];
          }
          catch(\Exception $e) {
            $ret[] = [
              'target'      => $t,
              'status'      => ProvisioningStatusEnum::Unknown,
              'comment'     => $e->getMessage()
            ];
          }
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
                                                  ->orderBy(['id' => 'DESC'])
                                                  ->first();
          
          if(!empty($rec)) {
            $ret[] = [
              'target'      => $t,
              'status'      => $rec->status,
              'comment'     => $rec->comment,
              'identifier'  => $pkey ? $pkey->identifier : null,
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

    $validator->add('max_retry', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('max_retry');

    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');
    
    $this->registerClonableValidation($validator, $schema);
    
    return $validator; 
  }
}