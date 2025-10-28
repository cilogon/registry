<?php
/**
 * COmanage Registry Upsert Trait
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Lib\Traits;

trait UpsertTrait {
  /**
   * Perform an upsert. The upsert status is available in $entity->_upsertStatus.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  array                            $data         Data to persist
   * @param  array                            $whereClause  Conditions to search for current entity
   * @param  bool                             $orFail       If true, use saveOrFail() instead of save()
   * @param  array                            $options      Options for save
   * @return Cake\Datasource\EntityInterface|false          Persisted entity, or false on failure
   * @throws Cake\ORM\Exception\PersistenceFailedException
   * @throws Cake\ORM\Exception\RolledbackTransactionException
   */

  public function upsert(
    array $data,
    array $whereClause,
    bool  $orFail=false,
    array $options=[]
  ): \Cake\Datasource\EntityInterface|false {
    // First check if we have an entity matching $whereClause
    $entity = $this->find()
                   ->where($whereClause)
                   ->epilog('FOR UPDATE')
                   ->first();
    
    if($entity) {
      // This is an update

      $entity = $this->patchEntity($entity, $data);
      $entity->_upsertStatus = !empty($entity->getDirty()) ? 'update' : 'unchanged';
    } else {
      // This is an insert

      $entity = $this->newEntity($data);
      $entity->_upsertStatus = 'insert';
    }

    // We inject a hidden field into the entity to return the status because after
    // the save is called the standard Cake metadata will be reset, so calls like
    // isNew() or getOriginal() can't be used to determine what happened.
    $entity->setHidden(['_upsertStatus']);

    return $orFail ? $this->saveOrFail($entity, $options) : $this->save($entity, $options);
  }

  /**
   * Perform an upsert, or throw an exception on failure.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  array                            $data         Data to persist
   * @param  array                            $whereClause  Conditions to search for current entity
   * @param  array                            $options      Options for save
   * @return Cake\Datasource\EntityInterface|false          Persisted entity, or false on failure
   * @throws Cake\ORM\Exception\PersistenceFailedException
   */

  public function upsertOrFail(
    array $data,
    array $whereClause,
    array $options
  ): \Cake\Datasource\EntityInterface {
    return $this->upsert($data, $whereClause, true, $options);
  }
}
