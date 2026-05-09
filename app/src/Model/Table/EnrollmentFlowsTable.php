<?php
/**
 * COmanage Registry Enrollment Flows Table
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
 * @since         COmanage Registry v5.1.0
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
use App\Lib\Enum\EnrollmentAuthzEnum;
use App\Lib\Enum\PetitionStatusEnum;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Enum\TemplateableStatusEnum;
use App\Lib\Util\StringUtilities;

class EnrollmentFlowsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\CopyTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.1.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('Cous')
         ->setForeignKey('authz_cou_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('authz_cou');
    $this->belongsTo('Groups')
         ->setForeignKey('authz_group_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('authz_group');
    $this->belongsTo('NotificationGroups')
         ->setClassName('Groups')
         ->setForeignKey('notification_group_id')
         ->setProperty('notification_group');
    $this->belongsTo('FinalizationMessageTemplates')
         ->setClassName('MessageTemplates')
         ->setForeignKey('finalization_message_template_id')
         ->setProperty('finalization_message_template');
    $this->belongsTo('NotificationMessageTemplates')
         ->setClassName('MessageTemplates')
         ->setForeignKey('notification_message_template_id')
         ->setProperty('notification_message_template');

    $this->hasMany('Petitions');
    $this->hasMany('EnrollmentFlowSteps')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setDisplayField('name');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['copy', 'start']);
    $this->setRedirectGoal('self');

    $this->setAutoViewVars([
      'authzTypes' => [
        'type'  => 'enum',
        'class' => 'EnrollmentAuthzEnum'
      ],
      'finalizationMessageTemplates' => [
        'type' => 'select',
        'model' => 'MessageTemplates',
        'where' => ['context' => \App\Lib\Enum\MessageTemplateContextEnum::EnrollmentFinalization]
      ],
      'notificationGroups' => [
        'type'  => 'select',
        'model' => 'Groups'
      ],
      'notificationMessageTemplates' => [
        'type' => 'select',
        'model' => 'MessageTemplates',
        'where' => ['context' => \App\Lib\Enum\MessageTemplateContextEnum::EnrollmentStepCompleted]
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'TemplateableStatusEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'copy' =>     ['platformAdmin', 'coAdmin'],
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        // We handle start authorization in the Controller
        'start' =>      true,
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ],
      'related' => [
        'table' => [
          'EnrollmentFlowSteps'
        ]
      ]
    ]);
  }

  /**
   * Calculate the next Step for an Enrollment Flow.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $petitionId   Petition ID
   * @return array                url: URL to redirect to
   *                              step: EnrollmentFlowStep (null if finalize is true)
   *                              finalize: True if there are no further steps
   *                              lastStep: The prior EnrollmentFlowStep (the one before 'step')
   *                              petition: The Petition entity
   * @throws InvalidArgumentException
   */

  public function calculateNextStep(int $petitionId): array {
    // Start by retrieving the Petition

    $petition = $this->Petitions->get($petitionId);

    // Completed Petitions have no next step
    if($petition->isComplete()) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.completed', [$petitionId]));
    }

    // Pull the set of Petition Step Results for this Petition.

    $results = $this->Petitions->PetitionStepResults->find('list',
                                   keyField: 'enrollment_flow_step_id',
                                   valueField: 'status',
                                 )
                               ->where(['petition_id' => $petition->id])
                               ->orderBy(['enrollment_flow_step_id' => 'ASC'])
                               ->toArray();

    // Pull the Enrollment Flow Steps for this Enrollment Flow, in order,
    // and ignoring suspended Steps.

    $steps = $this->EnrollmentFlowSteps->find()
                                       ->where([
                                        'EnrollmentFlowSteps.enrollment_flow_id' => $petition->enrollment_flow_id,
                                        'EnrollmentFlowSteps.status' => SuspendableStatusEnum::Active
                                       ])
                                       ->orderBy(['EnrollmentFlowSteps.ordr' => 'ASC'])
                                       ->contain($this->EnrollmentFlowSteps->getPluginRelations())
                                       ->all();

    // Look for the first Enrollment Flow Step without a corresponding Result,
    // this is our next Step.

    if(empty($steps)) {
      // AR-EnrollmentFlow-1 An Enrollment Flow must have at least one Enrollment Flow Step
      // defined in order to be run. This is because the authorization for finalize is
      // calculated as the last configured step, so if there is no such step we cannpt
      // calculate permission correctly. Also, a Flow with no Steps has no purpose.

      throw new \InvalidArgumentException(__d('error', 'EnrollmentFlowSteps.none'));
    }

    $prior = null;

    foreach($steps as $step) {
      if(!array_key_exists($step->id, $results)) {
        // We do not have an array for this step, so it is the next step

        // We need the plugin name to find its instantiation ID
        $pluginModel = StringUtilities::pluginModel($step->plugin);
        $pluginName = Inflector::singularize(Inflector::underscore($pluginModel));

        return [
          'url' => [
            'plugin'      => StringUtilities::pluginPlugin($step->plugin),
            'controller'  => StringUtilities::pluginModel($step->plugin),
            'action'      => 'dispatch',
            $step->$pluginName->id,
            '?' => [
              'petition_id' => $petition->id
            ]
          ],
          'step' => $step,
          'finalize' => false,
          'lastStep' => $prior,
          'petition' => $petition
        ];
      }

      $prior = $step;
    }

    // If we didn't find a Step, it's time to Finalize the Petition.

    return [
      'url' => [
        'plugin'      => null,
        'controller'  => 'Petitions',
        'action'      => 'finalize',
        $petition->id
      ],
      'step' => null,
      'finalize' => true,
      'lastStep' => $steps->last(),
      'petition' => $petition
    ];
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');

    $this->registerStringValidation($validator, $schema, 'name', true);

    $validator->add('status', [
      'content' => ['rule' => ['inList', TemplateableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'sor_label', false);

    $validator->add('authz_type', [
      'content' => ['rule' => ['inList', EnrollmentAuthzEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('authz_type');

// XXX this becomes required when authz_type=CouAdmin || CouPerson
    $validator->add('authz_cou_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('authz_cou_id');

// XXX this becomes required when authz_type=GroupMember
    $validator->add('authz_group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('authz_group_id');

    $validator->add('collect_enrollee_email', [
      'content' => ['rule' => 'boolean']
    ]);
    $validator->allowEmptyString('collect_enrollee_email');

    $validator->add('redirect_on_duplicate', [
      'content' => ['rule' => 'url']
    ]);
    $validator->allowEmptyString('redirect_on_duplicate');

    $validator->add('redirect_on_finalize', [
      'content' => ['rule' => 'url']
    ]);
    $validator->allowEmptyString('redirect_on_finalize');
    
    $validator->add('finalization_message_template_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('finalization_message_template_id');

    $validator->add('notification_group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('notification_group_id');

    $validator->add('notification_message_template_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('notification_message_template_id');

    return $validator; 
  }
}