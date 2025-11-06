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

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\EnrollmentActorEnum;
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
    $this->setAllowLookupPrimaryLink([
      'continue',
      'finalize',
      'pending',
      'result',
      'resume',
      'terminate'
    ]);
    $this->setRedirectGoal(action: 'terminate', goal: 'self');

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
      'EnrollmentFlows' => ['EnrollmentFlowSteps' => 
        // This magic will pass the Step plugin configuration to each Cell automatically
        array_merge($this->EnrollmentFlows->EnrollmentFlowSteps->getPluginRelations(),
                    ['sort' => ['ordr' => 'ASC']])
      ],
      'EnrolleePeople' => ['PrimaryName' => ['foreignKey' => 'person_id']],
      'PetitionerPeople' => ['PrimaryName' => ['foreignKey' => 'person_id']],
      'PetitionHistoryRecords' => [
        'ActorPeople' => ['PrimaryName' => ['foreignKey' => 'person_id']]
      ],
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
      'cous' => [
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
        // terminate an in-progress Petition
        'terminate' =>   ['platformAdmin', 'coAdmin'],
        // Any approver for the associated Enrollment Flow can view the entire Petition
        'view' =>     ['platformAdmin', 'coAdmin', 'approver']
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
   * Find the Approver Group for the specified Step for this Petition.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $id         Petition ID
   * @param  int    $stepId     Enrollment Flow Step ID
   * @return int                Group ID
   * @return bool               true if $personId is an Approver for this Step for this Petition, false otherwise
   */

  public function approverGroupId(int $id, int $stepId): int {
    $EnrollmentFlowSteps = TableRegistry::getTableLocator()->get("EnrollmentFlowSteps");
    $Groups = TableRegistry::getTableLocator()->get("Groups");

    $petition = $this->get($id);

    $step = $EnrollmentFlowSteps->get($stepId);

    if(!empty($step->approver_group_id)) {
      // If there is an Approver Group set, use that.
      
      return $step->approver_group_id;
    } else {
      if(!empty($petition->cou_id)) {
        // If there is a COU set on the Petition (which is why this function is here and
        // not in EnrollmentFlowStepsTable) use the Approvers Group for that COU.

        return $Groups->getApproversGroupId(couId: $petition->cou_id);
      } else {
        // $personId must be a member of the Approvers Group for the CO.

        return $Groups->getApproversGroupId(coId: $this->calculateCoForRecord($petition));
      }
    }
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
   * Perform Petition derivations.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $id   Petition ID
   */

  public function derive(int $id) {
    $petition = $this->get($id, contain: [
                                  'EnrollmentFlows' => [
                                    'EnrollmentFlowSteps' => array_merge(
                                      $this->EnrollmentFlows->EnrollmentFlowSteps->getPluginRelations(),
                                      ['sort' => ['EnrollmentFlowSteps.ordr' => 'ASC']]
                                    )
                                ]]);

    if($petition->isComplete()) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.completed', [$id]));
    }
    
    // Tell each plugin to perform derivations

    if(!empty($petition->enrollment_flow->enrollment_flow_steps)) {
      foreach($petition->enrollment_flow->enrollment_flow_steps as $step) {
        if($step->status == SuspendableStatusEnum::Suspended) {
          // Skip suspended steps
          continue;
        }

        $Plugin = TableRegistry::getTableLocator()->get($step->plugin);

        // Plugins cannot interrupt finalization by returning false or throwing errors during
        // derivations since the Person record has been constructed, but if we catch an
        // Exception we'll at least log it
        try {
          if(method_exists($Plugin, "derive")) {
            // We have "CoreEnroller.AttributeCollectors" but we want "attribute_collector"
            $pmodel = Inflector::underscore(Inflector::singularize(StringUtilities::pluginModel($step->plugin)));

            $Plugin->derive($step->$pmodel->id, $petition);
          }
        }
        catch(\Exception $e) {
          $this->llog('error', "Plugin " . $step->plugin . " error during finalization of petition " . $petition->id . ": " . $e->getMessage());
        }
      }
    }
  }

  /**
   * Finalize a Petition.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $id   Petition ID
   */

  public function finalize(int $id) {
    $petition = $this->get($id, contain: 'EnrollmentFlows');

    if($petition->isComplete()) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.completed', [$id]));
    }
    
    // Update the Petition status and create a History Record.
    $petition->status = PetitionStatusEnum::Finalized;

    $this->saveOrFail($petition, ['associated' => false]);

    $this->PetitionHistoryRecords->record(
      petitionId:           $petition->id,
      enrollmentFlowStepId: null,
      action:               PetitionActionEnum::Finalized,
      comment:              __d('result', 'Petitions.finalized')
      // actorPersonId
    );

    if(!empty($petition->enrollment_flow->finalization_message_template_id)) {
      // A finalization Message Template was specified, use it to notify the Enrollee.
      // We use the enrollee_email address, if populated, otherwise we generate a
      // Notification to the Enrollee Person (which may or may not have a deliverable
      // email address).

      $MessageTemplates = TableRegistry::getTableLocator()->get('MessageTemplates');

      $template = $MessageTemplates->get($petition->enrollment_flow->finalization_message_template_id);

      $template->setContextPetition($petition);

      if(!empty($petition->enrollee_email)) {
        // Send the message. sendEmailToAddress will throw an Exception if SMTP failed,
        // but if there is no SMTP server configured we'll just get false back.

        // Because we're calling DeliveryUtilities directly we need to generate the message
        // from the template here.

        $template->generateMessage();

        if(!DeliveryUtilities::sendEmailToAddress(
          coId:       $petition->enrollment_flow->co_id,
          recipient:  $petition->enrollee_email,
          subject:    $template->getMessagePart('subject'),
          body_text:  $template->getMessagePart('body_text'),
          body_html:  $template->getMessagePart('body_html')
        )) {
          throw new \RuntimeException("Message delivery failed"); // XXX I18n. can we get an exception from sendEmailToAddress instead?
        }
      } elseif(!empty($petition->enrollee_person)) {
        // Register a Notification.

        $Notifications = TableRegistry::getTableLocator()->get('Notifications');

        $Notifications->register(
          subjectPersonId: $petition->enrollee_person_id,
          subjectGroupId: null,
          actorPersonId: null, //$actorInfo['person_id'],
          recipientPersonId: $petition->enrollee_person_id,
          recipientGroupId: null,
          action: ActionEnum::PetitionFinalized,
          comment: __d('result', 'Petitions.finalized'),
          messageTemplate: $template,
          // We'll set the source to be the URL to the Petition itself
          source: [
            'controller'  => 'petitions',
            'action'      => 'view',
            $id
          ],
          mustResolve: false
        );
      } else {
        $this->llog('debug', "Cannot send finalization notification for Petition " . $id . " due to lack of email or enrollee Person rocerd");
      }
    }
  }

  /**
   * Modify an index Query to specify how to filter on the requested CO.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Query  $query  Query object
   * @param  int    $coId   CO ID to filter on
   * @return Query          Modified query
   */

  public function filterIndexByCO(Query $query, int $coId): Query {
    return $query->where(['EnrollmentFlows.co_id' => $coId]);
  }

  /**
   * Mark a Petition as a Duplicate
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $id       Petition ID
   * @param  int    $stepId   Enrollment Flow Step ID
   * @param  string $comment  Comment
   * @return bool             true on success
   */

  public function flagDuplicate(int $id, ?int $stepId=null, ?string $comment=null): bool {
    $petition = $this->get($id);

    if($petition->isComplete()) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.completed', [$id]));
    }

    $petition->status = PetitionStatusEnum::Duplicate;

    $this->saveOrFail($petition);

    $this->PetitionHistoryRecords->record(
      petitionId:           $petition->id,
      enrollmentFlowStepId: null,
      action:               PetitionActionEnum::FlaggedDuplicate,
      comment:              $comment ?? __d('result', 'Petitions.flaggedduplicate')
    );

    if($stepId) {
      // Also create a Petition Step Result (normally handled by finishStep)
      $this->PetitionStepResults->record(
        petitionId:           $id,
        enrollmentFlowStepId: $stepId,
        comment:              $comment ?? __d('result', 'Petitions.flaggedduplicate')
      );
    }

    return true;
  }

  /**
   * Determine the Name associated with the Enrollee for this Petition. Not all Petitions
   * will have Enrollee Names, and some Petitions could have more than one Name. This
   * function will try to find a suitable Name, but no guarantees can be made as to the
   * result; different values can be returned across subsequent calls, especially if the
   * Petition state changes.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int   $id    Petition ID
   * @return string       A name if found, null otherwise
   */

  public function getEnrolleeName(int $id): ?string {
    $ret = null;

    // First see if there is an Enrollee Person associated with the Petition, which would
    // be the case for (eg) account linking. If so, use their Primary Name.

    $petition = $this->get($id, contain: [
      'EnrolleePeople' => 'PrimaryName',
      'PetitionStepResults' => [
        'EnrollmentFlowSteps' => $this->PetitionStepResults->EnrollmentFlowSteps->getPluginRelations()
      ]
    ]);

    if(!empty($petition->enrollee_person->primary_name)) {
      return $petition->enrollee_person->primary_name->full_name;
    }

    // Next walk through the Petition Step Results (ie: the completed Steps) and query the
    // associated plugins for a Name. We'll stop at the first one we find, which might or
    // might not be the correct thing to do under all circumstances. Note the step results
    // are returned in a non-deterministic order.

    foreach($petition->petition_step_results as $psr) {
      $PluginTable = TableRegistry::getTableLocator()->get($psr->enrollment_flow_step->plugin);

      if(method_exists($PluginTable, "enrolleeName")) {
        $pmodel = StringUtilities::pluginToEntityField($psr->enrollment_flow_step->plugin);

        $name = $PluginTable->enrolleeName($psr->enrollment_flow_step->$pmodel, $petition->id);

        if(!empty($name)) {
          return $name;
        }
      }
    }

    return $ret;
  }

  /**
   * Perform Petition hydration.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $id   Petition ID
   * @throws InvalidArgumentException
   * @throws RuntimeException
   */

  public function hydrate(int $id) {
    // This is intended to be the first part of finalization, so we set the Petition status
    // to Finalizing.

    $petition = $this->get($id, contain: [
                                  'EnrollmentFlows' => [
                                    'EnrollmentFlowSteps' => array_merge(
                                      $this->EnrollmentFlows->EnrollmentFlowSteps->getPluginRelations(),
                                      ['sort' => ['EnrollmentFlowSteps.ordr' => 'ASC']]
                                    )
                                ]]);

    if($petition->isComplete()) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.completed', [$id]));
    }

    // We explicitly start a transaction here. If a Plugin throws an Exception, we'll rollback
    // everything (including the Petition status), and then create a separate History Record.
    $cxn = $this->getConnection();
    $cxn->begin();

    try {
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

      // Tell each plugin to perform hydration

      if(!empty($petition->enrollment_flow->enrollment_flow_steps)) {
        foreach($petition->enrollment_flow->enrollment_flow_steps as $step) {
          if($step->status == SuspendableStatusEnum::Suspended) {
            // Skip suspended steps
            continue;
          }

          $Plugin = TableRegistry::getTableLocator()->get($step->plugin);

          // Although not encouraged, Plugins _can_ interrupt finalization during hydration
          // by throwing an error. This is intended for extreme scenarios only that would
          // prevent a coherent construction of the Person record, such as the registration
          // of a duplicate identity that was not detected during an earlier step. This will
          // cause the entire finalization process to fail and rollback.
          try {
            // TODO: We need to take into account if the plugin has been disabled
            if(method_exists($Plugin, "hydrate")) {
              // We have "CoreEnroller.AttributeCollectors" but we want "attribute_collector"
              $pmodel = Inflector::underscore(Inflector::singularize(StringUtilities::pluginModel($step->plugin)));

              $Plugin->hydrate($step->$pmodel->id, $petition);
            }
          }
          catch(\OverflowException $e) {
            // Rollback the transaction and then flag the Petition as a duplicate
            $cxn->rollback();
            
            $this->flagDuplicate($id, $step->id, $e->getMessage());

            // Rethrow the exception to cause a redirect to the duplicate URL
            throw $e;
          }
          catch(\Exception $e) {
            $this->llog('error', "Plugin " . $step->plugin . " error during hydration of petition " . $petition->id . ": " . $e->getMessage());

            // Pass the Step ID as the exception code so we can retrieve it below
            throw new \RuntimeException($e->getMessage(), $step->id);
          }
        }
      }
    }
    catch(\OverflowException $e) {
      // Catch and rethrow the Exception so it doesn't get handled by the \Exception block
      throw $e;
    }
    catch(\Exception $e) {
      // Rollback the transaction and then try to record Petition History
      $cxn->rollback();

      $this->PetitionHistoryRecords->record(
        petitionId:           $petition->id, 
        enrollmentFlowStepId: $e->getCode(),
        action:               PetitionActionEnum::Finalized,
        comment:              $e->getMessage()
      );
    }

    // We're done, commit the transaction
    $cxn->commit();
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
   * Determine if a Person can approve the specified Step of the specified Petition.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $id         Petition ID
   * @param  int    $stepId     Enrollment Flow Step ID
   * @param  int    $personId   Person ID
   * @return bool               true if $personId is an Approver for this Step for this Petition, false otherwise
   */

  public function isApprover(int $id, int $stepId, int $personId): bool {
    // This function is here and not in EnrollmentFlowStepsTable because the Approvers Group
    // can be influenced by the COU ID of the Petition.

    // $EnrollmentFlowSteps = TableRegistry::getTableLocator()->get("EnrollmentFlowSteps");

    $step = $this->EnrollmentFlows->EnrollmentFlowSteps->get($stepId);

    if($step->actor_type == EnrollmentActorEnum::Approver) {
      $GroupMembers = TableRegistry::getTableLocator()->get("GroupMembers");

      return $GroupMembers->isMember(
        groupId: $this->approverGroupId($id, $stepId),
        personId: $personId
      );
    }

    return false;
  }

  /**
   * Determine if a Person can Approve any Step for the specified Petition..
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $id         Petition ID
   * @param  int    $personId   Person ID
   * @return bool               true if $personId is an Approver for this Flow, false otherwise
   */

  public function isApproverForFlow(int $id, int $personId): bool {
    // This function is here and not in EnrollmentFlowStepsTable because the Approvers Group
    // can be influenced by the COU ID of the Petition.

    $petition = $this->get($id);

    $steps = $this->EnrollmentFlows->EnrollmentFlowSteps
                  ->find()
                  ->where([
                    'enrollment_flow_id' => $petition->enrollment_flow_id,
                    'status' => SuspendableStatusEnum::Active
                  ])
                  ->orderBy(['EnrollmentFlowSteps.ordr' => 'ASC'])
                  ->all();

    foreach($steps as $step) {
      if($step->actor_type == EnrollmentActorEnum::Approver) {
        if($this->EnrollmentFlows->EnrollmentFlowSteps->ApproverGroups->GroupMembers->isMember(
          groupId: $this->approverGroupId($id, $step->id),
          personId: $personId
        )) {
          return true;
        }
      }
    }

    return false;
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
   * Terminate a Petition.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int  $id   Petition ID
   */

  public function terminate(int $id) {
    $petition = $this->get($id);

    if($petition->isComplete()) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.completed', [$id]));
    }

    $petition->status = PetitionStatusEnum::Terminated;

    $this->save($petition);
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