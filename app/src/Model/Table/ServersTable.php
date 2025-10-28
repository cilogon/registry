<?php
/**
 * COmanage Registry Servers Table
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

use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Util\StringUtilities;
use App\Lib\Util\TableUtilities;

class ServersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\ClonableTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PluggableModelTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Clonable');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    // XXX This is set to be a primary table. Nevertheless we access it via the configuration view
    // and when clicked we do not get the configuration breadcrumb.???
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Primary);
    
    // Define associations
    $this->belongsTo('Cos');

    // In general, we don't want to propagate deletes of a Server to its
    // hasMany dependents since we want to throw an error for the administrator
    // first. (For deleting a CO, the dependent objects should be deleted first.)
    $this->hasMany('CoSettings')
         ->setForeignKey('email_smtp_server_id');
    $this->hasMany('Pipelines')
         ->setForeignKey('match_server_id');

    $this->setPluginRelations();
    
    $this->setDisplayField('description');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'server'
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
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }
  
  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-Server-1 A Server cannot be deleted if it is referenced from
    // a Configuration object (including plugins).

    $rules->addDelete([$this, 'ruleInUse'],
                              'serverInUse',
                              ['errorField' => 'status']);
    
    // AR-GMR-6 The same UUID cannot be assigned to multiple objects within the same CO.
    $rules->add([$this, 'ruleUuidUnique'],
                'uuidUnique',
                ['errorField' => 'uuid']);
    
    return $rules;
  }

  /**
   * Application Rule to determine if the server is in use.
   *
   * @since  COmanage Registyr v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleInUse($entity, $options) {
    // XXX CFM-281 we need to do something here

    return true;
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');
    
    $this->registerStringValidation($validator, $schema, 'description', false);
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'plugin', true);

    $this->registerClonableValidation($validator, $schema);
    
    return $validator; 
  }
}