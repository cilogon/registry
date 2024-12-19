<?php
/**
 * COmanage Registry Petition History Records Table
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

use Cake\ORM\Table;
use Cake\Event\EventInterface;
use Cake\Validation\Validator;
use App\Lib\Enum\JobStatusEnum;

class PetitionHistoryRecordsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);
    
    // Define associations
    $this->belongsTo('Petitions');
    $this->belongsTo('EnrollmentFlowSteps');
    $this->belongsTo('ActorPeople')
         ->setClassName('People')
         ->setForeignKey('actor_person_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('actor_person');
    
    $this->setDisplayField('comment');
    
    $this->setPrimaryLink(['petition_id']);
    $this->setRequiresCO(true);

    $this->setViewContains([
      // contain results in a join when the relation is belongsTo (or hasOne),
      // and joining the same table twice makes the database unhappy, so we
      // force ActorPeople to use multiple queries.
      'ActorPeople' => ['Names' => ['queryBuilder' => function ($q) {
        return $q->where(['primary_name' => true]);
      }]]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false,
        'edit' =>     false,
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false,
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }
  
  /**
   * Perform actions while marshaling data, before validation.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event    Event
   * @param  ArrayObject    $data     Object data, in array format
   * @param  ArrayObject    $options  Entity save options
   */

  public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options)
  {
    if(!empty($data['comment'])) {
      // Truncate the comment to fit the column width
      $column = $this->getSchema()->getColumn('comment');

      $data['comment'] = substr($data['comment'], 0, $column['length']);
    }
  }
  
  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  JobHistoryRecord $entity Entity to generate display field for
   * @return string                   Display field
   */

  public function generateDisplayField(\App\Model\Entity\JobHistoryRecord $entity): string {
    // Comments may be too long to render, so we just use the model name
    // (which will get appended with the record ID)

    return __d('controller', 'PetitionHistoryRecords', [1]);
  }
  
  /**
   * Record a Petition History Record.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $petitionId             Petition ID
   * @param  string $enrollmentFlowStepId   Enrollment Flow Step ID, or null for start or finalize
   * @param  string $action                 PetitionActionEnum
   * @param  string $comment                Comment
   * @param  int    $actorPersonId          Actor Person ID
   * @return int                            Petition History Record ID
   */
  
  public function record(
    int    $petitionId, 
    ?int   $enrollmentFlowStepId=null,
    string $action,
    string $comment,
    ?int $actorPersonId=null
  ): int {
    $obj = $this->newEntity([
      'petition_id'             => $petitionId,
      'enrollment_flow_step_id' => $enrollmentFlowStepId,
      'action'                  => $action,
      'comment'                 => $comment,
      'actor_person_id'         => $actorPersonId
    ]);
    
    $this->saveOrFail($obj);
    
    return $obj->id;
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
    
    $validator->add('petition_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('petition_id');
    
    $validator->add('enrollment_flow_step_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    // There is no enrollment_flow_step_id for start or finalize
    $validator->allowEmptyString('enrollment_flow_step_id');

    $this->registerStringValidation($validator, $schema, 'action', true);

    $this->registerStringValidation($validator, $schema, 'comment', true);

    $validator->add('actor_person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('actor_person_id');

    return $validator; 
  }
}