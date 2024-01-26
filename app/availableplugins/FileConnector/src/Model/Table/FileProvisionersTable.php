<?php
/**
 * COmanage Registry File Provisioners Table
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

namespace FileConnector\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Lib\Enum\ProvisioningEligibilityEnum;
use App\Lib\Enum\ProvisioningStatusEnum;
use \FileConnector\Model\Entity\FileProvisioner;

class FileProvisionersTable extends Table {
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionerTrait;
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
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('ProvisioningTargets');
    
    $this->setDisplayField('filename');
    
    $this->setPrimaryLink(['provisioning_target_id']);
    $this->setRequiresCO(true);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false, //['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);

    $this->setProvisionableModels([
      'People',
      'Groups'
    ]);
  }
  
  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */

  public function buildRules(RulesChecker $rules): RulesChecker {
    // The requested file must exist and be writeable.

    $rules->add([$this, 'ruleIsFileWriteable'],
                'isFileWriteable',
                ['errorField' => 'filename']);

    return $rules;
  }

  /**
   * Provision object data to the provisioning target.
   *
   * @param   FileProvisioner  $provisioningTarget  FileProvisioner configuration
   * @param   string           $entityName
   * @param   object           $data                Provisioning data in Entity format (eg: \App\Model\Entity\Person)
   * @param   string           $eligibility         Provisioning Eligibility Enum
   *
   * @return array                                            Array of status, comment, and optional identifier
   * @since  COmanage Registry v5.0.0
   */

  public function provision(
    FileProvisioner $provisioningTarget,
    string $entityName,
    object $data,
    string $eligibility
  ): array {
    // Default output is an empty record
    $output = [ 'id' => $data->id ];

    if($eligibility == ProvisioningEligibilityEnum::Eligible) {
      $output = $data;
    }

    if(file_put_contents(
      filename: $provisioningTarget->filename,
      data: json_encode($output, JSON_INVALID_UTF8_SUBSTITUTE) . "\n",
      flags: FILE_APPEND
    ) === false) {
      throw new \RuntimeException("Write to " . $provisioningTarget->filename . " failed");
    }
    
    return [
      'status'      => ProvisioningStatusEnum::Provisioned,
      'comment'     => "Wrote 1 record to file",
      'identifier'  => null
    ];
  }

  /**
   * Application Rule to determine if the current entity is a writeable file.
   *
   * @param   Entity  $entity   Entity to be validated
   * @param   array   $options  Application rule options
   *
   * @return string|bool true if the Rule check passes, false otherwise
   * @since  COmanage Registry v5.0.0
   */

  public function ruleIsFileWriteable($entity, array $options): string|bool {
    if(!is_writable($entity->filename)) {
      return __d('file_connector', 'error.filename.writeable', [$entity->filename]);
    }

    return true;
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
    
    $this->registerStringValidation($validator, $schema, 'filename', true);
    
    return $validator; 
  }
}