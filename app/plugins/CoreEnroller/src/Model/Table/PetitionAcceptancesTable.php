<?php
/**
 * COmanage Registry Petition Acceptances Table
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreEnroller\Model\Table;

use Cake\I18n\FrozenTime;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\PetitionActionEnum;
use App\Lib\Enum\PetitionStatusEnum;

class PetitionAcceptancesTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.1.0
   * @param  array  $config Configuration options passed to constructor
   */

  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);

    // Define associations
    $this->belongsTo('Petitions');

    $this->setDisplayField('accepted');

    $this->setPrimaryLink('petition_id');
    $this->setRequiresCO(true);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false,
        'edit' =>     false,
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false,
        'index' =>    false
      ]
    ]);
  }

  /**
   * Process an Invitation reply.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $petitionId           Petition ID
   * @param  int  $enrollmentFlowStepId Enrollment Flow Step ID
   * @param  bool $accepted             true if the reply was accepted, false if declined
   * @throws Exceptions
   */

  public function processReply(int $petitionId, int $enrollmentFlowStepId, bool $accepted) {
    // There should already be an empty Acceptance indicating when the step was handed off
    // for Invitation, and for which we need to refer to to determine the invitation validity.
    // If it doesn't exist, that's an error.

    $acceptance = $this->find()->where(['petition_id' => $petitionId])->firstOrFail();

    // If accepted is not null, we've already processed this invitation, so that's also an error.

    if(!is_null($acceptance->accepted)) {
      throw new \RuntimeException(__d('core_enroller', 'error.PetitionAcceptances.processed'));
    }

    // Next check the create time of the original record. For this, we need the configuration.

    $InvitationAccepters = TableRegistry::getTableLocator()->get('CoreEnroller.InvitationAccepters');

    $ia = $InvitationAccepters->find()
                              ->where(['enrollment_flow_step_id' => $enrollmentFlowStepId])
                              ->firstOrFail();
    
    // A validity of 0 disables expiration

    if($ia->invitation_validity > 0) {
      $expires = $acceptance->created->addSeconds($ia->invitation_validity);

      if($expires->isPast()) {
        throw new \RuntimeException(__d('core_enroller','error.PetitionAcceptances.expired'));
      }
    }

    // We're finally ready to process the reply

    $acceptance->accepted = $accepted;

    $this->saveOrFail($acceptance);

    // Set the Petition status appropriately

    $petition = $this->Petitions->get($petitionId);

    $petition->status = $accepted ? PetitionStatusEnum::Accepted : PetitionStatusEnum::Declined;

    $this->Petitions->saveOrFail($petition);

    // Record Petition History

    $this->Petitions->PetitionHistoryRecords->record(
      petitionId:           $petitionId,
      enrollmentFlowStepId: $enrollmentFlowStepId,
      action:               PetitionActionEnum::StatusUpdated,
      comment:              __d('core_enroller', $accepted ? 'result.accept.accepted' : 'result.accept.declined')
// We don't have $actorPersonId yet...
//    ?int $actorPersonId=null
    );
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

    $validator->add('petition_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('petition_id');

    $validator->add('accepted', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('accepted');
    
    return $validator;
  }
}
