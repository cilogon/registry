<?php
/**
 * COmanage Registry T And C Agreements Table
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\ActionEnum;

class TAndCAgreementsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
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
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);
    
    // Define associations
    $this->belongsTo('TermsAndConditions');
    $this->belongsTo('People');
    
    $this->setDisplayField('agreement_time');
    
    $this->setPrimaryLink(['terms_and_conditions_id', 'person_id']);
    $this->setRequiresCO(true);

    // Enable the Model Specific REST API for this Table
    $this->enableMsrApi();

    // There is no direct UI for T&C Agreements, these Permissions are
    // for the REST API. Only read operations are supported, write
    // operations are via TermsAndConditions.
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
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }
  
  /**
   * Record a T&C Agreement.
   * 
   * As of v5 this function no longer triggers provisioning. If provisioning
   * should happen following T&C Agreement the calling context should request it.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $termsAndConditionsId Terms And Conditions ID
   * @param  int    $personId             Person ID to record Agreement for
   * @param  int    $actorPersonId        Person ID completing Agreement
   * @param  string $identifier           Authenticated Identifier of $actorPersonId
   * @param  int    $agreementTime        Time of Agreement (if not now)
   * @param  int    $petitionId           If Agreement was collected via a Petition, the Petition ID
   * @return TAndCAgreement               Newly created TAndCAgreement
   */

  public function record(
    int     $termsAndConditionsId,
    int     $personId,
    int     $actorPersonId,
    string  $identifier=null,
    ?int    $agreementTime=null,
    ?int    $petitionId=null
  ): \App\Model\Entity\TAndCAgreement {
    // GMR-2 will enforce that the various foreign keys all point to entities
    // in the same CO when we try to save. This also means we don't need to
    // get($personId) just to verify it exists.

    // Pull the T&C
    $tandc = $this->TermsAndConditions->get($termsAndConditionsId);

    // Record the T&C Agreement

    $agreement = $this->newEntity([
      'terms_and_conditions_id' => $termsAndConditionsId,
      'person_id'               => $personId,
      'agreement_time'          => date('Y-m-d H:i:s', $agreementTime ?? time()),
      // We require $identifier to be passed because it won't always be the same
      // as Changelog's actor_identifier (eg: Petitions)
      'identifier'              => $identifier
    ]);

    $this->saveOrFail($agreement);

    // Create a History Record
    $action = ActionEnum::TAndCAgreement;
    $comment = __d('result', 'TermsAndConditions.agreed', [$tandc->description]);

    if($petitionId) {
      $action = ActionEnum::TAndCAgreementPetition;
      $comment = __d('result', 'TermsAndConditions.agreed.petition', [$tandc->description, $petitionId]);
    } elseif($personId != $actorPersonId) {
      $action = ActionEnum::TAndCAgreementBehalf;
      $comment = __d('result', 'TermsAndConditions.agreed.behalf', [$tandc->description]);
    }

    $this->People->HistoryRecords->recordForPerson(
      personId: $personId,
      action: $action,
      comment: $comment,
      actorPersonId: $actorPersonId
    );

    return $agreement;
  }

  /**
   * Revoke a T&C Agreement.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $termsAndConditionsId Terms And Conditions ID
   * @param  int    $personId             Person ID to record Agreement for
   * @param  int    $actorPersonId        Person ID completing Agreement
   * @return int                          Count of TAndCAgreements revoked, including 0 if no Agreements are found to revoke
   */

  public function revoke(
    int $termsAndConditionsId,
    int $personId,
    int $actorPersonId
  ): int {
    // Pull the T&C
    $tandc = $this->TermsAndConditions->get($termsAndConditionsId);

    // There can be more than one TAndCAgreement to revoke, eg if
    // expired Agreements also exists we'll revoke those too.
    // We don't currently handle outdated Agreements (since that requires
    // walking the changelog data), but conceptually those should probably
    // also be revoked.

    // We don't need to validate that $personId and $termsAndConditionsId
    // are in the same CO since this shouldn't return any results if they aren't.
    $agreements = $this->find()
                       ->where([
                        'terms_and_conditions_id' => $termsAndConditionsId,
                        'person_id'               => $personId
                       ])
                       ->all();
    
    foreach($agreements as $a) {
      // We could delete the set using deleteMany but we want to record history as we go

      $this->deleteOrFail($a);

      $this->People->HistoryRecords->recordForPerson(
        personId: $personId,
        action: ActionEnum::TAndCAgreementRevoked,
        comment: __d('result', 'TermsAndConditions.revoked.admin', [$a->id, $tandc->description]),
        actorPersonId: $actorPersonId
      );
    }

    return $agreements->count();
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

    $validator->add('terms_and_conditions_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('terms_and_conditions_id');

    $validator->add('person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('person_id');

    $validator->add('agreement_time', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->notEmptyString('agreement_time');
    
    $this->registerStringValidation($validator, $schema, 'identifier', true);

    return $validator; 
  }
}