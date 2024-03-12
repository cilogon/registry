<?php
/**
 * COmanage Registry DBAL Connection
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

namespace App\Lib\Util;

use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class DBALConnection extends Connection {

  // Console for output
  protected $io = null;

  // The database driver in use
  public $driver = null;

  // PostgreSQL schema
  protected $pgSchema = null;

  /**
   * Return DBALConnection instance constructed from CakePHP connection name
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ConsoleIO  $io          Cake ConsoleIo object
   * @param  string     $connection Database connection name
   */

  public static function factory(?ConsoleIo $io=null, string $connection='default') {
    // Use the CakePHP ConnectionManager to get the database config to pass to DBAL.
    $db = ConnectionManager::get($connection);
    
    // $db is a ConnectionInterface object.
    $cfg = $db->config();
    
    // DBAL Configuration instance to pass into DBAL connection factory
    $config = new Configuration();
    
    // Translate from CakePHP param names to DBAL param names.
    $cfargs = [
      'dbname'   => $cfg['database'],
      'user'     => $cfg['username'],
      'password' => $cfg['password'],
      'host'     => $cfg['host'],
      'driver'   => ($cfg['driver'] == 'Cake\Database\Driver\Postgres' ? "pdo_pgsql" : "mysqli")
    ];
    
    // For MySQL SSL
    if(!empty($cfg['ssl_ca'])) {
      $cfargs['ssl_ca'] = $cfg['ssl_ca'];
    }

    // Signal to DBAL factory to create an instance of this class.
    $cfargs['wrapperClass'] = self::class;

    if($io) {
      $io->out("Connecting to database " . $cfg['database'] . " as " 
                                  . $cfg['username'] . "@" . $cfg['host']);
    }

    // Use the DBAL factory to open a connection but return instance
    // of this class.
    $conn = DriverManager::getConnection($cfargs, $config);

    // The CakePHP database configuration supports using a PostgreSQL schema. If
    // defined signal that we should prefix tables with the PostgreSQL schema and a dot '.'.
    if(!empty($cfg['schema'])) {
      $conn->pgSchema = $cfg['schema'];
    }

    if($io) {
      $conn->io = $io;
    }
    $conn->driver = $cfg['driver'];

    return $conn;
  }

  /**
   * Is the database MySQL or MariaDB?
   *
   * @since  COmanage Registry v5.0.0
   * @return boolean
   */
  public function isMySQL() {
    return ($this->driver == 'Cake\Database\Driver\Mysql');
  }

  /**
   * Is the database PostgreSQL
   *
   * @since  COmanage Registry v5.0.0
   * @return boolean
   */
  public function isPostgreSQL() {
    return ($this->driver == 'Cake\Database\Driver\Postgres');
  }

  /**
   * Qualify database table name.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $tableName Unqualified table name
   * @return string Qualified table name
   */

  public function qualifyTableName($tableName) {
    switch ($this->driver) {
      case 'Cake\Database\Driver\Postgres':
        if($this->pgSchema) {
          $qualifiedTableName = $this->pgSchema . '.' . $tableName;
        }
        break;

      case 'Cake\Database\Driver\Mysql':
        $qualifiedTableName = $this->getDatabase() . '.' . $tableName;
        break;

      default:
        $qualifiedTableName = $tableName;

    }

    return $qualifiedTableName;
  }
}
