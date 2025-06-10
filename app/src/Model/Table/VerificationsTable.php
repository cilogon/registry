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
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\ActionEnum;
use App\Lib\Enum\VerificationMethodEnum;
use App\Lib\Random\RandomString;
use App\Lib\Util\DeliveryUtilities;
use App\Model\Entity\Verification;
use Random\RandomException;

class VerificationsTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\UpsertTrait;
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
    $this->setRequiresCO(true);
    
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
   * Define business rules.
   *
   * @since  COmanage Registry v5.1.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // This is not an Application Rule per se, but we don't allow changes to
    // completed Verifications under most circumstances
    $rules->add([$this, 'ruleIsVerified'],
                'isVerified',
                ['errorField' => 'email_address_id']);
    
    return $rules;
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

    // We'll try to record history, but most likely it'll fail due to lack of a Person
    $this->recordHistory($verification);

    return $verification;
  }

  /**
   * Record a manual Verification.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int          $emailAddressId       Email Address ID
   * @return Verification                       Verification entity
   */

  public function manual(int $emailAddressId): Verification {
    $data = [
      'email_address_id'    => $emailAddressId,
      'method'              => VerificationMethodEnum::Manual,
      'code'                => null,
      'verification_time'   => date('Y-m-d H:i:s', time())
    ];

    $where = ['email_address_id' => $emailAddressId];

    $verification = $this->upsert($data, $where);

    $this->recordHistory($verification);

    return $verification;
  }

  /**
   * Record history associated with a Verification.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  Verification $verification   Verification
   * @return bool                         true if history was recorded, false otherwise
   */

  protected function recordHistory(
    Verification  $verification
  ): bool {
    // Note HistoryTrait has a generic recordHistory(), but we need somewhat different logic.

    // We need a Person ID in order to record history. We may or may not have one for $petitionId
    // (in most cases we won't because they Person hasn't been hydrated yet), but we'll check
    // just in case.

    $personId = null;
    $addr = "";

    if(!empty($verification->email_address_id)) {
      $addr = $this->EmailAddresses->get($verification->email_address_id);

      if(!empty($addr->person_id)) {
        $personId = $addr->person_id;
      }
    } elseif($verification->petition_id) {
      $pt = $this->Petitions->get($verification->petition_id);

      if(!empty($pt->enrollee_person_id)) {
        $personId = $pt->enrollee_person_id;
      }
    }

    if($personId) {
      $action = ActionEnum::EmailVerified;
      $comment = "";

      switch($verification->method) {
        case VerificationMethodEnum::Code:
          $comment = __d('result', 'EmailAddresses.verify.code', [$addr->mail]);
          break;
        case VerificationMethodEnum::Manual:
          $action = ActionEnum::EmailForceVerified;
          $comment = __d('result', 'EmailAddresses.verify.manual', [$addr->mail]);
          break;
        case VerificationMethodEnum::PetitionHandoff:
          $comment = __d('result', 'EmailAddresses.verify.handoff', [$addr->mail]);
          break;
        case VerificationMethodEnum::TrustedSource:
          $comment = __d('result', 'EmailAddresses.verify.trust', [$addr->mail, $verification->source]);
          break;
        case null:
          if(!empty($verification->code)) {
            // We sent a code but it has not yet been verified
            $action = ActionEnum::EmailVerifyCodeSent;
            $comment = __d('result', 'EmailAddresses.verify.code.sent', [$addr->mail]);
          }
          break;
      }

      $HistoryRecords = TableRegistry::getTableLocator()->get('HistoryRecords');

      $HistoryRecords->recordForPerson(
        $personId,
        $action,
        $comment
      );

      return true;
    }

    return false;
  }

  /**
   * Request a Verification for the specified petition and email address.
   *
   * @param int $petitionId Petition ID
   * @param string $mail Email Address to verify
   * @param int $messageTemplateId Message Template ID
   * @param int $validity Request validity, in minutes
   * @param int $codeLength
   * @param string|null $codeCharset
   * @param string|null $codeRegex
   * @param int|null $verificationId If set, resend Verification for this request
   * @return int                        Verification ID
   * @throws RandomException
   * @since  COmanage Registry v5.1.0
   */

  public function requestCodeForPetition(
    int     $petitionId,
    string  $mail,
    int     $messageTemplateId,
    int     $validity,
    int     $codeLength,
    ?string  $codeCharset,
    ?string  $codeRegex,
    int     $verificationId = null
  ): int {
    // First generate a new code
    $code = RandomString::generateToken($codeLength, $codeCharset, $codeRegex);
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
      code:               $this->tokenToD($code)
    );

    // We'll try to record history, but most likely it'll fail due to lack of a Person
    $this->recordHistory($verification);

    return $verification->id;
  }
  
  /**
   * Application Rule to determine if the Verification is already verified.
   *
   * @since  COmanage Registyr v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleIsVerified($entity, $options) {
    // We reject updates to a Verification once there is a verification time,
    // except to unverify (reset), which is indicated by a blank method.
    if(!empty($entity->method)
       && !empty($verification->verification_time)) {
      return __d('error', 'Verifications.already');
    }
    
    return true;
  }

  /**
   * Record a trusted source Verification.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $emailAddressId       Email Address ID
   * @param  string $source               Description of Trusted Source
   * @return Verification                 Verification entity
   */

  public function trustedSource(int $emailAddressId, string $source): Verification {
    $data = [
      'email_address_id'    => $emailAddressId,
      'method'              => VerificationMethodEnum::TrustedSource,
      'trusted_source'      => $source,
      'code'                => null,
      'verification_time'   => date('Y-m-d H:i:s', time())
    ];

    $where = ['email_address_id' => $emailAddressId];

    $verification = $this->upsert($data, $where);

    $this->recordHistory($verification);

    return $verification;
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

    if($verification->code !== $code) {
      $this->llog('debug', "Invalid code provided for Verification $id");
      throw new \InvalidArgumentException(__d('error', 'Verifications.code'));
    }

    if($verification->request_expiration_time->lessThan(FrozenTime::now())) {
      $this->llog('debug', "Verification $id has expired");
      throw new \InvalidArgumentException(__d('error', 'Verifications.expired'));
    }

    $this->llog('debug', "Successfully processed Verification $id");

    $verification->method = VerificationMethodEnum::Code;
    // This field signifies that the email is verified
    $verification->verification_time = time();
    
    $this->saveOrFail($verification);

    $this->recordHistory($verification);

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
   * Converts a token by adding dashes for improved readability.
   *
   * @param string $token The token to be formatted
   * @param int    $jump  Characters to skip before adding a dash
   * @return string        The formatted token with dashes
   * @since  COmanage Registry v5.2.0
   */
  public function tokenToD(string $token, int $jump = 4): string
  {
    // Insert some dashes to improve readability
    $dtoken = '';

    for($i = 0, $iMax = strlen($token); $i < $iMax; $i++) {
      $dtoken .= $token[$i];

      if((($i + 1) % $jump == 0)
        && ($i + 1 < strlen($token))) {
        $dtoken .= '-';
      }
    }

    return $dtoken;
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

    $this->registerStringValidation($validator, $schema, 'trusted_source', false);

    $validator->add('email_address_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('email_address_id');

    $validator->add('petition_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('petition_id');

    $validator->add('attempts_count', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('attempts_count');

    return $validator;
  }
}