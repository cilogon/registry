<?php
/**
 * COmanage Registry Dashboards Controller
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

namespace App\Controller;

// XXX not doing anything with Log yet
use Cake\Log\Log;
//use \App\Lib\Enum\PermissionEnum;

class DashboardsController extends StandardController {
  protected $permissions = [
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
      'dashboard'     => ['platformAdmin', 'coAdmin']   // XXX this is not the correct long term permission
/*      'add' =>      ['platformAdmin', 'coAdmin'],
      'index' =>    ['platformAdmin', 'coAdmin']
      */
    ]
  ];
  
  /**
   * Render the CO Configuration Dashboard.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function configuration() {
    $cur_co = $this->getCO();
    
    $this->set('vv_title', __d('operation', 'dashboard.configuration', $cur_co->name));
    
    // Construct the set of configuration items. For everything except CO Settings
    // we want to order by the localized text string.
    
    // We're assuming that the permission for each of these items is the same as for
    // configuration() itself, ie: CMP or CO Admin. But plausibly some of this stuff
    // could be delegated to (eg) a COU Admin at some point...
    
    $configMenuItems = [
      __d('controller', 'ApiUsers', [99]) => [
        'icon'          => 'vpn_key',
        'controller'    => 'api_users',
        'action'        => 'index'
      ],
      __d('controller', 'Cous', [99]) => [
        'icon'          => 'people_outline',
        'controller'    => 'cous',
        'action'        => 'index'
      ],
      __d('controller', 'Types', [99]) => [
        'icon'          => 'widgets',
        'controller'    => 'types',
        'action'        => 'index'
      ]
    ];
    
    ksort($configMenuItems);
    
    // Insert CO Settings to the front of the list

    $configMenuItems = array_merge([
      __d('controller', 'CoSettings', [99]) => [
        'icon'          => 'settings',
        'controller'    => 'co_settings',
        'action'        => 'add'
      ]],
      $configMenuItems
    );
    
    $this->set('vv_configuration_menu_items', $configMenuItems);

    $platformMenuItems = [];
    
    if($this->getCOID() == 1) {
      // Also pass the platform menu items
      
      $platformMenuItems = [
        __d('controller', 'Cos', [99]) => [
          'icon'          => 'build',  // XXX kind of want house here, but maybe need newer material icons?
          'controller'    => 'cos',
          'action'        => 'index'
        ]
      ];
    }
    
    ksort($platformMenuItems);
    
    $this->set('vv_platform_menu_items', $platformMenuItems);
  }
   
  /**
   * Render a Dashboard.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int   $id Dashboard ID
   */
  
  public function dashboard(?int $id=null) {
    // XXX placeholder
  }
}