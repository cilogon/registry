<?php
/**
 * COmanage Registry API Users Table
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

use ArrayObject;
use Cake\Auth\FallbackPasswordHasher;
use Cake\Chronos\Chronos;
use Cake\Event\EventInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Random\RandomString;

class ApiUsersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\SearchFilterTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Timestamp');
    $this->addBehavior('Timezone');
    
    // ApiUsers are configuration
    $this->setIsConfigurationTable(true);
    
    // Define associations
    $this->belongsTo('Cos');
    
    $this->setDisplayField('username');
    
    $this->setPrimaryLink('co_id');
    $this->setAllowLookupPrimaryLink(['generate', 'generateApiKey']);
    $this->setRequiresCO(true);
    
    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
        'class' => 'SuspendableStatusEnum'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'generate' => ['platformAdmin', 'coAdmin'],
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
   * Add namespace prefix to username
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface  $event   beforeMarshal event
   * @param  ArrayObject     $data    Entity data
   * @param  ArrayObject     $options Callback options
   */

  public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options)
  {
    if (isset($data['username'])) {
      $data['username'] = "co_" . $data['co_id'] . "." . $data['username'];
    }
  }
  
  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // We don't want to perform the uniqueness check until after then namespacing
    // check in order to avoid information leakage. This requires more complicated
    // rule building.
    
    $rules->add(function($entity, $options) use($rules) {
      
      // AR-ApiUser-3 API usernames must be unique across the entire platform.
      $rule = $rules->isUnique(['username'], __d('error', 'exists', [__d('controller', 'ApiUsers', [1])]));
      
      return $rule($entity, $options);
    },
    'isUsernameValid',
    ['errorField' => 'username']);
    
    return $rules;
  }
  
  /**
   * Generate (and save) an API Key for the specified API User.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int $id API User ID
   * @return string  API Key
   */
  
  public function generateKey(int $id) {
    $token = RandomString::generateAppKey();
    
    // Note hashing happens in the entity (ApiUser.php)
    $apiUser = $this->get($id);
    $apiUser->api_key = $token;
    
    $this->save($apiUser);
    
    return $token;
  }
  
  /**
   * Obtain an API User's priviledged status. Note this function will not validate
   * any aspects of the record (status, valid_from, etc) -- use validateKey for that.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $username API Username
   * @return mixed            true if $username is a platform API user, an integer (the CO ID) if the user is a privileged API user within that CO, or false otherwise
   * @throws InvalidArgumentException
   */
  
// public function getUserPrivilege(string $username): mixed {
// mixed requires PHP 8
  public function getUserPrivilege(string $username) {
    $apiUser = $this->find()->where(['username' => $username])->contain('Cos')->first();
    
    if(empty($apiUser)) {
      throw new \InvalidArgumentException(__d('error', 'auth.api.unknown', [$username]));
    }
    
    if($apiUser->co->isCOmanageCO()) {
      return true;
    } elseif($apiUser->privileged) {
      return $apiUser->co_id;
    }
    
    return false;
  }
  
  /**
   * Validate an API Key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $username API Username
   * @param  string $apiKey   API Key to validate
   * @param  string $remoteIp IP Address of request
   * @return boolean          true if the API Key validates
   * @throws InvalidArgumentException
   */
  
  public function validateKey(string $username, string $apiKey, string $remoteIp) {
    // First pull the ApiUser record for $username. Note we don't know which
    // CO we're querying for, so $username requires the CO name as a prefix
    // (except for legacy usernames, which are assumed to be part of the
    // COmanage CO).
    
    // We could add where clauses to filter on status, etc, but by manually
    // examining the record we can provide better error information.
    $apiUser = $this->find()->where(['username' => $username])->first();
    
    if(empty($apiUser)) {
      throw new \InvalidArgumentException(__d('error', 'auth.api.unknown', [$username]));
    }
    
    // First validate the key. We use the FallbackPasswordHasher because API Users
    // that were created in version prior to 5.0.0 use Cake 2's SHA-1 hashing.
    // We can detect that here and rehash the password, but only when the apiuser
    // authenticates.
    
    $Hasher = new FallbackPasswordHasher([
      'hashers' => [
        'Default' => [],
        'Weak' => ['hashType' => 'sha1']
      ]
    ]);
    
    if(!$Hasher->check($apiKey, $apiUser->api_key)) {
      throw new \InvalidArgumentException(__d('error', 'auth.api.key', [$username]));
    }
    
    if($Hasher->needsRehash($apiUser->api_key)) {
      // We'll rehash passwords even if subsequent eligibility checks fail
      \Cake\Log\Log::write('debug', "Rehashing password for API User \"" . $username . "\"");
      
      $apiUser->api_key = $apiKey;
      // We disable rules checking to permit legacy usernames (those not prefixed
      // with the CO name to remain)
      $this->save($apiUser, ['checkRules' => false]);
    }
    
    // Is the ApiUser active?
    if($apiUser->status != SuspendableStatusEnum::Active) {
      throw new \InvalidArgumentException(__d('error', 'auth.api.status', [$username]));
    }
    
    // Are we within the validity window, if applicable?
    $now = Chronos::now();
    
    if($apiUser->valid_from
       && $now->lt($apiUser->valid_from)) {
      throw new \InvalidArgumentException(__d('error', 'auth.api.toosoon', [$username]));
    }
    
    if($apiUser->valid_through
       && $now->gt($apiUser->valid_through)) {
      throw new \InvalidArgumentException(__d('error', 'auth.api.expired', [$username]));
    }
    
    // Perform the IP Address check
    if($apiUser->remote_ip
       && !preg_match($apiUser->remote_ip, $remoteIp)) {
      throw new \InvalidArgumentException(__d('error', 'auth.api.ip', [$remoteIp, $username]));
    }
    
    return true;
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return $validator           Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');
    
    $this->registerStringValidation($validator, $schema, 'username', true, 'co_id');
    
    $validator->add('api_key', [
      'length' => ['rule'     => ['validateMaxLength', ['column' => $schema->getColumn('api_key')]],
                   'provider' => 'table'],
    ]);
    $validator->allowEmptyString('api_key');
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    
    $validator->add('privileged', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('privileged');
    
    $validator->add('valid_from', [
      'content' => ['rule' => ['datetime']]
    ]);
    $validator->allowEmptyString('valid_from');
    
    $validator->add('valid_through', [
      'content' => ['rule' => ['datetime']]
    ]);
    $validator->allowEmptyString('valid_through');
    
    $validator->add('remote_ip', [
      'length' => ['rule'     => ['validateMaxLength', ['column' => $schema->getColumn('remote_ip')]],
                   'provider' => 'table'],
    ]);
    $validator->allowEmptyString('remote_ip');
    
    return $validator; 
  }
}