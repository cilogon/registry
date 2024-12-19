<?php
/**
 * COmanage Registry Petition Verifications Table
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

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\PetitionActionEnum;
use CoreEnroller\Model\Entity\PetitionVerification;

/**
 * The purpose of this table is a bit obscure. Ordinarily, verifying an EmailAddress is
 * handled via VerificationsTable, and the resulting Verification entity will have a foreign
 * key pointing to the EmailAddress entity that was verified. However, when an Enrollment
 * Flow runs, there is no EmailAddress entity yet, just whatever internal Petition state
 * the relevant plugin is maintaining.
 * 
 * PetitionVerifications are basically placeholders (and artifacts) for use until the
 * EmailAddress is created during finalization. So the expected order of usage is
 * 
 * (1) Verification created, points to Petition
 * (2) PetitionVerification created, points to Verification
 * (3) Petition finalized, EmailAddress created
 * (4) Verification updated to point to EmailAddress, EmailAddress flagged as verified
 */

class PetitionVerificationsTable extends Table {
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
    $this->belongsTo('Verifications');

    $this->setDisplayField('mail');

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
   * Verify a code.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  integer  $petitionId   Petition ID
   * @param  integer  $enrollmentFlowStepId Enrollment Flow Step ID
   * @param  string   $mail         Email Address that was verified
   * @return bool                   true if validation is successful
   * @throws \InvalidArgumentException
   */

  public function verifyCode(int $petitionId, int $enrollmentFlowStepId, string $mail, string $code): bool {
    // Find the PetitionVerification for the requested petition and address,
    // then use the verification ID to process the code.

    $pVerification = $this->find()
                          ->where([
                            'petition_id' => $petitionId,
                            'mail'        => $mail
                          ])
                          ->firstOrFail();
    
    // This will throw an error on failure
    $this->Verifications->verifyCode($pVerification->verification_id, $code);

    // Record Petition History

    $this->Petitions->PetitionHistoryRecords->record(
      petitionId:           $petitionId,
      enrollmentFlowStepId: $enrollmentFlowStepId,
      action:               PetitionActionEnum::EmailVerified,
      comment:              __d('core_enroller', 'result.EmailVerifiers.verified.history', [$mail, __d('enumeration', 'VerificationMethodEnum.C')])
// We don't have $actorPersonId yet...
//    ?int $actorPersonId=null
    );

    return true;
  }

  /**
   * Record an Email Verification from a handoff.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  integer  $petitionId           Petition ID
   * @param  integer  $enrollmentFlowStepId Enrollment Flow Step ID
   * @param  string   $mail                 Email Address that was verified
   * @return PetitionVerification           PetitionVerification
   */

  public function verifyFromHandoff(int $petitionId, int $enrollmentFlowStepId, string $mail): PetitionVerification {
    // We're only called once it has been determined that a handoff effectively verified
    // $mail, so we just need to create a Verification and a PetitionVerification.

    $verification = $this->Verifications->handoff($petitionId);

    $pVerification = $this->newEntity([
      'petition_id'     => $petitionId,
      'mail'            => $mail,
      'verification_id' => $verification->id
    ]);

    $this->saveOrFail($pVerification);

    // Record Petition History

    $this->Petitions->PetitionHistoryRecords->record(
      petitionId:           $petitionId,
      enrollmentFlowStepId: $enrollmentFlowStepId,
      action:               PetitionActionEnum::EmailVerified,
      comment:              __d('core_enroller', 'result.EmailVerifiers.verified.history', [$mail, __d('enumeration', 'VerificationMethodEnum.PH')])
// We don't have $actorPersonId yet...
//    ?int $actorPersonId=null
    );

    // We return in the format as if we used find() and contain()

    $pVerification->verification = $verification;

    return $pVerification;
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

    $this->registerStringValidation($validator, $schema, 'mail', true);

    $validator->add('verification_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('verification_id');

    return $validator;
  }
}
