<?php
/**
 * COmanage Registry Application States Table
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

namespace App\Model\Table;

use App\Lib\Enum\ApplicationStateEnum;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ApplicationStatesTable extends Table {
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Log');
    $this->addBehavior('Changelog');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Metadata);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('People');
    
    $this->setDisplayField('tag');
    $this->setPrimaryLink(['co_id', 'person_id']);


    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   true,
        'edit' =>     true,
        'unfreeze' => true,
        'view' =>     true
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      true,
        'index' =>    true,
        'deleted' =>  true
      ],
    ]);
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $this->registerPrimaryKeyValidation($validator, $this->getPrimaryLinks());
    
    $this->registerStringValidation($validator, $schema, 'tag', true);
    
    $this->registerStringValidation($validator, $schema, 'value', false);
//    $validator->add('co_id', [
//      'content' => ['rule' => 'isInteger']
//    ]);
    $this->registerStringValidation($validator, $schema, 'username', false);
    $validator->add('username', [
      // Username must have at least one non-space character to avoid
      'content' => ['rule'    => ['notBlank'],
                    'message' => __d('error', 'input.blank')]
    ]);

    return $validator; 
  }

  /**
   * Retrieve all Application State.
   *
   * @param   string    $username
   * @param   int|null  $coid
   * @param   int|null  $personid
   *
   * @return array      List of application states or an empty array
   * @since  COmanage Registry v5.1.0
   */

  public function retrieveAll(string $username, ?int $coid, ?int $personid): array {
    $subquery = $this->find();
    $subquery = $subquery->where(['username' => $username]);
    if ($coid !== null) {
      $subquery = $subquery->where(['co_id' => $coid]);
    } else {
      $subquery = $subquery->where(fn(QueryExpression $exp, Query $query) => $exp->isNull('co_id'));
    }
    if ($personid !== null) {
      $subquery = $subquery->where(['person_id' => $personid]);
    } else {
      $subquery = $subquery->where(fn(QueryExpression $exp, Query $query) => $exp->isNull('person_id'));
    }
    $subquery = $subquery->select($this);
    $tags = $subquery->toArray();

    return $tags ?? [];
  }


  /**
   * Create or update an application state.
   *
   * This method either updates an existing application state record or creates
   * a new one based on the provided data and value.
   *
   * @param array $data Associative array containing fields and their values.
   * @param string $value Value to set for the application state.
   * @return void
   * @throws \Cake\ORM\Exception\PersistenceFailedException When a save operation fails.
   * @since COmanage Registry v5.2.0
   */
  public function createOrUpdate(array $data, string $value): void
  {
    $appId = $this->find()
      ->cache(false)
      ->where($data)->first();

    // Enter the new value
    $data['value'] = $value;

    If ($appId->id !== null) {
      // Update existing record
      $appId->set($data);
      $this->saveOrFail($appId);
    } else {
      $newRecord = $this->newEntity($data);
      // Create new entry
      $this->saveOrFail($newRecord);
    }
  }
}