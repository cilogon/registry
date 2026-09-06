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
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    $this->addBehavior('Changelog');
    $this->addBehavior('Clonable');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Primary);
    
    // Define associations
    $this->belongsTo('Cos');

    // In general, we don't want to propagate deletes of a Server to its
    // hasMany dependents since we want to throw an error for the administrator
    // first. (For deleting a CO, the dependent objects should be deleted first.)
    $this->hasMany('CoSettings')
         ->setForeignKey('email_smtp_server_id');
    $this->hasMany('Identifiers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Pipelines')
         ->setForeignKey('match_server_id');

    $this->bindPluginRelations();
    
    $this->setDisplayField('description');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['test']);
    // We need to calculate the redirect URL for sync ourselves (in the controller)
    $this->setRedirectGoal(goal: 'special', action: 'test');

    $this->setEditContains([
      'Identifiers'
    ]);
    $this->setViewContains([
      'Identifiers'
    ]);

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
    
    // Enable the Model Specific REST API for this Table
    $this->enableMsrApi();
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'configure' =>  ['platformAdmin', 'coAdmin'],
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin'],
        'test' =>  ['platformAdmin', 'coAdmin'],
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
   * Request provisioning.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  int                      $id                   This table's entity ID to provision
   * @param  ProvisioningContextEnum  $context              Context in which provisioning is being requested
   * @param  int                      $provisioningTargetId If set, the Provisioning Target ID to request provisioning for (otherwise all)
   * @param  Job                      $job                  If called from a Job, the current Job entity
   * @param  array                    $passThroughData      Additional data to merge into the marshalled provisioning data
   * @throws InvalidArgumentException
   */

  public function requestProvisioning(
    int     $id,
    string  $context,
    ?int    $provisioningTargetId=null,
    ?Job    $job=null,
    ?array  $passThroughData=null
  ) {
    return;
  }

  /**
   * Application Rule to determine if the server is in use.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleInUse($entity, $options) {
    // XXX CFM-281 we need to do something here

    return true;
  }

  /**
   * Perform a keyword search.
   *
   * @since  COmanage Registry v5.3.0
   * @param  int    $coId   CO ID to constrain search to
   * @param  string $q      String to search for
   * @param  int    $limit  Search limit
   * @return Array          Array of search results, as from find('all')
   */

  public function search(int $coId, string $q, int $limit) {
    // Tokenize $q on spaces
    $tokens = explode(" ", $q);

    // We take two loops through, the first time we only do a prefix search
    // (foo%). If that doesn't reach the search limit, we'll do an infix search
    // the second time around.

    $whereClause = [];

    foreach($tokens as $t) {
      $whereClause['AND'][] = [
        'OR' => [
          'LOWER(Servers.description) LIKE' => '%' . strtolower($t) . '%'
        ]
      ];
    }

    return $this->find()
                ->where($whereClause)
                ->andWhere(['Servers.co_id' => $coId])
                ->orderBy(['Servers.description'])
                ->limit($limit)
                ->all();
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