<?php
/**
 * COmanage Registry Verifications Table
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

use Cake\I18n\FrozenTime;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Lib\Enum\VerificationMethodEnum;
use App\Lib\Random\RandomString;
use App\Lib\Util\DeliveryUtilities;
use App\Model\Entity\Verification;

class VerificationsTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
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
    // Timestamp behavior handles created/modified updates
    // We enable Changelog here in case a Step decides to revise its result
    $this->addBehavior('Changelog');
    $this->addBehavior('Timestamp');
    $this->addBehavior('Timezone');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);
    
    // Define associations
    $this->belongsTo('EmailAddresses');
    $this->belongsTo('Petitions');

    // Verifications aren't generally going to be directly rendered or managed
    $this->setDisplayField('id');
    
    $this->setPrimaryLink(['email_address_id', 'petition_id']);
    $this->setRequiresCO(false);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false,
        'edit' =>     false,
        'view' =>     false
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false,
        'index' =>    false
      ]
    ]);
  }

  /**
   * Record a handoff Verification.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $petitionId   Petition ID
   * @return Verification         Verification entity
   */

  public function handoff(int $petitionId): Verification {
    // Note when this is called the relevant EmailAddress probably doesn't exist yet,
    // so we just link the Verification to the Petition (since otherwise we'd need to
    // foreign key into a plugin table, which we're not allowed to do from core code)
    // and expect the Enrollment Flow plugin to clean this up later.

    // Because we don't know the email address we also can't perform a uniqueness check
    // (there might be multiple verifications for the same Petition).

    $verification = $this->newEntity([
      'petition_id'       => $petitionId,
      'method'            => VerificationMethodEnum::PetitionHandoff,
      'verification_time' => date('Y-m-d H:i:s', time())
    ]);

    $this->saveOrFail($verification);

    return $verification;
  }

  /**
   * Record a manual Verification.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $emailAddressId       Email Address ID
   */

  public function manual(int $emailAddressId) {
    // First, see if we have a Verification for this Email Address

    $verification = $this->find()->where(['email_address_id' => $emailAddressId])->first();

    if($verification) {
      if(!empty($verification->verification_time)) {
        // If there is a campleted Verification, we don't allow a manual Verification

        throw new \InvalidArgumentException(__d('error', 'Verifications.already'));
      } else {
        // If there is a pending Verification, we'll override and update it

        $verification->code = null;
        $verification->method = VerificationMethodEnum::Manual;
        $verification->verification_time = date('Y-m-d H:i:s', time());
      }
    } else {
      // Create a new Verification

      $verification = $this->newEntity([
        'email_address_id'    => $emailAddressId,
        'method'              => VerificationMethodEnum::Manual,
        'verification_time'   => date('Y-m-d H:i:s', time())
      ]);
    }

    $this->save($verification);

    // We don't record history here because we may not have a Person context yet (ie: Petitions)
  }

  /**
   * Request a Verification for the specified petition and email address.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $petitionId         Petition ID
   * @param  string $mail               Email Address to verify
   * @param  int    $messageTemplateId  Message Template ID
   * @param  int    $validity           Request validity, in minutes
   * @param  int    $verificationId     If set, resend Verification for this request
   * @return int                        Verification ID
   */

  public function requestCodeForPetition(
    int     $petitionId,
    string  $mail,
    int     $messageTemplateId,
    int     $validity,
    int     $verificationId=null
  ): int {
    // First generate a new code
    $code = RandomString::generateCode();
    $expiry = date('Y-m-d H:i:s', time() + ($validity * 60));

    $verification = null;

    // If there's already a Verification, pull it, check it, and update it
    if($verificationId) {
      $verification = $this->get($verificationId);

      if($verification->petition_id != $petitionId) {
        throw new \InvalidArgumentException(__d('error', 'Verifications.petition'));
      }

      $verification->code = $code;
      $verification->request_expiration_time = $expiry;
    } else {
      $verification = $this->newEntity([
        'code'                    => $code,
        'verification_time'       => null,
        'request_expiration_time' => $expiry,
        'method'                  => null,
        'email_address_id'        => null,
        'petition_id'             => $petitionId
      ]);
    }

    $this->saveOrFail($verification);

    // Send the verification message

    DeliveryUtilities::sendEmailFromTemplate(
      address:            $mail,
      messageTemplateId:  $messageTemplateId,
      code:               $code
    );

    return $verification->id;
  }

  /**
   * Unverify a Verification.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $emailAddressId   Email Address ID
   */

  public function unverify(int $emailAddressId) {
    // First, see if we have a Verification for this Email Address

    $verification = $this->find()->where(['email_address_id' => $emailAddressId])->first();

    if($verification) {
      $verification->code = null;
      $verification->method = null;
      $verification->verification_time = null;
      $verification->request_expiration_time = null;

      $this->save($verification);
    }
    // If we don't have a verification, we don't do anything
  }

  /**
   * Check a verification code.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $id     Verification ID
   * @param  string $code   Code, as provided by the verifier
   * @return bool           true if validation is successful
   * @throws \InvalidArgumentException
   */

  public function verifyCode(int $id, string $code): bool {
    $verification = $this->get($id);

    if($verification->verification_time) {
      $this->llog('debug', "Verification $id has already been processed");
      throw new \InvalidArgumentException(__d('error', 'Verifications.processed'));
    }

    if($verification->request_expiration_time->lt(FrozenTime::now())) {
      $this->llog('debug', "Verification $id has expired");
      throw new \InvalidArgumentException(__d('error', 'Verifications.expired'));
    }

    if($verification->code !== $code) {
      $this->llog('debug', "Invalid code provided for Verification $id");
      throw new \InvalidArgumentException(__d('error', 'Verifications.code'));
    }

    $this->llog('debug', "Successfully processed Verification $id");

    $verification->method = VerificationMethodEnum::Code;
    $verification->verification_time = time();
    
    $this->saveOrFail($verification);

    return true;
  }

  /**
   * Create a new Verification from an existing Verification associated with a Petition,
   * but linked to the specified Email Address.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $id             Verification ID
   * @param  int  $emailAddressId Email Address ID
   */

  public function verifyFromPetition(int $id, int $emailAddressId) {
    // Due to AR-GMR-3, we can't reassign a Verification from a Petition to an Email Address,
    // so we duplicate it instead.

    $oldVerification = $this->get($id);

    $newVerification = $this->newEntity($oldVerification->toArray());
    $newVerification->petition_id = null;
    $newVerification->email_address_id = $emailAddressId;

    $this->saveOrFail($newVerification);
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

    // Fields here are generally not required because not all types of verifications
    // user all fields, and some fields are not populated at the initial verification
    // request.

    $this->registerStringValidation($validator, $schema, 'code', false);

    $validator->add('verification_time', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('verification_time');

    $validator->add('request_expiration_time', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('request_expiration_time');

    $validator->add('method', [
      'content' => ['rule' => ['inList', VerificationMethodEnum::getConstValues()]]
    ]);
    $validator->allowEmptyString('method');

    $validator->add('email_address_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('email_address_id');

    $validator->add('petition_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('petition_id');

    return $validator; 
  }
}