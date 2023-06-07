<?php
/**
 * COmanage Registry SQL Servers Table
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

namespace CoreServer\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

// Even though CoreServer is a plugin, it should always be enabled
use CoreServer\Lib\Enum\RdbmsTypeEnum;

class SqlServersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;

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
    $this->belongsTo('Servers');

    $this->setDisplayField('hostname');

    $this->setPrimaryLink('server_id');
    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'types' => [
        'type' => 'enum',
        'class' => 'CoreServer.RdbmsTypeEnum'
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
   * Establish a connection (via Cake's ConnectionManager) to the specified SQL server.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $serverId Server ID (NOT SqlServer ID)
   * @param  string $name     Connection name, used for subsequent access via Models
   * @return bool   true on success
   * @throws Exception
   */
  
  public function connect(int $serverId, string $name): bool {
    // Note if you're looking to add support for tablePrefix here (eg: "cm_")
    // Cake basically dropped support for that in v3. As an alternate,
    // individual models can be configured to use alternate table names,
    // which is basically what the SQL Provisioner does.

    // Pull our configuration via the parent Server object.
    $server = $this->Servers->get($serverId, ['contain' => ['SqlServers']]);

    $dbmap = [
      RdbmsTypeEnum::MariaDB    => 'Mysql',
      RdbmsTypeEnum::MySQL      => 'Mysql',
      RdbmsTypeEnum::Postgres   => 'Postgres',
      RdbmsTypeEnum::SQLite     => 'Sqlite',
      RdbmsTypeEnum::SqlServer  => 'Sqlserver'
    ];

    $dbconfig = [
      'className'         => 'Cake\Database\Connection',
      'driver'            => "Cake\Database\Driver\\" . $dbmap[$server->sql_server->type],
      'persistent'        => false,
      'host'              => $server->sql_server->hostname,
      'username'          => $server->sql_server->username,
      'password'          => $server->sql_server->password,
      'database'          => $server->sql_server->databas,
      'quoteIdentifiers'  => false,
      'encoding'          => 'utf8',
      'timezone'          => 'UTC'
    ];

    ConnectionManager::setConfig('targetdb', $dbconfig);

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

    $validator->add('server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('server_id');

    $validator->add('type', [
      'content' => ['rule' => ['inList', RdbmsTypeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('type');

    $this->registerStringValidation($validator, $schema, 'hostname', true);

    $this->registerStringValidation($validator, $schema, 'databas', true);

    $this->registerStringValidation($validator, $schema, 'username', false);

    $this->registerStringValidation($validator, $schema, 'password', false);
    
    return $validator;
  }
}
