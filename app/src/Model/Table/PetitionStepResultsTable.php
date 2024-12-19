<?php
/**
 * COmanage Registry Petition Step Results Table
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

use \Cake\ORM\Table;
use \Cake\Validation\Validator;
use \App\Lib\Enum\PetitionStatusEnum;

class PetitionStepResultsTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
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
    // We enable Changelog here in case a Step decides to revise its result
    $this->addBehavior('Changelog');
    $this->addBehavior('Timestamp');
    $this->addBehavior('Timezone');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);
    
    // Define associations
    $this->belongsTo('EnrollmentFlowSteps');
    $this->belongsTo('Petitions');

    $this->setDisplayField('comment');
    
    $this->setPrimaryLink('petition_id');
    $this->setRequiresCO(true);
    
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
   * Record a Petition Step Result.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $enrollmentFlowStepId Enrollment Flow Step ID
   * @param  int    $petitionId           Petition ID
   * @param  string $status               Status
   * @param  string $comment              Comment
   * @return int                          Petition Step Result ID
   */

  public function record(
    int     $enrollmentFlowStepId,
    int     $petitionId,
    string  $comment
  ): int {
    $obj = $this->newEntity([
      'enrollment_flow_step_id' => $enrollmentFlowStepId,
      'petition_id'             => $petitionId,
      'comment'                 => $comment
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
    $validator->notEmptyString('enrollment_flow_step_id');

    $this->registerStringValidation($validator, $schema, 'comment', true);

    return $validator; 
  }
}