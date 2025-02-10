<?php
/**
 * COmanage Registry Petitions Table
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

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\PetitionActionEnum;
use \App\Lib\Enum\PetitionStatusEnum;
use \App\Lib\Enum\ProvisioningContextEnum;
use \App\Lib\Enum\StatusEnum;
use \App\Lib\Enum\SuspendableStatusEnum;
use \App\Lib\Random\RandomString;
use \App\Lib\Util\StringUtilities;

class PetitionsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TabTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\SearchFilterTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.1.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Timestamp');
    $this->addBehavior('Timezone');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);
    
    // Define associations
    $this->belongsTo('Cous');
    $this->belongsTo('EnrollmentFlows');
    $this->belongsTo('EnrolleePeople')
      ->setClassName('People')
      ->setForeignKey('enrollee_person_id')
      // Property is set so ruleValidateCO can find it. We don't use the
      // _id suffix to match Cake's default pattern.
      ->setProperty('enrollee_person');
    $this->belongsTo('PetitionerPeople')
      ->setClassName('People')
      ->setForeignKey('petitioner_person_id')
      ->setProperty('petitioner_person');
    
    $this->hasMany('PetitionHistoryRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('PetitionStepResults')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('CoreEnroller.PetitionAttributes')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->hasOne('Verifications')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->setDisplayField('id');
    
    $this->setPrimaryLink('enrollment_flow_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['continue', 'finalize', 'pending', 'result', 'resume']);

    // These are required for the link to work from the Artifacts page
    $this->setAllowUnkeyedPrimaryCO(['index']);
    $this->setAllowEmptyPrimaryLink(['index']);
    
    $this->setIndexContains([
      'Cous',
      'EnrolleePeople' => ['PrimaryName' => ['foreignKey' => 'person_id']],
      'EnrollmentFlows',
      'PetitionerPeople' => ['PrimaryName' => ['foreignKey' => 'person_id']]
    ]);

    $viewContainsRelations = [
      'EnrollmentFlows' => ['EnrollmentFlowSteps' => ['sort' => ['ordr' => 'ASC']]],
      'EnrolleePeople' => ['PrimaryName' => ['foreignKey' => 'person_id']],
      'PetitionerPeople' => ['PrimaryName' => ['foreignKey' => 'person_id']],
      'PetitionHistoryRecords',
      'PetitionStepResults',
    ];

    // Fetch extra data if the CoreEnroller plugin is enabled.
    if(\Cake\Core\Plugin::isLoaded('CoreEnroller')) {
      $viewContainsRelations[] = 'PetitionAttributes';
    }
    $this->setViewContains($viewContainsRelations);

    $this->setAutoViewVars([
      'statuses' => [
        'type'  => 'enum',
        'class' => 'PetitionStatusEnum'
      ],
      'couIds' => [
        'type'  => 'select',
        'model' => 'Cous'
      ]
    ]);

    $this->setFilterConfig(
      [
        'cou_id' => [
          // We want to keep the default column configuration and add extra functionality.
          // Here the extra functionality is additional to select options since the cou_id
          // is of type select
          // XXX If the extras key is present, no other provided key will be evaluated. The rest
          //     of the configuration will be expected from the TableMetaTrait::filterMetadataFields()
          'extras' => [
            'options' => [
              'isnotnull' => __d('operation','any'),
              'isnull' => __d('operation','none'),
              __d('information','table.list', 'COUs') => '@DATA@',
            ]
          ]
        ]
      ]
    );

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        // We handle assign authorization in the Controller
        // 'assign' => true,
        // We handle continue authorization in the Controller
        'continue' => true,
        'delete' =>   false,
        'edit' =>     false,
        // We handle finalize authorization in the Controller
        'finalize' => true,
        // We handle provision authorization in the Controller
        // 'provision' => true,
        // result just issues a redirect, so we're generous with permissions
        'result' =>   ['platformAdmin', 'coAdmin'],
        // resume renders a landing page, the admin can copy a URL and resend it
        // to the appropriate actor if the actor is not also an admin
        'resume' =>   ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that are permitted on readonly entities (besides view)
      'readOnly' =>   ['result'],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false,
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);

    $this->setTabsConfig(
      [
        // Ordered list of Tabs
        'tabs' => ['EnrollmentFlows', 'EnrollmentFlowSteps', 'Petitions'],
        // What actions will include the subnavigation header
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
      ]
    );
  }

  /**
   * Assign Identifiers for a Petition.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $id   Petition ID
   * @throws InvalidArgumentException
   */

  public function assignIdentifiers(int $id) {
    // AR-Petition-1 When a Petition is finalized, any configured Identifier Assignments will be run.
    $this->llog('rule', "AR-Petition-1 Running Identifier Assignments for Petition $id");

    $petition = $this->get($id);

    if($petition->status != PetitionStatusEnum::Finalizing) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.status.finalizing', [$id]));
    }

    if(!$petition->enrollee_person_id) {
      // No Person associated with the Petition, so nothing to do
      $this->llog('debug', "No Enrollee Person ID found in Petition $id, so not assigning identifiers");
      return;
    }

    $IdentifierAssignments = TableRegistry::getTableLocator()->get('IdentifierAssignments');

    $ret = $IdentifierAssignments->assign(
      entityType: 'People',
      entityId:   $petition->enrollee_person_id,
      provision:  false,
// $actorPersonId: XXX
    );

    if(!empty($ret['assigned'])) {
      $this->PetitionHistoryRecords->record(
        petitionId:           $petition->id,
        enrollmentFlowStepId: null,
        action:               PetitionActionEnum::Finalized,
        comment:              __d('result', 'IdentifierAssignments.assigned.ok', [implode(',', array_keys($ret['assigned']))])
        // actorPersonId
      );
    }
  }

  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // An Enrollee email address may be required by the Enrollment Flow configuration
    $rules->add([$this, 'ruleEnrolleeEmail'],
                'enrolleeEmail',
                ['errorField' => 'enrollee_email']);
    
    return $rules;
  }

  /**
   * Finalize a Petition.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $id   Petition ID
   */

  public function finalize(int $id) {
    $petition = $this->get($id);

    if($petition->isComplete()) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.completed', [$id]));
    }
    
    // Update the Petition status and create a History Record.
    $petition->status = PetitionStatusEnum::Finalized;

    $this->saveOrFail($petition);

    $this->PetitionHistoryRecords->record(
      petitionId:           $petition->id,
      enrollmentFlowStepId: null,
      action:               PetitionActionEnum::Finalized,
      comment:              __d('result', 'Petitions.finalized')
      // actorPersonId
    );
  }

  /**
   * Perform Plugin finalization for a Petition.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $id   Petition ID
   */

  public function finalizePlugins(int $id) {
    // This is intended to be the first part of finalization, so we set the Petition status
    // to Finalizing.

    $petition = $this->get($id, ['contain' => [
                                  'EnrollmentFlows' => [
                                    'EnrollmentFlowSteps' => array_merge(
                                      $this->EnrollmentFlows->EnrollmentFlowSteps->getPluginRelations(),
                                      ['sort' => ['EnrollmentFlowSteps.ordr' => 'ASC']]
                                    )
                                ]]]);

    if($petition->isComplete()) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.completed', [$id]));
    }

    $petition->status = PetitionStatusEnum::Finalizing;

    $this->saveOrFail($petition);

    // If there is no Person attached to this Petition, allocate a new Person now
    // (with no attributes).

    if(empty($petition->enrollee_person_id)) {
      $People = TableRegistry::getTableLocator()->get('People');

      $person = $People->newEntity([
        'co_id' =>  $petition->enrollment_flow->co_id,
        'status' => StatusEnum::Active
      ]);

      $People->saveOrFail($person);

      $petition->enrollee_person_id = $person->id;
      
      // Save here in case any plugin tries to reload petition info
      $this->saveOrFail($petition);
      
      $People->recordHistory(
        entity: $person, 
        action: ActionEnum::PersonAddedPetition, 
        comment: __d('result',
                     'People.added.petition', [
                       $petition->enrollment_flow->description,
                       $petition->enrollment_flow->id,
                       $petition->id])
      );

      $this->llog('trace', 'Created new Person ' . $person->id . ' for Petition ' . $petition->id);
    }

    // Tell each plugin to finalize

    if(!empty($petition->enrollment_flow->enrollment_flow_steps)) {
      foreach($petition->enrollment_flow->enrollment_flow_steps as $step) {
        if($step->status == SuspendableStatusEnum::Suspended) {
          // Skip suspended steps
          continue;
        }

        $Plugin = TableRegistry::getTableLocator()->get($step->plugin);

        // Plugins cannot interrupt finalization by returning false or
        // throwing errors, but if we catch an Exception we'll at least log it
        try {
          if(method_exists($Plugin, "finalize")) {
            // We have "CoreEnroller.AttributeCollectors" but we want "attribute_collector"
            $pmodel = Inflector::underscore(Inflector::singularize(StringUtilities::pluginModel($step->plugin)));

            $Plugin->finalize($step->$pmodel->id, $petition);
          }
        }
        catch(\Exception $e) {
          $this->llog('error', "Plugin " . $step->plugin . " error during finalization of petition " . $petition->id . ": " . $e->getMessage());
        }
      }
    }
  }

  /**
   * Obtain a Petition's token.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $id   Petition ID
   * @return string     Petition token
   */

  public function getToken(int $id): string {
    // We use this function rather than have the invoking code access the
    // entity directly so we can allocate the token and persist it if there
    // isn't yet one.

    $petition = $this->get($id);

    if(empty($petition->token)) {
      // No token, so allocate one
      $petition->token = RandomString::generateToken();

      $this->save($petition);
    }

    return $petition->token;
  }

  /**
   * Run Provisioning for a Petition.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $id   Petition ID
   */

  public function provision(int $id) {
    // AR-Petition-2 When a Petition is finalized, any configured Provisioners will run, including those in Enrollment Only mode.
    $this->llog('rule', "AR-Petition-2 Running Provisioning for Petition $id");

    $petition = $this->get($id);

    if($petition->status != PetitionStatusEnum::Finalizing) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.status.finalizing', [$id]));
    }

    if(!$petition->enrollee_person_id) {
      // No Person associated with the Petition, so nothing to do
      $this->llog('debug', "No Enrollee Person ID found in Petition $id, so not Provisioning");
      return;
    }

    $People = TableRegistry::getTableLocator()->get('People');

    $People->requestProvisioning(id: $petition->enrollee_person_id, context: ProvisioningContextEnum::Enrollment);
  }

  /**
   * Application Rule to determine if an Enrollee Email is required.
   *
   * @since  COmanage Registyr v5.1.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleEnrolleeEmail($entity, $options) {
    // Whether or not an Enrollee Email address is required depends on the
    // Enrollment Flow configuration, so it's a bit cleaner to do this as an
    // application rule rather than a validation rule. Note this is _not_ an
    // official Registry Application Rule.

    if(!empty($entity->enrollment_flow_id)) {
      $EnrollmentFlows = TableRegistry::getTableLocator()->get('EnrollmentFlows');

      $flow = $EnrollmentFlows->get($entity->enrollment_flow_id);

      if(isset($flow->collect_enrollee_email) && $flow->collect_enrollee_email) {
        // Enrollee Email is required

        if(empty($entity->enrollee_email)) {
          return __d('error', 'Petitions.enrollee_email');
        }
      }
    }
    
    return true;
  }

  /**
   * Start a new Petition.
   * 
   * @since  Registry v5.1.0
   * @param  int    $enrollmentFlowId       Enrollment Flow ID
   * @param  string $petitionerIdentifier   Authenticated Petitioner Identifier (NOT Person ID)
   * @param  int    $petitionerPersonId     Petitioner Person ID, if known
   * @param  bool   $isEnrollee             If true, the Petitioner is also the Enrollee
   * @return Petition                       Newly created Petition
   */

  public function start(
    int     $enrollmentFlowId,
    string  $petitionerIdentifier=null,
    int     $petitionerPersonId=null,
    bool    $isEnrollee=false,
    string  $enrolleeEmail=null
  ): \App\Model\Entity\Petition {
    $petition = $this->newEntity([
      'enrollment_flow_id'    => $enrollmentFlowId,
      'status'                => PetitionStatusEnum::Created,
      'petitioner_identifier' => $petitionerIdentifier,
      'petitioner_person_id'  => $petitionerPersonId,
      'enrollee_email'        => $enrolleeEmail,
      'enrollee_identifier'   => $isEnrollee ? $petitionerIdentifier : null,
      'enrollee_person_id'    => $isEnrollee ? $petitionerPersonId : null
    ]);

    $this->saveOrFail($petition);

    // We don't create Petition History on start since it's sort of implied
    // by the Petition having been created

    return $petition;    
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

    $validator->add('status', [
      'content' => ['rule' => ['inList', PetitionStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $validator->add('cou_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('cou_id');

    $this->registerStringValidation($validator, $schema, 'enrollee_identifier', false);

    $validator->add('enrollee_email', [
      'content' => ['rule'    => ['email'],
                    'message' => __d('error', 'input.invalid.email')]
    ]);
    // See ruleEnrolleeEmail for additional logic
    $validator->allowEmptyString('enrollee_email');

    $validator->add('enrollee_person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('enrollee_person_id');

    $this->registerStringValidation($validator, $schema, 'petitioner_identifier', false);

    $validator->add('petitioner_person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('petitioner_person_id');

    return $validator; 
  }
}