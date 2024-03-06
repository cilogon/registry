<?php
/**
 * COmanage Registry SQL Assigners Table
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
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreAssigner\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Validation\Validator;

class SqlAssignersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
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
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('IdentifierAssignments');
    $this->belongsTo('Servers');
    $this->belongsTo('Types');
    
    $this->setDisplayField('source_table');

    $this->setPrimaryLink('identifier_assignment_id');
    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'servers' => [
        'type' => 'select',
        'model' => 'Servers',
        'where' => ['plugin' => 'CoreServer.SqlServers']
      ],
      'types' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
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
        'add' =>      false, // This is added by the parent model
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Assign an identifier.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  IdentifierAssignment $ia     Identifier Assignment describing the requested configuration
   * @param  object               $entity The entity (Person, Group, Department) to assign an Identifier for
   * @return string                       The newly proposed Identifier
   * @throws InvalidArgumentException
   * @throws RuntimeException
   */

  public function assign($ia, $entity): string {
    // Find the key identifier type in the $entity data
    $keyIdentifier = Hash::extract($entity->identifiers, '{n}[type_id='.$ia->sql_assigner->type_id.']');

    if(empty($keyIdentifier)) {
      throw new \InvalidArgumentException(__d('core_assigner', 'error.SqlAssigners.key.none'));
    }

    $SqlServer = TableRegistry::getTableLocator()->get('CoreServer.SqlServers');

    $SqlServer->connect($ia->sql_assigner->server_id, 'sqlassigner');

    $options = [
      'table'       => $ia->sql_assigner->source_table,
      'alias'       => 'SourceIdentifiers',
      'connection'  => ConnectionManager::get('sqlassigner')
    ];

    $SourceTable = TableRegistry::getTableLocator()->get(
      alias: 'SourceIdentifiers',
      options: $options
    );

    $identifier = $SourceTable->find()
                              ->where(['key' => $keyIdentifier[0]->identifier])
                              ->first();

    if(!empty($identifier->identifier)) {
      return $identifier->identifier;
    }

    throw new \InvalidArgumentException(__d('core_assigner', 'error.SqlAssigners.failed'));
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

    $validator->add('identifier_assignment_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('identifier_assignment_id');

    $validator->add('server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('server_id');

    $this->registerStringValidation($validator, $schema, 'source_table', true);

    $validator->add('type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('type_id');

    return $validator;
  }
}
