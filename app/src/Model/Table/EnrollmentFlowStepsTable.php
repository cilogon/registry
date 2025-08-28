<?php
/**
 * COmanage Registry Enrollment Flow Steps Table
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
use Cake\Validation\Validator;
use App\Lib\Enum\EnrollmentActorEnum;
use App\Lib\Enum\SuspendableStatusEnum;

class EnrollmentFlowStepsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PluggableModelTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TabTrait;
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
    $this->addBehavior('Orderable');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('ApproverGroups')
         ->setClassName('Groups')
         ->setForeignKey('approver_group_id')
         ->setProperty('approver_group');
    $this->belongsTo('EnrollmentFlows');
    $this->belongsTo('MessageTemplates');
    $this->belongsTo('NotificationGroups')
         ->setClassName('Groups')
         ->setForeignKey('notification_group_id')
         ->setProperty('notification_group');
    $this->belongsTo('NotificationMessageTemplates')
         ->setClassName('MessageTemplates')
         ->setForeignKey('notification_message_template_id')
         ->setProperty('notification_message_template');
    $this->hasMany('PetitionStepResults');

    $this->setPluginRelations();

    $this->setDisplayField('description');
    
    $this->setPrimaryLink('enrollment_flow_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');

    $this->setAutoViewVars([
      'actorTypes' => [
        'type'  => 'enum',
        'class' => 'EnrollmentActorEnum'
      ],
      'approverGroups' => [
        'type'  => 'select',
        'model' => 'Groups'
      ],
      'messageTemplates' => [
        'type' => 'select',
        'model' => 'MessageTemplates',
        'where' => ['context' => \App\Lib\Enum\MessageTemplateContextEnum::EnrollmentHandoff]
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
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'enrollment_flow_step'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SuspendableStatusEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'configure' =>  ['platformAdmin', 'coAdmin'],
        'delete' =>     ['platformAdmin', 'coAdmin'],
        // We handle dispatch authorization in the Controller
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
        'deleted' =>  ['platformAdmin', 'coAdmin']
      ],
      'related' => [
        'table' => [
          'EnrollmentFlowSteps'
        ]
      ]
    ]);

    $this->setTabsConfig(
      [
        'index' => [
          // Ordered list of Tabs
          'tabs' => ['EnrollmentFlows', 'EnrollmentFlowSteps', 'Petitions'],
          // What actions will inlcude the subnavigation header
          'action' => [
            // If a model renders in a subnavigation mode in edit/view mode, it cannot
            // render in index mode for the same use case/context
            // XXX edit should go first.
            'EnrollmentFlows' => ['edit', 'view'],
            'EnrollmentFlowSteps' => ['index'],
            'Petitions' => ['index'],
          ],
          // What model will have a counter-badge after the tab title
          'counter' => ['EnrollmentFlowSteps', 'Petitions']
        ],
        'edit' => [
          // Ordered list of Tabs
          'tabs' => ['EnrollmentFlowSteps', 'EnrollmentFlowSteps.Plugin', 'EnrollmentFlowSteps.Hierarchy'],
          // What actions will inlcude the subnavigation header
          'action' => [
            // If a model renders in a subnavigation mode in edit/view mode, it cannot
            // render in index mode for the same use case/context
            // XXX edit should go first.
            'EnrollmentFlowSteps' => ['edit', 'view'],
            'EnrollmentFlowSteps.Plugin' => ['edit'],
            // This means that we are looking at the plugins associated model
            // EnrollmentFlowSteps -> plugin -> @plugin
            // XXX There might be plugins that have no hasMany associations. We will check
            //     for these use cases inside the element.
            'EnrollmentFlowSteps.Hierarchy' => ['index']
          ],
        ]
      ]
    );
  }
  
  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.1.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-EnrollmentFlowStep-1 Two Enrollment Flow Steps in the same Enrollment Flow
    // cannot have the same order value. This is because we need to deterministically
    // determine the next step (in order to make sure we don't accidentally skip one)
    // and the last step run (in particular for determining the correct authorization
    // for finalize).
    $rules->add($rules->isUnique(['ordr', 'enrollment_flow_id'], __d('error', 'ordr.unique', [__d('controller', 'EnrollmentFlowSteps', [1])])));
    
    return $rules;
  }

  /**
   * Prepare for handoff to an Enrollment Flow Step.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  EnrollmentFlowStep $step     Enrollment Flow Step
   * @param  Petition           $petition Petition to prepare for
   */

  public function prepare(
    \App\Model\Entity\EnrollmentFlowStep $step,
    \App\Model\Entity\Petition $petition
  ) {
    // We give the plugin for this Step an opportunity to do something before
    // the handoff to the Step takes place. This is intended, for example, to
    // allow the Petition to be set to a Pending status prior to the next Actor
    // taking an action.

    // (We accept a Step entity rather than an ID because in the context in which
    // we're called we already have the Step, so no need to make another DB call.)

    $Plugin = TableRegistry::getTableLocator()->get($step->plugin);

    if(method_exists($Plugin, "prepare")) {
      $Plugin->prepare($step, $petition);
    }
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

    $validator->add('enrollment_flow_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('enrollment_flow_id');

    $this->registerStringValidation($validator, $schema, 'description', true);

    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'plugin', true);

    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');
    
    $validator->add('actor_type', [
      'content' => ['rule' => ['inList', EnrollmentActorEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('actor_type');

    $validator->add('approver_group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('approver_group_id');
    /*
    // An approvers group is required when the actor_type is Approver
    $validator->notEmptyString(
      field: 'approver_group_id',
      when: function($context) {
        return (!empty($context['data']['actor_type'])
                && ($context['data']['actor_type'] == EnrollmentActorEnum::Approver));
      }
    );*/

    $validator->add('message_template_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('message_template_id');

    $validator->add('redirect_on_handoff', [
      'content' => ['rule' => 'url']
    ]);
    $validator->allowEmptyString('redirect_on_handoff');

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