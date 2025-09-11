<?php
/**
 * COmanage Registry Menu Helper
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
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\View\Helper;

use \Cake\View\Helper;

class MenuHelper extends Helper {

 public array $helpers = ['Html'];

  /**
   * Get the Menu Order per action
   *
   * @param string $action
   * @return int|null
   *
   * @since  COmanage Registry v5.0.0
   */
  public function getMenuOrder($action) {
    if(empty($action)) {
      return null;
    }

    $order = array(
      'Add'           => 3,    // add_circle
      'View'          => 5,    // visibility
      'Edit'          => 10,   // edit
      'Default'       => 20,   // link - default starting order for arbitrary action menu items 
      'Delete'        => 100   // delete
    );

    return $order[$action];
  }

  /**
   * Get the Menu Icon per action
   *
   * @param string $action
   * @return string|null
   *
   * @since  COmanage Registry v5.0.0
   */
  public function getMenuIcon($action) {
    if(empty($action)) {
      return null;
    }

    $icon = array(
      'Add'           =>  'add_circle',
      'View'          =>  'visibility',
      'Edit'          =>  'edit',
      'Default'       =>  'link',  // default icon for arbitrary menu items
      'Delete'        =>  'delete'
    );

    // For the actions with Default order we can pass directly the name of the icon
    return $icon[$action] ?? $action;
  }

}