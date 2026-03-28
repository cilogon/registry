<?php
/**
 * COmanage Registry Authenticators Table
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
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use App\Lib\Enum\ActionEnum;
use App\Lib\Enum\ProvisioningContextEnum;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Util\StringUtilities;

class AuthenticatorsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PluggableModelTrait;
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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('MessageTemplates');

    $this->hasMany('AuthenticatorStatuses')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setPluginRelations();
    
    $this->setDisplayField('description');
    
    // $this->setPrimaryLink('co_id');
    $this->setPrimaryLink(['co_id', 'authenticator_id']);
    $this->setRequiresCO(true);
    $this->setAllowUnkeyedPrimaryLink(['lock', 'manage', 'reset', 'unlock']);

    $this->setAutoViewVars([
      'messageTemplates' => [
        'type'  => 'select',
        'model' => 'MessageTemplates',
        'where' => ['context' => \App\Lib\Enum\MessageTemplateContextEnum::Authenticator]
      ],
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'authenticator'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SuspendableStatusEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'configure' =>  ['platformAdmin', 'coAdmin'],
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      // Note because of how parameters are passed to Authenticators many actions here
      // are "table" rather than "entity" actions
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
        'lock' =>     ['platformAdmin', 'coAdmin'],
        // 'manage' will just issue a redirect based on the query params, but we still
        // require authz since there's no reason to leave it fully open
        'manage' =>   ['platformAdmin', 'coAdmin'],
        'reset' =>    ['platformAdmin', 'coAdmin'],
        // 'status' =>   ['platformAdmin', 'coAdmin']
        'unlock' =>   ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Determine the fully qualified Authenticator Entity Name based on the provided
   * fully qualified Plugin name.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string $pluginName Plugin Name, eg PasswordAuthenticator.PasswordAuthenticators
   * @return string             Authenticator Entity Name, eg PasswordAuthenticator.Password
   */

  public function authenticatorEntityName(string $pluginName) {
    // $plugin is something like PasswordAuthenticator.PasswordAuthenticators,
    // the actual Authenticator entity is something like PasswordAuthenticator.Password

    return substr($pluginName, 0, strlen($pluginName)-14);
  }
  
  /**
   * Obtain the Authenticator Table for the provided fully qualified Plugin.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string $pluginName Plugin Name, eg PasswordAuthenticator.PasswordAuthenticators
   * @return Table              Authenticator Table, eg PasswordAuthenticator.Passwords
   */

  public function authenticatorTable(string $pluginName) {
    // We need to pluralize the entity name to get back to the table

    $tableName = Inflector::pluralize($this->authenticatorEntityName($pluginName));

    return TableRegistry::getTableLocator()->get($tableName);
  }

  /**
   * Lock an Authenticator.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $id       Authenticator ID
   * @param  int    $personId Person ID
   * @throws InvalidArgumentException
   */

  public function lock(int $id, int $personId) {
    $this->processLock($id, $personId, 'lock');
  }

  /**
   * Process a status change of a lock.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $id       Authenticator ID
   * @param  int    $personId Person ID
   * @param  string $action   "lock" or "unlock"
   * @throws InvalidArgumentException
   */

  protected function processLock(int $id, int $personId, string $action) {
    $cfg = $this->get($id, contain: $this->getPluginRelations());

    // Make sure our configuration is active
    if($cfg->status != SuspendableStatusEnum::Active) {
      throw new \InvalidArgumentException(__d('error', 'inactive', [__d('controller', 'Authenticators', [1], $cfg->id)]));
    }

    // See if there is a current status that is incompatible with $action
    $curStatus = $this->AuthenticatorStatuses->find()->where([
      'authenticator_id' => $id,
      'person_id' => $personId
    ])->first();

    if($action == 'lock' && !empty($curStatus) && $curStatus->locked) {
      throw new \InvalidArgumentException(__d('error', 'Authenticators.status.locked'));
    } elseif($action == 'unlock' && (empty($curStatus) || !$curStatus->locked)) {
      throw new \InvalidArgumentException(__d('error', 'Authenticators.status.unlocked'));
    }

    // Give the backend a chance to do something
    $PluginTable = TableRegistry::getTableLocator()->get($cfg->plugin);

    if(method_exists($PluginTable, $action)) {
      $PluginTable->$action($cfg, $personId);
    }

    // Upsert
    if($curStatus) {
      $curStatus->locked = ($action == 'lock');
    } else {
      $curStatus = $this->AuthenticatorStatuses->newEntity([
        'authenticator_id' => $id,
        'person_id' => $personId,
        'locked' => ($action == 'lock')
      ]);
    }

    $this->AuthenticatorStatuses->saveOrFail($curStatus);

    // Record History and Provision

    $People = TableRegistry::getTableLocator()->get('People');

    $People->recordHistory(
      $curStatus,
      ActionEnum::AuthenticatorLocked,
      __d('result', 'Authenticators.'.$action.'ed-a', [$cfg->description])
    );

    $People->requestProvisioning($personId, ProvisioningContextEnum::Automatic);
  }

  /**
   * Reset an Authenticator.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $id       Authenticator ID
   * @param  int    $personId Person ID
   * @throws InvalidArgumentException
   */

  public function reset(int $id, int $personId) {
    $cfg = $this->get($id, contain: $this->getPluginRelations());

    // Make sure our configuration is active
    if($cfg->status != SuspendableStatusEnum::Active) {
      throw new \InvalidArgumentException(__d('error', 'inactive', [__d('controller', 'Authenticators', [1], $cfg->id)]));
    }

    // Per AR-Authenticator-2 a locked Authenticator may be reset, so we don't need to
    // check for that here.

    // The actual reset logic is backend specific

    $PluginTable = $this->authenticatorTable($cfg->plugin);

    if(method_exists($PluginTable, 'reset')) {
      $PluginTable->reset($cfg, $personId);
    }

    // Record History and Provision

    $People = TableRegistry::getTableLocator()->get('People');

    // We need an entity to pass to recordHistory, so we create a new Person entity
    // and pass it around

    $person = $People->get($personId);

    $People->recordHistory(
      $person,
      ActionEnum::AuthenticatorReset,
      __d('result', 'Authenticators.reset-a', [$cfg->description])
    );

    $People->requestProvisioning($personId, ProvisioningContextEnum::Automatic);
  }

  /**
   * Unlock an Authenticator.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $id       Authenticator ID
   * @param  int    $personId Person ID
   * @throws InvalidArgumentException
   */

  public function unlock(int $id, int $personId) {
    $this->processLock($id, $personId, 'unlock');
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
    
    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');
    
    $this->registerStringValidation($validator, $schema, 'description', true);
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'plugin', true);
    
    $validator->add('enable_ptp', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('enable_ptp');
    
    $validator->add('message_template_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('message_template_id');

    return $validator; 
  }
}