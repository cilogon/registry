<?php
/**
 * COmanage Registry External Identity Roles Table
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

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use \App\Lib\Enum\StatusEnum;

class ExternalIdentityRolesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
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
    
    // External Identity Roles are not configuration
    $this->setIsConfigurationTable(false);
    
    // Define associations
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('Types')
         ->setForeignKey('affiliation_type_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('affiliation_type');
    
    $this->hasMany('Addresses')
         ->setDependent(true);
    $this->hasMany('AdHocAttributes')
         ->setDependent(true);
    $this->hasMany('TelephoneNumbers')
         ->setDependent(true);
    $this->hasMany('HistoryRecords')
         ->setDependent(true);
    
    $this->setDisplayField('id');
    
    $this->setPrimaryLink('external_identity_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    
    $this->setEditContains([
      /*
      'Addresses',
      'AdHocAttributes',
      'TelephoneNumbers'*/
    ]);
    
    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
        'class' => 'StatusEnum'
      ],
      'affiliationTypes' => [
        'type' => 'type',
        'attribute' => 'PersonRoles.affiliation'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
// See also CFM-126
// XXX need to add couAdmin, eventually
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }
  
  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Person $entity Entity to generate display field for
   * @return string         Display field
   */
  
  public function generateDisplayField(\App\Model\Entity\ExternalIdentityRole $entity): string {
    // Try to find something renderable
    
    if(!empty($entity->title)) {
      return $entity->title;
    }
    
// XXX else affiliation type if set, else organization, else department
    
    return (string)$entity->id;
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
    
    $this->registerPrimaryKeyValidation($validator, $this->getPrimaryLinks());
    
    $validator->add('affiliation_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('affiliation_type_id');
    
    $this->registerStringValidation($validator, $schema, 'title', false);
    
    $this->registerStringValidation($validator, $schema, 'organization', false);
    
    $this->registerStringValidation($validator, $schema, 'department', false);
    
    $this->registerStringValidation($validator, $schema, 'manager_identifier', false);
    
    $this->registerStringValidation($validator, $schema, 'sponsor_identifier', false);
    
    $validator->add('valid_from', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('valid_from');
    
    $validator->add('valid_through', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('valid_through');
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', StatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    
    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');
    
    return $validator; 
  }
}