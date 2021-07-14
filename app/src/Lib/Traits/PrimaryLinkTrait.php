<?php
/**
 * COmanage Registry PrimaryLink Trait
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

trait PrimaryLinkTrait {
  // Primary Link field (eg: model:co_id)
  private $primaryLink = null;
  
  // Primary Link table name (eg: Cos)
  private $primaryLinkTable = null;
  
  // Allow empty primary link?
  private $allowEmpty = false;
  
  // Actions that can have an unkeyed (ie: self asserted) primary link ID
  private $unkeyedActions = ['add', 'index'];
  
  // Actions where the primary link can be obtained by looking up the record ID
  private $lookupActions = ['delete', 'edit', 'view'];
  
  /**
   * Whether the primary link is permitted to be empty.
   * 
   * @since  COmanage Registry v5.0.0
   * @param boolean $allowEmpty true if the primary link is permitted to be empty
   */
  
  public function allowEmptyPrimaryLink() {
    return $this->allowEmpty;
  }
  
  /**
   * Check to see whether the specified action allows a record ID to be passed
   * in the URL, which can be used to lookup a primary link.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $action Action
   * @return boolean true if permitted, false otherwise
   */
  
  public function allowLookupPrimaryLink(string $action) {
    return in_array($action, $this->lookupActions, true);
  }
  
  /**
   * Check to see whether the specified action is allowed to assert a primary link ID
   * directly (ie: not via lookup of an associated record).
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $action Action
   * @return boolean true if permitted, false otherwise
   */
  
  public function allowUnkeyedPrimaryLink(string $action) {
    return in_array($action, $this->unkeyedActions, true);
  }
  
  /**
   * Calculate the Primary Link ID associated with the requested object ID.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int $id Object ID
   * @return int     Primary Link ID
   * @throws Cake\Datasource\Exception\RecordNotFoundException
   */
  
  public function calculatePrimaryLinkId(int $id) {
    $obj = $this->findById($id)->firstOrFail();
    
    return $obj->{$this->primaryLink};
  }
  
  /**
   * Generate an ORM Query for the Primary Link.
   *
   * @since  COmanage Registry v5.0.0
   * @param  CakeORMQuery $query   Cake ORM Query
   * @param  array        $options Cake ORM Query options
   * @return CakeORMQuery          Cake ORM Query
   */
  
  public function findFilterPrimaryLink(\Cake\ORM\Query $query, array $options) {
    return $query->where([$this->getPrimaryLink() => $options[$this->primaryLink]]);
  }
  
  /**
   * Obtain the primary link.
   *
   * @since  COmanage Registry v5.0.0
   * @return string Primary link attribute
   */
  
  public function getPrimaryLink() {
    return $this->primaryLink;
  }
  
  /**
   * Obtain the primary link's table name.
   *
   * @since  COmanage Registry v5.0.0
   * @return string Primary link table name
   */
  
  public function getPrimaryLinkTableName() {
    return $this->primaryLinkTable;
  }
  
  /**
   * Set whether the primary link is permitted to be empty.
   * 
   * @since  COmanage Registry v5.0.0
   * @param boolean $allowEmpty true if the primary link is permitted to be empty
   */
  
  public function setAllowEmptyPrimaryLink(bool $allowEmpty) {
    $this->allowEmpty = $allowEmpty;
  }
  
  /**
   * Set whether the primary link can be resolved via the object ID in the URL.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  boolean $allowEmpty true if the primary link can be resolved via the URL ID
   */
  
  public function setAllowLookupPrimaryLink(array $actions) {
    $this->lookupActions = array_merge($this->lookupActions, $actions);
  }
  
  /**
   * Set the primary link attribute.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $field Primary link attribute
   */ 
  
  public function setPrimaryLink($field) {
    $this->primaryLink = $field;
    
    // Calculate the table name for future reference
    if(preg_match('/^(.*?)_id$/', $field, $f)) {
      $this->primaryLinkTable = \Cake\Utility\Inflector::camelize(\Cake\Utility\Inflector::pluralize($f[1]));
    }
  }
  
  /**
   * Set whether the primary link can be asserted directly.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  boolean $allowEmpty true if the primary link can be asserted directly
   */
  
  public function setAllowUnkeyedPrimaryLink(array $actions) {
    $this->unkeyedActions = array_merge($this->unkeyedActions, $actions);
  }
}
