<?php
/**
 * COmanage Registry Person Role Mappers Table
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

namespace PipelineToolkit\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class PersonRoleMappersTable extends Table {
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
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
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Flanges');

    $this->hasMany('PipelineToolkit.PersonRoleMappings')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setDisplayField('attribute');
    
    $this->setPrimaryLink(['flange_id']);
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
        'add' =>      false,
        'index' =>    ['platformAdmin', 'coAdmin']
      ],
      // Related models whose permissions we'll need, typically for table views
      'related' => [
        'table' => [
          'PipelineToolkit.PersonRoleMappings'
        ]
      ]
    ]);
  }

  /**
   * Apply mappings for buildPersonRole.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Flange               $flange   Flange, including top level plugin configuration
   * @param  array                $newdata  Array of new Person Role data
   * @param  ExternalIdentityRole $eirdata  Original External Identity Role data
   * @return array              Updated array of new Person Role data
   */

  public function buildPersonRole(
    \App\Model\Entity\Flange $flange,
    array $newdata,
    \App\Model\Entity\ExternalIdentityRole $eirdata
  ): array {
    $retdata = $newdata;

    // We need our Mapping configuration, which won't be in $flange
    $mappings = $this->PersonRoleMappings->find()
                     ->where(['person_role_mapper_id' => $flange->person_role_mapper->id])
                     ->orderBy(['ordr' => 'ASC'])
                     ->all();
    
    foreach($mappings as $mapping) {
      if($mapping->matches($retdata, $eirdata)) {
        // This Mapping matched, so update the return data

        if(!empty($mapping->target_affiliation_type_id)) {
          $this->llog('trace', $flange->description . " mapping External Identity Role ID " 
                               . $eirdata->id . " to Affiliation Type ID " 
                               . $mapping->target_affiliation_type_id);
          $retdata['affiliation_type_id'] = $mapping->target_affiliation_type_id;
        }

        if(!empty($mapping->target_cou_id)) {
          $this->llog('trace', $flange->description . " mapping External Identity Role ID " 
                               . $eirdata->id . " to COU ID " 
                               . $mapping->target_cou_id);
          $retdata['cou_id'] = $mapping->target_cou_id;
        }
      }
    }

    return $retdata;
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
    
    $validator->add('flange_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('flange_id');
    
    return $validator; 
  }
}