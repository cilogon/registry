<?php
/**
 * COmanage Registry Provisioning History Records Table
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
use App\Lib\Enum\ProvisioningStatusEnum;

class ProvisioningHistoryRecordsTable extends Table {
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
    $this->belongsTo('ProvisioningTargets');
    $this->belongsTo('People');
    $this->belongsTo('Groups');
    
    $this->setDisplayField('comment');
    
    // We list provisioning_target_id last so breadcrumbs don't try to use it
    $this->setPrimaryLink(['person_id', 'group_id', 'provisioning_target_id']);
    //$this->setAllowLookupPrimaryLink(['primary']);
    $this->setRequiresCO(true);
    
    $this->setAutoViewVars([
      'statuses' => [
        'type'  => 'enum',
        'class' => 'ProvisioningStatusEnum'
      ]
    ]);

    $this->setIndexContains(['ProvisioningTargets']);

    $this->setViewContains([
      'People' => ['PrimaryName'],
      'Groups'
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
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  JobHistoryRecord $entity Entity to generate display field for
   * @return string                   Display field
   */

  public function generateDisplayField(\App\Model\Entity\ProvisioningHistoryRecord $entity): string {
    // Comments may be too long to render, so we just use the model name
    // (which will get appended with the record ID)

    return __d('controller', 'ProvisioningHistoryRecords', [1]);
  }
  
  /**
   * Record a Provisioning History Record.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $provisioningTargetId   Provisioning Target ID
   * @param  string $comment                Comment
   * @param  string $status                 ProvisioningStatusEnum
   * @param  string $subjectModel           The provisioned model
   * @param  int    $subjectId              The provisioned entity id (of type $subjectModel)
   * @return int                            Provisioning History Record ID
   */

  public function record(int    $provisioningTargetId, 
                         string $comment,
                         string $status,
                         string $subjectModel,
                         int    $subjectId): int {
    // We record all models and foreign keys, but only select (primary) models
    // have database level foreign key relations (for viewing history) so we
    // populate the correct foreign key if supported

    $personId = null;
    $groupId = null;

    switch($subjectModel) {
      case 'Groups':
        $groupId = $subjectId;
        break;
      case 'People':
        $personId = $subjectId;
        break;
      default:
        break;
    }

    $obj = $this->newEntity([
      'provisioning_target_id'  => $provisioningTargetId,
      'comment'                 => $comment,
      'status'                  => $status,
      'subject_model'           => $subjectModel,
      'subjectid'               => $subjectId,
      'person_id'               => $personId,
      'group_id'                => $groupId
    ]);
    
    $this->saveOrFail($obj);

// XXX trace this too? (below is copy/paste from Job History)
    // For now, always trace log Job History. We might do something more complicated later.
    // eg: Make it configurable whether we create Job History, log, or both?
    // This is documented at https://spaces.at.internet2.edu/display/COmanage/Registry+PE+Jobs#RegistryPEJobs-RegistryJobHistory
//   $this->llog('trace', $comment, "{$jobId}:{$recordKey}");
    
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
    
    $validator->add('provisioning_target_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('provisioning_target_id');
        
    $this->registerStringValidation($validator, $schema, 'comment', true);
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', ProvisioningStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'subject_model', true);

    $validator->add('subjectid', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('subjectid');

    $validator->add('person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('person_id');

    $validator->add('group', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('group');
    
    return $validator; 
  }
}