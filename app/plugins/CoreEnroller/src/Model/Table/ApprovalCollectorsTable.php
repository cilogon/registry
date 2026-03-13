<?php
/**
 * COmanage Registry Approval Collectors Table Table
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
 * @package       registry-plugins
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreEnroller\Model\Table;

use App\Lib\Enum\AllTernaryEnum;
use App\Lib\Enum\NotificationStatusEnum;
use App\Lib\Enum\PetitionActionEnum;
use App\Lib\Enum\PetitionStatusEnum;
use App\Lib\Enum\TableTypeEnum;
use App\Lib\Util\StringUtilities;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

class ApprovalCollectorsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;  
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.2.0
   * @param  array  $config Configuration options passed to constructor
   */

  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('EnrollmentFlowSteps');
    $this->belongsTo('Groups');
    $this->belongsTo('MessageTemplates')
         ->setForeignKey('denial_message_template_id')
         ->setProperty('denial_message_template');

    $this->hasMany('CoreEnroller.PetitionApprovals')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setDisplayField('id');

    $this->setPrimaryLink('enrollment_flow_step_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['dispatch', 'display']);

    $this->setAutoViewVars([
      'modes' => [
        'type' => 'enum',
        'class' => 'AllTernaryEnum'
      ],
      'denialMessageTemplates' => [
        'type' => 'select',
        'model' => 'MessageTemplates',
        'where' => ['context' => \App\Lib\Enum\MessageTemplateContextEnum::EnrollmentApproval]
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'dispatch' => true,
        'display' =>  true,
        'edit' =>     ['platformAdmin', 'coAdmin'],
        // 'resend' =>  true,
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false, // This is added by the parent model
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Perform steps necessary to hydrate the Person record as part of Petition finalization.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int      $id           Approval Collector ID
   * @param  Petition $petition     Petition
   * @return bool                   true on success
   */

  public function hydrate(int $id, \App\Model\Entity\Petition $petition) {
    // $cfg = $this->get($id);
    // Approval Collector just affects the flow as it happens, and so nothing is
    // currently required at finalization.

    return true;
  }

  /**
   * Perform tasks prior to transitioning to this step.
   *
   * @since  COmanage Registry v5.2.0
   * @param  EnrollmentFlowStep $step     Enrollment Flow Step
   * @param  Petition           $petition Petition
   * @return bool                         true on success
   */

  public function prepare(
    \App\Model\Entity\EnrollmentFlowStep $step,
    \App\Model\Entity\Petition $petition
  ): bool {
    // Set this petition to Pending Verification

    $Petitions = TableRegistry::getTableLocator()->get('Petitions');

    $petition->status = PetitionStatusEnum::PendingApproval;

    $Petitions->saveOrFail($petition);

    return true;
  }
  
  /**
   * Record an Approval (or denial).
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $petitionId           Petition ID
   * @param  int    $approvalCollectorId  Approval Collector ID
   * @param  int    $approverPersonId     Approver Person ID
   * @param  bool   $approved             true if the Approval is granted, false otherwise
   * @param  string $comment              Approval (or denial) Comment
   * @throws \InvalidArgumentException
   */

  public function record(
    int     $petitionId,
    int     $approvalCollectorId,
    int     $approverPersonId,
    bool    $approved=true,
    ?string $comment=null
  ) {
    $Petitions = TableRegistry::getTableLocator()->get('Petitions');

    $cfg = $this->get($approvalCollectorId);

    $petition = $Petitions->get($petitionId);

    // First check if require_comment but no comment was provided
    
    if($cfg->require_comment && (!$comment || strlen(trim($comment)) == 0)) {
      throw new \InvalidArgumentException(__d('core_enroller', 'error.ApprovalCollectors.comment'));
    }

    // Record a PetitionApproval (which is also used for denials).

    $pa = [
      'petition_id'           => $petitionId,
      'approval_collector_id' => $approvalCollectorId,
      'approver_person_id'    => $approverPersonId,
      'approved'              => $approved,
      'comment'               => $comment
    ];

    $this->PetitionApprovals->upsertOrFail(
      $pa,
      ['petition_id' => $petitionId, 'approval_collector_id' => $approvalCollectorId]
    );

    // Next, update the Petition status.

    // If there'a another Approval step after this one we'll bounce back to Pending
    // Approval as soon as we switch to it, which creates a bit of noise but in some
    // ways is sort of correct. We could try to calculate if there is another Approval
    // step, but that's a bit complicated and not really worth the effort. We don't
    // calculate Approval during finalization because the status wouldn't stick around
    // long enough to be useful for an administrator.
    $petition->status = $approved ? PetitionStatusEnum::Approved : PetitionStatusEnum::Denied;

    $Petitions->saveOrFail($petition);

    // Record PetitionHistory.

    $this->llog('debug', "Petition " . $petition->id . ($approved ? " approved" : " denied") . " by Person " . $approverPersonId);

    $Petitions->PetitionHistoryRecords->record(
      petitionId:           $petitionId,
      enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
      action:               $approved ? PetitionActionEnum::Approved : PetitionActionEnum::Denied,
      comment:              __d('core_enroller', 'result.ApprovalCollectors.' . ($approved ? 'approved' : 'denied'))
    );

    // Finally, resolved the Notification created by the handoff.

    $Notifications = TableRegistry::getTableLocator()->get('Notifications');

    // The URL was originally created by $EnrollmentFlows->calculateNextStep, but we
    // can easily reconstruct what it should be

    $url = [
      'plugin' => 'CoreEnroller',
      'controller' => 'approval_collectors',
      'action' => 'dispatch',
      $approvalCollectorId,
      '?' => ['petition_id' => $petitionId]
    ];

    $Notifications->resolveFromSource(
      source: $url,
      resolution: NotificationStatusEnum::Resolved,
      resolverPersonId: $approverPersonId
    );
  }

  /**
   * Set validation rules.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */

  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('enrollment_flow_step_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('enrollment_flow_step_id');

    $validator->add('require_comment', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('require_comment');

    $validator->add('denial_message_template_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('denial_message_template_id');
    
    $validator->add('redirect_on_denial', [
      'content' => ['rule' => 'url']
    ]);
    $validator->allowEmptyString('redirect_on_denial');

    return $validator;
  }
}
