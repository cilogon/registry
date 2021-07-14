<?php
/**
 * COmanage Registry CO Link Trait
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

namespace App\Lib\Traits;

// XXX Merge this into PrimaryLinkTrait? note Match already merged most of this stuff
trait CoLinkTrait {
  // Does the associated model require a CO ID?
  private $requiresCO = false;
  
  // If we normally require a CO, can we proceed without one?
  private $allowEmptyCO = false;
  
  // Actions that can have an unkeyed (ie: self asserted) CO ID
  private $unkeyedActions = ['add', 'index'];
  
  /**
   * If the associated controller normally requires a CO ID, whether the
   * CO ID can be empty.
   * 
   * @since  COmanage Registry v5.0.0
   * @return boolean true if empty Matchgrid IDs are permitted
   */
  
  public function allowEmptyCO() {
    return $this->allowEmptyCO;
  }
  
  /**
   * Check to see whether the specified action is allowed to assert a CO ID
   * directly (ie: not via lookup of an associated record).
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $action Action
   * @return boolean true if permitted, false otherwise
   */
  
  public function allowUnkeyedCO(string $action) {
    return in_array($action, $this->unkeyedActions, true);
  }

  /**
   * Calculate the CO ID associated with the requested object ID.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int $id Object ID
   * @return int     CO ID
   * @throws Cake\Datasource\Exception\RecordNotFoundException
   */
  
  public function calculateCoId(int $id) {
    // For now we assume we have a direct foreign key to Cos.
    
    $obj = $this->findById($id)->firstOrFail();
    
    return $obj->co_id;
  }
  
  /**
   * Determine if the associated controller requires a CO ID.
   *
   * @since  COmanage Registry v5.0.0
   * @return boolean True if a CO ID is required, false otherwise
   */
  
  public function requiresCO() {
    return $this->requiresCO;
  }
  
  /**
   * Set if the associated controller normally requires a CO ID, whether the
   * CO ID can be empty.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  boolean $allowEmpty True if the CO ID is permitted to be empty
   */
  
  public function setAllowEmptyCO(bool $allowEmpty) {
    $this->allowEmptyCO = $allowEmpty;
  }
  
  /**
   * Set whether the CO can be asserted directly.
   *
   * @since  COmanage Registry v5.0.0
   * @param boolean $allowEmpty true if the CO can be asserted directly
   */

  public function setAllowUnkeyedPrimaryCO(array $actions) {
    $this->unkeyedActions = array_merge($this->unkeyedActions, $actions);
  }
  
  /**
   * Set if the associated controller requires a CO ID.
   *
   * @since  COmanage Registry v5.0.0
   * @param  boolean $required Boolean True if a Matchgrid ID is required, false otherwise
   */
  
  public function setRequiresCO(bool $required) {
    $this->requiresCO = $required;
  }
}
