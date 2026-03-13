<?php
/**
 * COmanage Registry Email Verifiers Table Table
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

use App\Lib\Enum\AllTernaryEnum;
use App\Lib\Enum\EnrollmentActorEnum;
use App\Lib\Enum\PermittedCharactersEnum;
use App\Lib\Enum\PetitionStatusEnum;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Enum\TableTypeEnum;
use App\Lib\Util\StringUtilities;
use App\Model\Entity\Petition;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use CoreEnroller\Lib\Enum\VerificationDefaultsEnum;
use CoreEnroller\Model\Entity\EmailVerifier;

class EmailVerifiersTable extends Table {
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
   * @since  COmanage Registry v5.1.0
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
    $this->belongsTo('MessageTemplates');
    // $this->belongsTo('Types');

    // We intentionally don't hasMany PetitionIdentifiers since there should only be one
    // collector per Petition, and the net result of not having the direct foreign key
    // is that if an admin instantiates the plugin multiple times the second instance
    // will refuse to do anything.

    $this->setDisplayField('id');

    $this->setPrimaryLink('enrollment_flow_step_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['dispatch', 'display', 'resend']);

    $this->setAutoViewVars([
      'modes' => [
        'type' => 'enum',
        'class' => 'AllTernaryEnum'
      ],
      'defaults' => [
        'type' => 'enum',
        'class' => 'CoreEnroller.VerificationDefaultsEnum'
      ],
      'messageTemplates' => [
        'type' => 'select',
        'model' => 'MessageTemplates',
        'where' => ['context' => \App\Lib\Enum\MessageTemplateContextEnum::Verification]
      ],
      'cosettings' => [
        'type' => 'auxiliary',
        'model' => 'CoSettings'
      ],
      'types' => [
        'type' => 'auxiliary',
        'model' => 'Types'
      ],
      'permittedCharacters' => [
        'type' => 'enum',
        'class' => 'PermittedCharactersEnum'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'dispatch' => true,
        'display' =>  true,
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'resend' =>  true,
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
   * Assemble the set of Email Addresses that may be verified for this Petition,
   * and determine the verification status of these addresses.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  EmailVerifier  $emailVerifier  Email Verifier Entity
   * @param  Petition       $petition       Petition Entity
   * @return array                          Array of unique verifiable Email Addresses
   */

  public function assembleVerifiableAddresses(
    EmailVerifier $emailVerifier,
    Petition      $petition
  ): array {
    $ret = [];

    // Pull the set of Verifications already associated with this Petition

    $PetitionVerifications = TableRegistry::getTableLocator()->get('CoreEnroller.PetitionVerifications');

    $verifications = $PetitionVerifications->find()
                                           ->where(['PetitionVerifications.petition_id' => $petition->id])
                                           ->contain(['Verifications'])
                                           ->all();

    // The set of verifiable addresses is constructed by asking any Step that runs
    // before this Step for any email addresses they may have, and then adding in
    // the Enrollee Email, if found. (We don't bother using PetitionStepResults to
    // verify what actually ran, we simply assume that we wouldn't be called if it
    // wasn't our turn.)

    // We could figure out our Step order and then only request those Steps with a lower
    // order value, but that would require an extra query, and Flows generally don't
    // have a huge number of Steps, so we can just pull all of them and look at the
    // ones we care about.

    $steps = $this->EnrollmentFlowSteps->find()
                                       ->where([
                                        'enrollment_flow_id' => $petition->enrollment_flow_id,
                                        'status' => SuspendableStatusEnum::Active
                                       ])
                                       ->orderBy(['EnrollmentFlowSteps.ordr' => 'ASC'])
                                       ->contain($this->EnrollmentFlowSteps->getPluginRelations())
                                       ->all();
    
    // We'll start with the Enrollee Email address, if present. If we can verify it this way,
    // then we don't need to worry about verifying it if another step collected it also.
    if(!empty($petition->enrollee_email)) {
      $verified = false;

      // See if we already have a verification for this address
      $matchedVerifications = $verifications->match(['mail' => $petition->enrollee_email]);

      if($matchedVerifications->count() > 0) {
        // The matched verifications might be pending, we'll have to actually look to
        // see if there is a completed Verification.

        foreach($matchedVerifications as $pv) {
          if(($pv->verification->isVerified())) {
            // This address was already verified
            $ret[ $petition->enrollee_email ] = $pv;
            $verified = true;
          }
        }
      }

      if(!$verified) {
        // We can consider this address verified if there was a transition _to_ a Step
        // with an Enrollee actor no later than the current Step. In order to allow this
        // we need to confirm that the Petitioner is not also the Enrollee. We can't rely
        // on the Enrollment Flow Petitioner Authorization because for any possible setting
        // the Enrollee could also be the Petitioner (eg: Additional Role Enrollment,
        // Account Linking, etc).

        // We can definitively compare petitioner_identifier with enrollee_identifier
        // (if both are set) or petitioner_person_id and enrollee_person_id (if both
        // are set), or if neither petitioner value is set (unauthenticated enrollments
        // are presumed to be self signups).
        
        // We default to the initial Actor being Enrollee to require an Actor flip
        // if we can't otherwise determine that the Petitioner is the Enrollee.
        $lastActor = EnrollmentActorEnum::Enrollee;

        // We basically look to see if we can confirm the Petitioner is _not_ the
        // Enrollee, and if we can then we flip $lastActor to Petitioner.

        if(!empty($petition->petitioner_person_id)) {
          // This Petitioner is an authenticated, registered Person, and is not
          // the Enrollee.

          if(empty($petition->enrollee_person_id)
             || $petition->petitioner_person_id != $petition->enrollee_person_id) {
            $lastActor = EnrollmentActorEnum::Petitioner;
          }

          // There could potentially be other scenarios, but currently the above is
          // the only one we can confirm.
        }

        $petitionerIsEnrollee = false;

        foreach($steps as $step) {
          if($step->status == SuspendableStatusEnum::Active) {
            if($lastActor != EnrollmentActorEnum::Enrollee
               && $step->actor_type == EnrollmentActorEnum::Enrollee) {
              $this->llog('debug', "Flagging " . $petition->enrollee_email . " as verified via Handoff");

              $ret[ $petition->enrollee_email ] = $PetitionVerifications->verifyFromHandoff(
                $petition->id,
                $emailVerifier->enrollment_flow_step_id,
                $petition->enrollee_email
              );

              $verified = true;
              break;

              if($step->id == $emailVerifier->enrollment_flow_step_id) {
                // Don't check future Steps
                break;
              }
            }

            $lastActor = $step->actor_type;
          }
        }
      }

      if(!$verified) {
        $ret[ $petition->enrollee_email ] = false;
      }
    }

    // Query the plugins for steps that haven't run yet

    foreach($steps as $step) {
      if($step->id == $emailVerifier->enrollment_flow_step_id) {
        // Don't check future Steps
        break;
      }

      $PluginTable = TableRegistry::getTableLocator()->get($step->plugin);

      if(method_exists($PluginTable, "verifiableEmailAddresses")) {
        $pmodel = StringUtilities::pluginToEntityField($step->plugin);

        $paddrs = $PluginTable->verifiableEmailAddresses($step->$pmodel, $petition->id);

        if(!empty($paddrs)) {
          foreach($paddrs as $paddr => $vstatus) {
            if($vstatus) {
              // The plugin asserts the address is verified, and is responsible for registering
              // any Verifications
              $ret[ $paddr ] = true;
            } elseif(!array_key_exists($paddr, $ret)) {
              // Do we have a verification for this address?
              // This is basically copy/paste from above
              $verified = false;

              // See if we already have a verification for this address
              $matchedVerifications = $verifications->match(['mail' => $paddr]);

              if($matchedVerifications->count() > 0) {
                // The matched verifications might be pending, we'll have to actually look to
                // see if there is a completed Verification.

                foreach($matchedVerifications as $pv) {
                  if(($pv->verification->isVerified())) {
                    // This address was already verified
                    $ret[ $paddr] = $pv;
                    $verified = true;
                  }
                }
              }

              if(!$verified) {
                $ret[ $paddr ] = false;
              }
            }
          }
        }
      }
    }

    return $ret;
  }

  /**
   * Perform steps necessary to hydrate the Person record as part of Petition finalization.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int      $id           Invitation Accepter ID
   * @param  Petition $petition     Petition
   * @return bool                   true on success
   */

  public function hydrate(int $id, \App\Model\Entity\Petition $petition) {
    $cfg = $this->get($id);

    // At this point, the Steps that told us there are email addresses to verify
    // have run, so any addresses we verified should be available for us to work
    // with. The enrollee_email (if used) may or may not actually be an address
    // on the operational records, it's possible we verified it but it won't be used.

    $PetitionVerifications = TableRegistry::getTableLocator()->get('CoreEnroller.PetitionVerifications');

    // First, pull the set of email addresses we verified. If there aren't any,
    // there isn't anything else to do.

    $pVerifications = $PetitionVerifications->find()
                                           ->where(['PetitionVerifications.petition_id' => $petition->id])
                                           ->contain(['Verifications'])
                                           ->all();

    if($pVerifications->count() == 0) {
      return true;
    }

    // Next, pull the set of (unverified) EmailAddresses associated with the Enrollee.

    $EmailAddresses = TableRegistry::getTableLocator()->get('EmailAddresses');

    $allAddresses = $EmailAddresses->find()
                                   ->where([
                                    'EmailAddresses.person_id' => $petition->enrollee_person_id,
                                    'EmailAddresses.verified IS NOT true'
                                   ])
                                   ->all();
    
    if($allAddresses->count() == 0) {
      // Nothing to do
      $this->llog('debug', 'No unverified Email Addresses for Person ' . $petition->enrollee_person_id . ' (petition ' . $petition->id . ')');
      return true;
    }

    // For each verified address, find the associated EmailAddress (which may
    // not exist for the enrollee_email) and flag it as verified. Then, flip the
    // associated Verification so that it is foreign keyed to the EmailAddress
    // instead of the Petition.

    foreach($pVerifications as $pv) {
      // Only proceed if this verification was completed successfully
      if(!empty($pv->verification) && $pv->verification->isVerified()) {
        $addresses = $allAddresses->match(['mail' => $pv->mail]);

        if($addresses->count() > 0) {
          // We could have more than one matching address, although it is somewhat unlikely.
          // We skip already verified addresses, but otherwise we'll verify the first address
          // we see. (Verifications can only foreign key to a single Email Address, so we
          // won't verify more than one address.)

          // As per AR-EmailAddress-4, frozen addresses may be verified (though we're unlikely
          // to have any here). We'll also verify Person addresses that have a source address
          // (ie: that came from an EIS via a Pipeline).

          foreach($addresses as $addr) {

            if(!$addr->verified) {
              $this->llog('debug', 'Marking Email Addresses ' . $addr->id . ' as verified (petition ' . $petition->id . ')');

              // We want to update the Verification so it is linked to the Email Address,
              // but we can't change the primary link on an entity (per AR-GMR-3)
              // so we can't add a link to the EmailAddress to the existing Verification.
              // We'll need to create a new Verification.

              $EmailAddresses->Verifications->verifyFromPetition($pv->verification->id, $addr->id);

              $addr->verified = true;
              $EmailAddresses->saveOrFail($addr);

              break;
            }
          }
        }
      }
    }

    return true;
  }

  /**
   * Perform tasks prior to transitioning to this step.
   *
   * @since  COmanage Registry v5.1.0
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

    $petition->status = PetitionStatusEnum::PendingVerification;

    $Petitions->saveOrFail($petition);

    return true;
  }

  /**
   * Send an email verification request.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  EmailVerifier  $emailVerifier    Email Verifier configuration entity
   * @param  Petition       $petition         Petition entity
   * @param  string         $mail             Email Address to verify
   */

  public function sendVerificationRequest(
    EmailVerifier $emailVerifier, 
    Petition      $petition,
    string        $mail,
    bool          $resend = false,
  ): bool
  {
    // First check if there is already an existing Petition Verification.
    // If so, use that to get the existing Verification.

    $PetitionVerifications = TableRegistry::getTableLocator()->get('CoreEnroller.PetitionVerifications');
    $Verifications = TableRegistry::getTableLocator()->get('Verifications');

    $pVerification = $PetitionVerifications->find()
                                           ->where([
                                            'PetitionVerifications.petition_id' => $petition->id,
                                            'PetitionVerifications.mail'        => $mail
                                           ])
                                           ->first();

    [$charset, $regex] = $this->calculateVerificationCodeCharsetMode(
      $emailVerifier->verification_code_charset,
      $emailVerifier->verification_code_regex
    );

    if (empty($pVerification)) {
      // Request Verification and create an associated Petition Verification

      $this->llog('debug', "Sending verification code to $mail for Petition " . $petition->id);

      // I need to figure out the allowed characters for the code.

      $verificationId = $Verifications->requestCodeForPetition(
        petitionId: $petition->id,
        mail: $mail,
        messageTemplateId: $emailVerifier->message_template_id,
        validity:  $emailVerifier->request_validity,
        codeLength: !empty($emailVerifier->verification_code_length) ? $emailVerifier->verification_code_length : VerificationDefaultsEnum::DefaultCodeLength,
        codeCharset: $charset,
        codeRegex: $regex,
      );

      $pVerification = $PetitionVerifications->saveOrFail(
        $PetitionVerifications->newEntity([
          'petition_id'     => $petition->id,
          'mail'            => $mail,
          'verification_id' => $verificationId
        ]));
      return true;
    }

    if ($resend) {
      // Request a new code

      $this->llog('debug', "Sending replacement verification code to $mail for Petition " . $petition->id);

      $verificationId = $Verifications->requestCodeForPetition(
        petitionId: $petition->id,
        mail: $mail,
        messageTemplateId: $emailVerifier->message_template_id,
        validity: $emailVerifier->request_validity,
        codeLength: !empty($emailVerifier->verification_code_length) ? $emailVerifier->verification_code_length : VerificationDefaultsEnum::DefaultCodeLength,
        codeCharset: $charset,
        codeRegex: $regex,
        verificationId: $pVerification->verification_id,
      );
      // There's nothing to update in the Petition Verification

      return true;
    }

    return false;
  }


  /**
   * Calculate verification code charset based on provided charset or permitted characters.
   * Returns default charset if neither is provided.
   *
   * @param  ?string $charset Custom charset for verification code
   * @param  ?string $permitted Permitted characters enum value
   * @return array Resolved charset to use for verification code
   * @since  COmanage Registry v5.1.0
   */
  protected function calculateVerificationCodeCharsetMode(
    ?string $charset,
    ?string $permitted
  ): array {
    if (empty($charset) && empty($permitted)) {
      return [VerificationDefaultsEnum::DefaultCharset, null];
    }
    if (!empty($permitted)) {
      return [null, PermittedCharactersEnum::getPermittedCharacters(enum: $permitted)];
    }

    return [$charset, null];
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

    $validator->add('enrollment_flow_step_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('enrollment_flow_step_id');

    $validator->add('mode', [
      'content' => ['rule' => ['inList', AllTernaryEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('mode');

    $validator->add('message_template_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('message_template_id');

    $validator->add('request_validity', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('request_validity');

    $validator
      ->add('verification_code_charset', [
        'content' => [
          'rule' => 'alphaNumeric',
          'last' => true,
          'message' => __d('core_enroller', 'error.EmailVerifiers.verification_code_charset.content'),
        ],
        'is_upper_case' => [
          'rule' => fn($value, $context) => $value === strtoupper($value),
          'message' => __d('core_enroller', 'error.EmailVerifiers.verification_code_charset.is_upper_case'),
          'last' => true,
        ],
      ]);
    $validator->allowEmptyString('verification_code_charset');

    $validator->add('verification_code_regex', [
      'content' => ['rule' => ['inList', PermittedCharactersEnum::getConstValues()]]
    ]);
    $validator->allowEmptyString('verification_code_regex');

    $validator
      ->add('verification_code_length', 'content', [
        'rule' => 'isInteger',
        'last' => true,
        'message' => __d('core_enroller', 'error.EmailVerifiers.code_length.content'),
      ])
      ->add('verification_code_length', 'comparison_max', [
        'rule' => ['comparison', '>=', 1],
        'last' => true,
        'message' => __d('core_enroller', 'error.EmailVerifiers.code_length.comparison_max'),
      ])
      ->add('verification_code_length', 'comparison_less', [
        'rule' => ['comparison', '<=', 20],
        'last' => true,
        'message' => __d('core_enroller', 'error.EmailVerifiers.code_length.comparison_less'),
      ])
      ->add('verification_code_length', 'step_four', [
        'rule' => ['validateIncreaseStep', 4],
        'provider' => 'table',
        'last' => true,
        'message' => __d('core_enroller', 'error.EmailVerifiers.code_length.step_four'),
      ]);
    $validator->allowEmptyString('verification_code_length');

    $validator->add('enable_blockonfailure', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('enable_blockonfailure');
    
    return $validator;
  }
}
