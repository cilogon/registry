<?php
/**
 * COmanage Registry Password Authenticators Table
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
use Cake\Validation\Validator;
use PasswordAuthenticator\Lib\Enum\PasswordEncodingEnum;
use PasswordAuthenticator\Lib\Enum\PasswordSourceEnum;
use App\Lib\Enum\SuspendableStatusEnum;

class PasswordAuthenticatorsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  // Do we support multiple Authenticators attached to this configuration?
  public $multiple = false;

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

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('Authenticators');

    $this->hasMany('PasswordAuthenticator.Passwords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setDisplayField('id');

    $this->setPrimaryLink('authenticator_id');
    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'sourceModes' => [
        'type' => 'enum',
        'class' => 'PasswordAuthenticator.PasswordSourceEnum'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Callback before data is marshaled into an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface  $event   beforeMarshal event
   * @param  ArrayObject     $data    Entity data
   * @param  ArrayObject     $options Callback options
   */

  public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options) {
    // PAR-PasswordAuthenticator-1 When the Password Source is Self Select, the password
    // must be stored in PHP Crypt format

    if(!empty($data['source_mode']) && $data['source_mode'] == PasswordSourceEnum::SelfSelect) {
      $data['format_crypt_php'] = true;
    }
  }

  /**
   * Assemble Authenticator data for provisioning.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Authenticator  $cfg      Authenticator Configuration
   * @param  int            $personId Person ID
   * @return array                    Array of Password entities
   */

  public function marshalProvisioningData(
    \App\Model\Entity\Authenticator $cfg,
    int $personId
  ): array {
    // Retrieve any Passwords associated with this Person and the requested configuration.
    // We'll include all available Password types (encodings) since we don't know which types
    // any specific Provisioner will be interested in.

    $passwords = $this->Passwords->find()
                                 ->where([
                                  'Passwords.person_id' => $personId,
                                  'Passwords.password_authenticator_id' => $cfg->password_authenticator->id
                                 ])
                                 ->all();
    
    return $passwords->toArray();
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

    $validator->add('authenticator_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('authenticator_id');

    $validator->add('source_mode', [
      'content' => ['rule' => ['inList', PasswordSourceEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('source_mode');

// XXX min_length and max_length required depend on source_mode
    $validator->add('min_length', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->add('min_length', [
      'contentgt' => ['rule' => ['comparison', '>', 7]]
    ]);
    $validator->add('min_length', [
      'contentlt' => ['rule' => ['comparison', '<', 65]]
    ]);
    $validator->allowEmptyString('min_length');

    $validator->add('max_length', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->add('max_length', [
      'contentgt' => ['rule' => ['comparison', '>', 7]]
    ]);
    $validator->add('max_length', [
      'contentlt' => ['rule' => ['comparison', '<', 65]]
    ]);
    $validator->allowEmptyString('max_length');

    $validator->add('format_crypt_php', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('format_crypt_php');

    $validator->add('format_plaintext', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('format_plaintext');

    $validator->add('format_sha1_ldap', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('format_sha1_ldap');

    return $validator;
  }
}
