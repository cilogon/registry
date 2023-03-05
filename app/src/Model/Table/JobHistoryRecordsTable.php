<?php
/**
 * COmanage Registry Job History Records Table
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
use Cake\Validation\Validator;
use App\Lib\Enum\JobStatusEnum;

class JobHistoryRecordsTable extends Table {
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
    $this->belongsTo('Jobs');
    $this->belongsTo('People');
    $this->belongsTo('ExternalIdentities');
    
    $this->setDisplayField('comment');
    
    $this->setPrimaryLink(['job_id']);
    $this->setAllowLookupPrimaryLink(['primary']);
    $this->setRequiresCO(true);
    
    $this->setAutoViewVars([
      'statuses' => [
        'type'  => 'enum',
        'class' => 'JobStatusEnum'
      ]
    ]);

    $this->setViewContains([
      'People' => ['PrimaryName'],
      'ExternalIdentities' => ['PrimaryName']
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
        'add' =>      false, //['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
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

    return __d('controller', 'JobHistoryRecords', [1]);
  }
  
  /**
   * Record a Job History Record.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $jobId                  Job ID
   * @param  string $recordKey              A Job specific record identifier
   * @param  string $comment                Comment
   * @param  int    $personId               Person ID
   * @param  int    $externalIdentityId     External Identity ID
   * @return int                            Job History Record ID
   */
  
  public function record(int $jobId, 
                         string $recordKey,
                         string $comment,
                         string $status=JobStatusEnum::Notice,
                         ?int $personId=null,
                         ?int $externalIdentityId=null,
                         ?int $externalIdentityRoleId=null): int {
    $obj = $this->newEntity([
      'job_id'                => $jobId,
      'record_key'            => $recordKey,
      'comment'               => $comment,
      'person_id'             => $personId,
      'external_identity_id'  => $externalIdentityId,
      'status'                => $status
    ]);
    
    $this->saveOrFail($obj);

    // For now, always trace log Job History. We might do something more complicated later.
    // eg: Make it configurable whether we create Job History, log, or both?
    // This is documented at https://spaces.at.internet2.edu/display/COmanage/Registry+PE+Jobs#RegistryPEJobs-RegistryJobHistory
    $this->llog('trace', $comment, "{$jobId}:{$recordKey}");
    
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
    
    $validator->add('job_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('job_id');
    
    $validator->add('record_key', [
      'length' => ['rule'     => ['validateMaxLength', ['column' => $schema->getColumn('record_key')]],
                   'provider' => 'table'],
    ]);
    $validator->allowEmptyString('record_key');
    
    $validator->add('comment', [
      'length' => ['rule'     => ['validateMaxLength', ['column' => $schema->getColumn('comment')]],
                   'provider' => 'table'],
    ]);
    $validator->notEmptyString('comment');
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', JobStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $validator->add('person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('person_id');

    $validator->add('external_identity_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('external_identity_id');

    return $validator; 
  }
}