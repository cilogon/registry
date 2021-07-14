<?php
/**
 * COmanage Registry Cos Controller
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

use Cake\ORM\TableRegistry;

class CosController extends StandardController {
  protected $permissions = [
    // Actions that operate over an entity (ie: require an $id)
    'entity' => [
      'delete' =>    ['platformAdmin'],
      'duplicate' => ['platformAdmin'],
      'edit' =>      ['platformAdmin'],
      'view' =>      ['platformAdmin']
    ],
    // Actions that are permitted on readonly entities (besides view)
    'readOnly' =>    ['duplicate'],
    // Actions that operate over a table (ie: do not require an $id)
    'table' => [
      'add' =>       ['platformAdmin'],
      'index' =>     ['platformAdmin'],
      'select' =>    ['authenticatedUser']
    ]
  ];
  
  /*
   * XXX implement, also REST API
   *
   * @since  COmanage Registry v5.0.0
   * @param  Integer $id CO ID
   */
  
  public function duplicate(int $id) {
    
  }
  
  /**
   * Provide a set of COs to operate on.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function select() {
    // Population of vv_available_cos is currently done in AppController
  }
}