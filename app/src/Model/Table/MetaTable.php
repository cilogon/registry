<?php
/**
 * COmanage Registry Meta Table
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

use \Cake\Datasource\ConnectionManager;
use \Cake\ORM\Table;

class MetaTable extends Table {
  use \App\Lib\Traits\TableMetaTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Metadata);
    
    $this->setDisplayField('upgrade_version');
  }
  
  /**
   * Determine the current "upgrade" version.
   *
   * @since  COmanage Registry v5.0.0
   * @return Current version
   */

  public function getUpgradeVersion() {
    $sql = "SELECT upgrade_version FROM meta";
    
    $connection = ConnectionManager::get('default');
    $results = $connection->execute($sql)->fetchAll('assoc');
    
    return $results['upgrade_version'];
  }

  /**
   * Update the current "upgrade" version.
   *
   * @since  COmanage Registry v5.0.0
   * @param  String $version New current version
   * @param  Boolean $insert Whether to assume an insert rather than an update
   * @return Boolean True on success
   */

  public function setUpgradeVersion($version, $insert=false) {
    $sql = null;

    if($insert) {
      $sql = "INSERT INTO meta (upgrade_version) VALUES (:v)";
    } else {
      $sql = "UPDATE meta SET upgrade_version = :v";
    }
    
    $connection = ConnectionManager::get('default');
    $results = $connection->execute($sql, ['v' => $version])->fetchAll('assoc');

    return true;
  }
}