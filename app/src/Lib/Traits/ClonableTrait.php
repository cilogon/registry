<?php
/**
 * COmanage Registry Clonable Trait
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

use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;
use \Cake\Utility\Inflector;
use \App\Lib\Util\SearchUtilities;
use \App\Lib\Util\StringUtilities;
use \App\Lib\Util\TableUtilities;

// Utility functions for models that use ClonableBehavior
trait ClonableTrait {
  /**
   * Obtain the list of entities that must be cloned before the requested entity.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  EntityInterface  $original Entity to be cloned
   * @return array                      Array of UUIDs to clone, in order, before $original
   */
  
  public function getClonePredecessors(EntityInterface $original): array {
    // This is substantially the same logic as fixCloneForeignKeys, which means
    // we'll end up looking up the predecessors several times; once here, once when
    // we clone the predecessor, and again in fixCloneForeifnKeys. It might make sense
    // to cache these lookups.

    $ret = [];

    foreach($this->associations()->getByType('BelongsTo') as $assn) {
      if($assn->getClassName() == 'Cos') {
        // We don't clone CO
        continue;
      }

      $aForeignKey = $assn->getForeignKey();

      if(!empty($original->$aForeignKey)) {
        // We have a non-empty value for this foreign key. Get the object and add its
        // UUID to the list.

        // We're the Target Table, so we need to get a handle to the Source Table
        $SourceTable = TableUtilities::getTableWithDataSource(
          tableName: $assn->getClassName(),
          connectionName: 'default'
        );

        $originalForeignEntity = $SourceTable->get($original->$aForeignKey);

        // We don't need to check for dupes because CloneCommand will track which
        // entities it has already cloned
        $ret[] = $originalForeignEntity->uuid;
      }
    }

    return $ret;
  }

  /**
   * Obtain a record by UUID.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string           $uuid   UUID
   * @param  int              $coId   CO ID
   * @return EntityInterface          Entity
   * @throws \Cake\Datasource\Exception\RecordNotFoundException
   */

  public function getByUuid(string $uuid, int $coId) {
    // Clonable model must FK directly to CO
    return $this->find()->where(['uuid' => $uuid, 'co_id' => $coId])->firstOrFail();
  }

  /**
   * Prepare an entity for cloning.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  EntityInterface  $original   Original entity
   * @param  EntityInterface  $clone      Clone (not yet saved)
   * @param  string           $dataSource DataSource connection name
   * @return EntityInterface              Clone, updated as necessary
   */

  public function prepareClone(
    EntityInterface $original,
    EntityInterface $clone,
    string $dataSource
  ): EntityInterface {
    // If a parent_id is set, we need to map the _source_ parent to its UUID, then
    // find the same UUID on the target, then replace the foreign key.

    if(!empty($original->parent_id)) {
      // We're the Target Table, so we need to get a handle to the Source Table
      $SourceTable = TableUtilities::getTableWithDataSource(
        tableName: StringUtilities::entityToClassName($original),
        connectionName: 'default'
      );

      $originalParent = $SourceTable->get($original->parent_id);

      // We're the Target Table, query using the UUID we just found
      $cloneParent = $this->getByUuid($originalParent->uuid, $clone->co_id);

      // Update the foreign key
      $clone->parent_id = $cloneParent->id;
    }

    return $clone;
  }

  /**
   * Application Rule to determine if the entity's UUID is unique within the CO
   * across all clonable objects, not just those of the same type.
   *
   * @since  COmanage Registyr v5.2.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleUuidUnique($entity, $options) {
    // This is somewhat weird given that UUIDs are supposed to be unique,
    // but we're addressing the use case of an administrator manually assigning
    // the same UUID to two different objects, which is an error state.

    // ClonableBehavior::beforeMarshal will set a flag if it generated a UUID
    // so we know we can skip this (expensive) check.
    if($entity->has('_uuidGenerated') && $entity->_uuidGenerated) {
      return true;
    }

    // CloneCommand will set a flag for records it is processing for the same reason.
    if($entity->has('_uuidCloned') && $entity->_uuidCloned) {
      return true;
    }

    // If the UUID is unchanged we don't need to check it.
    if(!$entity->isDirty('uuid')) {
      return true;
    }

    // If we make it here the admin set the UUID, so we do need to check.

    // Clonable model must FK directly to CO
    $result = SearchUtilities::uuidSearch($entity->co_id, $entity->uuid);
    
    if(!empty($result['entity']) && !empty($entity->id) && ($entity->id != $result['entity']->id)) {
      return __d('error', 'exists.uuid', [$result['class'], $result['entity']->id]); 
    }

    return true;
  }
}
