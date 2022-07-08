<?php
/**
 * COmanage Registry External Identities Table
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

class ExternalIdentitiesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
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
    
    // External Identities are not configuration
    $this->setIsConfigurationTable(false);
    
    // Define associations
    $this->belongsTo('People');
    
    $this->hasOne('PrimaryName')
         ->setClassName('Names')
         ->setConditions(['PrimaryName.primary_name' => true]);
    $this->hasMany('Names')
         ->setDependent(true);
    $this->hasMany('Addresses')
         ->setDependent(true);
    $this->hasMany('AdHocAttributes')
         ->setDependent(true);
    $this->hasMany('EmailAddresses')
         ->setDependent(true);
    $this->hasMany('ExternalIdentityRoles')
         ->setDependent(true);
    $this->hasMany('HistoryRecords')
         ->setDependent(true);
    $this->hasMany('Identifiers')
         ->setDependent(true);
    $this->hasMany('TelephoneNumbers')
         ->setDependent(true);
    $this->hasMany('Urls')
         ->setDependent(true);
    
    $this->setDisplayField('id');
    
    $this->setPrimaryLink('person_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    
// XXX does some of this stuff really belong in the controller?
    $this->setEditContains([
      'PrimaryName',
/*      'Addresses',
      'AdHocAttributes',
      'EmailAddresses',
      'Identifiers',
      'Names',
      'PersonRoles',
      'TelephoneNumbers',
      'Urls'*/
    ]);
    $this->setIndexContains(['PrimaryName']);

    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
// XXX maybe this (and EIRoles) should be SuspendableStatusEnum?
        'class' => 'StatusEnum'
      ]
    ]);
  }
  
  
  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentity $entity Entity to generate display field for
   * @return string                   Display field
   */
  
  public function generateDisplayField(\App\Model\Entity\ExternalIdentity $entity): string {
    if(empty($entity->primary_name)) {
      throw new \InvalidArgumentException(__d('error', 'Names.primary_name'));
    }
    
    return $entity->primary_name->full_name;
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
    
    $validator->add('status', [
// XXX what to do about the sync status?
      'content' => ['rule' => ['inList', StatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    
    $validator->add('date_of_birth', [
      'content' => ['rule' => 'date']
    ]);
    $validator->allowEmptyString('date_of_birth');
    
    return $validator; 
  }
}