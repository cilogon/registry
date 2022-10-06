<?php
/**
 * COmanage Registry Dashboards Table
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

use \Cake\ORM\Table;

class DashboardsTable extends Table {
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
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Timestamp');
    $this->addBehavior('Timezone');
    
    // Dashboards are configuration
    $this->setIsConfigurationTable(false);
    
    // Define associations
    $this->belongsTo('Cos');
    
    $this->setDisplayField('name');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setAllowUnkeyedPrimaryCO(['configuration', 'dashboard', 'search']);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
  /*
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']*/
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'configuration' => ['platformAdmin', 'coAdmin'],
        // XXX CFM-230 This needs to be updated for actual Dashboard permissions
        'dashboard'     => ['platformAdmin', 'coAdmin', 'coMember'],
        'search'        => ['platformAdmin', 'coAdmin']
  /*      'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']*/
      ]
    ]);
  }
}