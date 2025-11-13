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

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use CoreServer\Lib\Enum\RdbmsTypeEnum;
use \App\Lib\Enum\SuspendableStatusEnum;

class SqlServersTable extends Table {
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
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */

  public function buildRules(RulesChecker $rules): RulesChecker {
    // This is not an Application Rule per se, but the Oracle plugin must
    // be enabled if the Server Type is set to Oracle.
    $rules->add([$this, 'ruleOracleEnabled'],
                'oracleEnabled',
                ['errorField' => 'type']);

    return $rules;
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
    $server = $this->Servers->get($serverId, contain: ['SqlServers']);

    if($server->status != SuspendableStatusEnum::Active) {
      throw new \InvalidArgumentException(__d('error', 'inactive', [__d('controller', 'Servers', [1]), $serverId]));
    }
    
    $dbmap = [
      RdbmsTypeEnum::MariaDB    => 'Mysql',
      RdbmsTypeEnum::MySQL      => 'Mysql',
      RdbmsTypeEnum::Oracle     => 'Oracle',
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

    if(!empty($server->sql_server->port) && is_numeric($server->sql_server->port)) {
      $dbconfig['port'] = $server->sql_server->port;
    }
    
    if($server->sql_server->type == RdbmsTypeEnum::Oracle) {
      $oracleEnabled = \Cake\Core\Configure::read('registry.database.oracle.enable');

      if($oracleEnabled) {
        // We don't test that the plugin is available here, an error should be thrown
        // when we try to connect.

        $dbconfig['className'] = 'CakeDC\OracleDriver\Database\OracleConnection';
        $dbconfig['driver'] = 'CakeDC\OracleDriver\Database\Driver\OracleOCI'; // For OCI8
        $dbconfig['quoteIdentifiers'] = true;

        // Use 'CakeDC\\OracleDriver\\Database\\Driver\\OraclePDO' for PDO_OCI, but CakeDC
        // recommends OCI8
        // The plugin documentation says certain features are enabled at v12, and more
        // specifically we require support for long aliases (Oracle only supported 30
        // characters until 12.2, which allows 128). See eg this commit
        // https://github.com/CakeDC/cakephp-oracle-driver/pull/57/commits/1461451ce896aa55a14b08fddc0b28266a3391df
        // Oracle 19c (aka 19.1.0 aka 12.2.0.3) appears to be the current oldest release
        // (as of this writing), and based on testing from SMU setting this value to "19"
        // correctly enables the long alias support, to we hard code that version to simplify
        // configuration. Note Oracle changed their release numbers to be based on calendar years,
        // retroactively assigning 18c (12.2.0.2) and 19c (12.2.0.3), so this approach should
        // work at least for those versions. 
        $dbconfig['server_version'] = 19;
      }
    }

    // We need to drop the existing configuration before we can reconfigure it
    ConnectionManager::drop($name);

    ConnectionManager::setConfig($name, $dbconfig);

    return true;
  }

  /**
   * Application Rule to determine if Oracle is enabled (if selected).
   *
   * @since  COmanage Registyr v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return mixed            true if the Rule check passes, or an error string otherwise
   */

  public function ruleOracleEnabled($entity, $options) {
    if($entity->type == RdbmsTypeEnum::Oracle) {
      $oracleEnabled = \Cake\Core\Configure::read('registry.database.oracle.enable');

      if(!$oracleEnabled) {
        return __d('core_server', 'error.SqlServers.oracle.enabled');
      }

      $pluginLoaded = Plugin::isLoaded('OracleDriver');

      if(!$pluginLoaded) {
        return __d('core_server', 'error.SqlServers.oracle.plugin');
      }
    }

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

    $validator->add('port', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('port');
    
    $this->registerStringValidation($validator, $schema, 'databas', true);

    $this->registerStringValidation($validator, $schema, 'username', false);

    $this->registerStringValidation($validator, $schema, 'password', false);
    
    return $validator;
  }
}
