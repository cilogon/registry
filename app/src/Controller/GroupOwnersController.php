<?php
/**
 * COmanage Registry Group Owners Controller
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

class GroupOwnersController extends StandardController {
  use \App\Lib\Traits\PermissionsTrait;
  
  public $pagination = [
    'order' => [
      'People.primary_name.name' => 'asc'
    ]
  ];
  
  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */

  public function beforeRender(\Cake\Event\EventInterface $event) {
    // Pull the Group name for breadcrumb rendering
    
    $link = $this->getPrimaryLink(true);
    
    if(!empty($link->value)) {
      $this->set('vv_bc_parent_obj', $this->GroupOwners->Groups->get($link->value));
      $this->set('vv_bc_parent_displayfield', $this->GroupOwners->Groups->getDisplayField());
    }
    
    return parent::beforeRender($event);
  }

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function initialize(): void {
    parent::initialize();
    
    $this->setPermissions([
// XXX update for couAdmins, group owners, etc
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     false,
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }
}