<?php
/**
 * COmanage Registry Query Modification Trait
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

use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Query;
use Cake\Utility\Inflector;

trait QueryModificationTrait {
  // Array of associated models to copy during a duplicate
  private $duplicateContains = false;
  
  // Array of associated models to pull during an edit
  private $editContains = false;
  
  // Containable models for index actions
  private $indexContains = null;
  
  // Filter (where clause) for index actions
  private $indexFilter = null;
  
  // Array of associated models to save during a patch
  private $patchAssociated = [];
  
  // Array of associated models to pull during a view
  private $viewContains = false;

  // Array of associated models to pull during a pick action
  private $pickerContains = false;


  /**
   * Construct the checkValidity for the fields valid_from and valid_through
   *
   * @param   Query  $query
   *
   * @return QueryExpression
   * @since  COmanage Registry v5.0.0
   */
  public function checkValidity(Query $query): QueryExpression {
    $fieldModelPrefix = Inflector::pluralize(substr($this->getEntityClass(), strrpos($this->getEntityClass(), '\\')+1));

    $exp = $query->newExpr();
    $orValidFromConditions = $exp->or(
      fn(QueryExpression $or) => $or->isNull($fieldModelPrefix . '.valid_from')
                                    ->lt($fieldModelPrefix . '.valid_from', date('Y-m-d H:i:s'))
    );
    $orValidThroughConditions = $exp->or(
      fn(QueryExpression $or) => $or->isNull($fieldModelPrefix . '.valid_through')
                                    ->gt($fieldModelPrefix . '.valid_through', date('Y-m-d H:i:s'))
    );

    return $exp->add($orValidFromConditions)
               ->add($orValidThroughConditions);
  }

  /**
   * Obtain the set of associated models to copy during a duplicate.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of associated models
   */
  
  public function getDuplicateContains() {
    return $this->duplicateContains;
  }
  
  /**
   * Obtain the set of associated models to pull during an edit.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of associated models
   */
  
  public function getEditContains() {
    return $this->editContains;
  }
  
  /**
   * Containable models for index actions.
   * 
   * @since  COmanage Registry v5.0.0
   * @param boolean $allowEmpty true if the primary link is permitted to be empty
   */
  
  public function getIndexContains() {
    return $this->indexContains;
  }
  
  /**
   * Obtain the set of associated models to save during a patch.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of associated models
   */
  
  public function getPatchAssociated() {
    return $this->patchAssociated;
  }

  /**
   * Obtain the set of associated models to pull during a pick.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of associated models
   */

  public function getPickerContains() {
    return $this->pickerContains;
  }

  /**
   * Obtain the set of associated models to pull during a view.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of associated models
   */
  
  public function getViewContains() {
    return $this->viewContains;
  }
  
  /**
   * Set the associated models to copy during a duplicate.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $c Array of associated models
   */
  
  public function setDuplicateContains(array $c) {
    $this->duplicateContains = $c;
  }
  
  /**
   * Set the associated models to pull during an edit.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $c Array of associated models
   */
  
  public function setEditContains(array $c) {
    $this->editContains = $c;
  }
  
  /**
   * Set containable models for index actions.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array $contains Containable models
   */
  
  public function setIndexContains(array $contains) {
    $this->indexContains = $contains;
  }
  
  /**
   * Set the index filter for this model.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array|Closure $filter Array of index filters or closure that generates the array
   */
  
  public function setIndexFilter(array|\Closure $filter) {
    $this->indexFilter = $filter;
  }
  
  /**
   * Set the associated models to save during a patch.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $a Array of associated models
   */
  
  public function setPatchAssociated(array $a) {
    $this->patchAssociated = $a;
  }

  /**
   * Set the associated models to pull during a pick.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $c Array of associated models
   */

  public function setPickerContains(array $c) {
    $this->pickerContains = $c;
  }

  /**
   * Set the associated models to pull during a view.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $c Array of associated models
   */
  
  public function setViewContains(array $c) {
    $this->viewContains = $c;
  }
}
