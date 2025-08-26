<?php
/**
 * COmanage Registry Passwords Table
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

namespace PasswordAuthenticator\Model\Table;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\ActionEnum;
use App\Model\Entity\Authenticator;
use App\Lib\Enum\AuthenticatorStatusEnum;
use PasswordAuthenticator\Lib\Enum\PasswordEncodingEnum;
use PasswordAuthenticator\Lib\Enum\PasswordSourceEnum;

class PasswordsTable extends Table {
  use \App\Lib\Traits\AuthenticatorTrait;
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
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
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);

    // Define associations
    $this->belongsTo('PasswordAuthenticator.PasswordAuthenticators');
    $this->belongsTo('People');
    
    $this->setDisplayField('id');

    $this->setPrimaryLink('PasswordAuthenticator.password_authenticator_id');
    $this->setRequiresCO(true);
    $this->setAllowUnkeyedPrimaryLink(['manage']);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     false, // use manage instead ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false, //['platformAdmin', 'coAdmin'],
        'manage' =>   ['platformAdmin', 'coAdmin'],
        'index' =>    false  // ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Handle an Authenticator update from a manage() request.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Authenticator  $cfg      Authenticator configuration
   * @param  int            $personId Person ID
   * @param  array          $data     Array of data from fields.inc
   */

  public function manage(
    Authenticator $cfg,
    int           $personId,
    array         $data
  ): void {
    $minlen = $cfg->password_authenticator->min_length ?: 8;
    $maxlen = $cfg->password_authenticator->max_length ?: 64;

    // Perform sanity checks on Self Selected passwords only
    if($cfg->password_authenticator->source_mode == PasswordSourceEnum::SelfSelect) {
      // Check minimum length
      if(strlen($data['password']) < $minlen) {
        throw new \InvalidArgumentException(__d('password_authenticator', 'error.Passwords.len.min', [$minlen]));
      }

      // Check maximum length
      if(strlen($data['password']) > $maxlen) {
        throw new \InvalidArgumentException(__d('password_authenticator', 'error.Passwords.len.max', [$minlen]));
      }

      // Check that passwords match
      if($data['password'] != $data['password2']) {
        throw new \InvalidArgumentException(__d('password_authenticator', 'error.Passwords.match'));
      }
    }

// XXX Note we're not checking the current password yet because we don't support self
// service yet. It might make sense to implement that check in the code that receives
// the self service request (presumably a dashboard widget).

    $cxn = $this->getConnection();
    $cxn->begin();

    try {
      // Delete any existing password for the user. We do it this way in case the
      // plugin configuration is changed.

      $passwords = $this->find()->where([
        'password_authenticator_id' => $cfg->password_authenticator->id,
        'person_id' => $personId
      ])
      ->all();

      foreach($passwords as $password) {
        $this->delete($password);
      }

      // We'll store one entry per hashing type. We always store CRYPT
      // so we can use the native php routines (which require PHP 5.5+).
      // Enabling SSHA requires PHP 7 for random_bytes.

      // We could use something like https://multiformats.io/multihash, but the
      // password_type column basically accomplishes the same thing.

      $pdata = null;

      if(true || $cfg->password_authenticator->format_crypt_php) {
        // We use password_hash, which due to various portability issues with crypt
        // is really only useful with password_verify.

        $pdata = $this->newEntity([
          'password_authenticator_id' => $data['password_authenticator_id'],
          'person_id'                 => $personId,
          'password'                  => password_hash($data['password'], PASSWORD_DEFAULT),
          'password_type'             => PasswordEncodingEnum::Crypt
        ]);
        
        $this->saveOrFail($pdata);
      }

      if($cfg->password_authenticator->format_sha1_ldap) {
        // Salted SHA1 isn't really a great algorithm (and our salt generation
        // could probably be better), but OpenLDAP doesn't support a better option
        // out of the box.

        $salt = substr(bin2hex(random_bytes(8)),0,4);
        $shapwd = base64_encode(sha1($data['password'].$salt, true) . $salt);

        $pdata = $this->newEntity([
          'password_authenticator_id' => $data['password_authenticator_id'],
          'person_id'                 => $personId,
          'password'                  => $shapwd,
          'password_type'             => PasswordEncodingEnum::SSHA
        ]);
        
        $this->saveOrFail($pdata);
      }

      if($cfg->password_authenticator->format_plaintext) {
        // Other than being easily readable by admins, plaintext is arguably not
        // that much less secure than the other supported options...

        $pdata = $this->newEntity([
          'password_authenticator_id' => $data['password_authenticator_id'],
          'person_id'                 => $personId,
          'password'                  => $data['password'],
          'password_type'             => PasswordEncodingEnum::Plain
        ]);
        
        $this->saveOrFail($pdata);
      }

      // At this point we've deleted any existing Passwords and correctly stored all
      // configured variations, so we can commit the transaction.
      $cxn->commit();

      // Record history
      $comment = __d('password_authenticator', 'result.Passwords.set', [$cfg->description]);

      $this->People->recordHistory(
        // It doesn't matter which version of $pdata we use
        $pdata,
        ActionEnum::AuthenticatorEdited,
        $comment
      );
    }
    catch(\Exception $e) {
      $cxn->rollback();

      throw $e;
    }
  }

  /**
   * Reset a Password for a Person,
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Authenticator  $cfg      Authenticator Configuration
   * @param  int            $personId Person ID
   */

  public function reset(
    Authenticator $cfg,
    int $personId
  ): void{
    // We simply delete all Passwords for $personId, if any

    $cxn = $this->getConnection();
    $cxn->begin();

    try {
      $passwords = $this->find()->where([
        'password_authenticator_id' => $cfg->password_authenticator->id,
        'person_id' => $personId
      ])
      ->all();

      foreach($passwords as $password) {
        $this->delete($password, ['reset' => true]);
      }

      $cxn->commit();

      // We don't need to record history or provision because the infrastructure will handle that
    }
    catch(\Exception $e) {
      $cxn->rollback();

      throw $e;
    }
  }

  /**
   * Obtain the current Authenticator status for a Person.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Authenticator  $cfg      Authenticator Configuration
   * @param  int            $personId Person ID
   * @return array                    Array with values
   *                                  status: AuthenticatorStatusEnum
   *                                  comment: Human readable string, visible to the CO Person
   */

  public function status(Authenticator $cfg, int $personId): array {
    // Is there a password for this person?

    $pwd = $this->find()
                ->where([
                  'password_authenticator_id' => $cfg->password_authenticator->id,
                  'person_id' => $personId
                ])
                ->first();

    // We don't know which password_type we have, but they should all have the
    // same mod time

    if(!empty($pwd->modified)) {
      return [
        'status' => AuthenticatorStatusEnum::Active,
        // Note we don't currently have access to local timezone setting
// XXX is this still true?
        'comment' => __d('password_authenticator', 'result.Passwords.modified', [$pwd->modified])
      ];
    }

    return [
      'status'  => AuthenticatorStatusEnum::NotSet,
      'comment' => __d('result', 'set.not')
    ];
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

    $validator->add('password_authenticator_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('password_authenticator_id');

    $validator->add('person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('person_id');

    $this->registerStringValidation($validator, $schema, 'password', true);

    $this->registerStringValidation($validator, $schema, 'password2', true);

    $validator->add('type', [
      'content' => ['rule' => ['inList', PasswordEncodingEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('type');

    return $validator;
  }
}
