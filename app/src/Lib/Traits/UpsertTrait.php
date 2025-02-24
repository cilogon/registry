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
  use \App\Lib\Traits\LabeledLogTrait;

  /**
   * Perform an upsert.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  array                            $data         Data to persist
   * @param  array                            $whereClause  Conditions to search for current entity
   * @return Cake\Datasource\EntityInterface|false          Persisted entity, or false on failure
   * @throws Cake\ORM\Exception\RolledbackTransactionException
   */
  public function upsert(
    array $data,
    array $whereClause
  ): \Cake\Datasource\EntityInterface|false {
    // First check if we have an entity matching $whereClause
    $entity = $this->find()
                   ->where($whereClause)
                   ->epilog('FOR UPDATE')
                   ->first();
    
    if($entity) {
      // This is an update

      $entity = $this->patchEntity($entity, $data);
    } else {
      // This is an insert

      $entity = $this->newEntity($data);
    }

    if (!empty($entity->getErrors())) {
      $this->llog('error', "Save failed for {$this->getAlias()}: " . print_r($entity->getErrors(), true));
      throw new \RuntimeException(__d('error', 'save', [$this->getAlias()]));
    }

    return $this->save($entity);
  }

  /**
   * Perform an upsert, or throw an exception on failure.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  array                            $data         Data to persist
   * @param  array                            $whereClause  Conditions to search for current entity
   * @return Cake\Datasource\EntityInterface|false          Persisted entity, or false on failure
   * @throws Cake\ORM\Exception\PersistenceFailedException
   */

  public function upsertOrFail(
    array $data,
    array $whereClause
  ): \Cake\Datasource\EntityInterface {
    $entity = $this->upsert($data, $whereClause);

    if($entity === false) {
      throw new Cake\ORM\Exception\PersistenceFailedException($entity, ['upsert']);
    }

    return $entity;
  }
}
