<?php
/**
 * COmanage Registry Tree Trait
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Lib\Traits;

use \Cake\Utility\Inflector;
use \App\Lib\Util\StringUtilities;

// Utility functions for models that use TreeBehavior
trait TreeTrait {
  /**
   * Assemble the set of potential parents. This function is primarily intended for
   * UI purposes, ie to build a list of available parent entities. In general, potentially
   * invalid parents are also included, to allow an error message to be thrown when one
   * is selected.
   *
   * @since  COmanage Registry v5.2.0
   * @param  int    $coId      CO ID
   * @param  int    $id        Entity ID to determine potential parents of, or null for any (or a new) entity
   * @param  bool   $hierarchy Render the hierarchy in the name
   * @param  array  $where     Additional conditions for filtering potential parents
   * @return array             Array of Entity IDs and Names
   */
  
  public function potentialParents(
    int $coId, 
    ?int $id=null, 
    bool $hierarchy=false,
    array $where=[]
  ) {
    // Note prior to v5 we filtered child COUs, meaning a COU couldn't be reassigned
    // to be a child of a current child. It's not clear why we imposed that restriction.
    // We could generate a list of only valid parents by using BETWEEN, see TreeBehavior
    // _setParents(), eg SELECT * FROM table WHERE lft > $entity->lft AND lft < $entity->rght
    // or something like that.

    $query = null;
    
    if($hierarchy) {
      $query = $this->find('treeList', spacer: '+ ');
    } else {
      $query = $this->find('list');
    }
    
    $query = $query->where(['co_id' => $coId])
                  // true overrides the default Cake order for treeList so we get
                  // our items sorted alphabetically instead of by tree ID
                   ->orderBy(['name' => 'ASC'], true);
    
    if($id) {
      $query = $query->where(['id <>' => $id]);
    }

    if(!empty($where)) {
      $query = $query->where($where);
    }
    
    return $query->toArray();
  }

  /**
   * Application Rule to determine if the parent ID is a potential parent.
   * Cake's TreeBehavior will perform checks as part of callbacks (eg) beforeSave,
   * but the error messages are not localized and fairly opaque. We'll check for
   * common failure modes here and throw better errors before TreeBehavior does
   * its thing.
   *
   * @since  COmanage Registyr v5.2.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function rulePotentialParent($entity, $options) {
    // The Entity we're working with, for error messages
    $entityNane = Inflector::singularize(StringUtilities::entityToClassName($entity));

    if(!empty($entity->parent_id)) {
      // First check that we're not trying to set ourself to be our own parent
      if($entity->id == $entity->parent_id) {
        return __d('error', 'tree.parent.same', [$entityNane]);
      }

      // This check is based on TreeBehavior::_setParent()
      $parent = $this->get($entity->parent_id);

      if($parent->lft > $entity->lft && $parent->lft < $entity->rght) {
        return __d('error', 'tree.parent.invalid', [$entityNane]);
      }
    }

    return true;
  }
}
