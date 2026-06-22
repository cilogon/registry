<?php
/**
 * COmanage Registry Password Collectors Table Table
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace PasswordAuthenticator\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\ActionEnum;
use App\Lib\Enum\PetitionActionEnum;
use App\Lib\Enum\RequiredEnum;
use PasswordAuthenticator\Lib\Enum\PasswordEncodingEnum;

class PasswordCollectorsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \PasswordAuthenticator\Lib\Traits\PasswordAuthenticatorTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.3.0
   * @param  array  $config Configuration options passed to constructor
   */

  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('EnrollmentFlowSteps');
    $this->belongsTo('Authenticators');

    $this->hasMany('PasswordAuthenticator.PetitionPasswords');

    $this->setDisplayField('id');

    $this->setPrimaryLink('enrollment_flow_step_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['dispatch', 'display']);

    $this->setAutoViewVars([
      'authenticators' => [
        'type' => 'select',
        'model' => 'Authenticators',
        'where' => ['Authenticators.plugin' => 'PasswordAuthenticator.PasswordAuthenticators']
      ],
      'requireds' => [
        'type'  => 'enum',
        'class' => 'RequiredEnum'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'dispatch' => true,
        'display' =>  true,
        'edit' =>     ['platformAdmin', 'coAdmin'],
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
   * @since  COmanage Registry v5.3.0
   * @param  int      $id           Password Collector ID
   * @param  Petition $petition     Petition
   * @return bool                   true on success
   */

  public function hydrate(int $id, \App\Model\Entity\Petition $petition) {
    $cfg = $this->get($id, contain: ['Authenticators' => 'PasswordAuthenticators']);

    // debug($cfg);

    if($cfg->authenticator->enable_ptp) {
      // If we're operating in PTP mode, we have nothing to do since we
      // already processed the Password.
    } else {
      // If we're not, we need to (1) copy the Password from the Petition
      // to the operational table, and (2) delete the Passwords from the
      // Petition. (Normally Petition data isn't removed on finalization,
      // but we'll make an exception here to reduce the footprint of the data.)

      $PasswordsTable = TableRegistry::getTableLocator()->get('PasswordAuthenticator.Passwords');
      $PetitionHistoryRecords = TableRegistry::getTableLocator()->get('PetitionHistoryRecords');

      $passwords = $this->PetitionPasswords->find()
                                           ->where([
                                            'password_collector_id' => $id,
                                            'petition_id' => $petition->id
                                           ])
                                           ->all();
      
      foreach($passwords as $password) {
        $pdata = $PasswordsTable->newEntity([
          'password_authenticator_id' => $cfg->authenticator->password_authenticator->id,
          'person_id'                 => $petition->enrollee_person_id,
          'password'                  => $password->password,
          'type'                      => $password->type
        ]);

        $PasswordsTable->saveOrFail($pdata);

        $this->PetitionPasswords->delete($password);

        $PasswordsTable->People->recordHistory(
          $pdata,
          ActionEnum::AuthenticatorEdited,
          __d('password_authenticator', 'result.PasswordCollector.set.ef', [$cfg->authenticator->description, $petition->id])
        );

        $PetitionHistoryRecords->record(
          petitionId:           $petition->id,
          enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
          action:               PetitionActionEnum::Finalized,
          comment:              __d('password_authenticator', 'result.PasswordCollector.set', [$cfg->authenticator->description])
        );
      }
    }

    return true;
  }

  /**
   * Stash a Petition password.
   *
   * @since  COmanage Registry v5.3.0
   * @param  PasswordCollector  $config   PasswordCollector configuration object
   * @param  Petition           $petition Petition
   * @param  array              $data     Data, as submitted by dispatch
   * @return bool                         true on success
   * @throws PersistenceFailedException
   */

  public function stash(
    \PasswordAuthenticator\Model\Entity\PasswordCollector $config,
    \App\Model\Entity\Petition                            $petition,
    array                                                 $data
  ): bool {
    // We partially replicate PasswordsTable::manage() here, specifically we
    // need to (1) perform password checks, since the Enrollee can't correct
    // anything during finalization, and (2) store the Password in the configured
    // formats.

    // Let validateRequest() do its thing and let any Exceptions bubble up.
    // Note validateRequest will attempt to prevent Password reuse if configured,
    // but that's not a concern here since we're by definition collecting a new
    // Password.
    $this->validateRequest($config->authenticator, $data);

    // Because we need to store Passwords in each configured format, rather
    // than use upsert() we simply delete any previous values and save new ones.
    // Since ChangelogBehvior is _not_ enabled on PetitionPasswords, we don't
    // need to worry about callbacks during deleteAll.

    $this->PetitionPasswords->deleteAll([
      'petition_id'           => $petition->id,
      'password_collector_id' => $config->id
    ]);

    // Store each configured type as a Petition Password.

    $pdata = null;

    if($config->authenticator->password_authenticator->format_crypt_php) {
      $pdata = $this->PetitionPasswords->newEntity([
        'password_collector_id' => $config->id,
        'petition_id'           => $petition->id,
        'password'              => $this->encode($data['password'], PasswordEncodingEnum::Crypt),
        'type'                  => PasswordEncodingEnum::Crypt
      ]);
      
      $this->PetitionPasswords->saveOrFail($pdata);
    }

    if($config->authenticator->password_authenticator->format_sha1_ldap) {
      $pdata = $this->PetitionPasswords->newEntity([
        'password_collector_id' => $config->id,
        'petition_id'           => $petition->id,
        'password'              => $this->encode($data['password'], PasswordEncodingEnum::SSHA),
        'type'                  => PasswordEncodingEnum::SSHA
      ]);
      
      $this->PetitionPasswords->saveOrFail($pdata);
    }

    if($config->authenticator->password_authenticator->format_plaintext) {
      // Other than being easily readable by admins, plaintext is arguably not
      // that much less secure than the other supported options...

      $pdata = $this->PetitionPasswords->newEntity([
        'password_collector_id' => $config->id,
        'petition_id'           => $petition->id,
        'password'              => $data['password'],
        'type'                  => PasswordEncodingEnum::Plain
      ]);
      
      $this->PetitionPasswords->saveOrFail($pdata);
    }

    $this->PetitionPasswords->Petitions->PetitionHistoryRecords->record(
      petitionId:           $petition->id,
      enrollmentFlowStepId: $config->enrollment_flow_step_id,
      action:               PetitionActionEnum::AttributesUpdated,
      comment:              __d('password_authenticator', 'result.PasswordCollector.collected')
    );

    return true;
  }

  /**
   * Set validation rules.
   *
   * @since  COmanage Registry v5.3.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */

  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('enrollment_flow_step_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('enrollment_flow_step_id');

    $validator->add('authenticator_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('authenticator_id');

    $validator->add('required', [
      'content' => ['rule' => ['inList', RequiredEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('required');

    return $validator;
  }
}
