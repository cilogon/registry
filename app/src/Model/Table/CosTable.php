<?php
/**
 * COmanage Registry Cos Table
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
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use \App\Lib\Enum\TemplateableStatusEnum;

class CosTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Timestamp');
 
    // COs are configuration
    $this->setIsConfigurationTable(true);
    
    // Define associations
    
    $this->hasMany('ApiUsers')
         ->setDependent(true);
    $this->hasMany('Cous')
         ->setDependent(true);
    $this->hasMany('Dashboards')
         ->setDependent(true);
    $this->hasMany('People')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Types')
         ->setDependent(true);
    
    $this->hasOne('CoSettings')
         ->setDependent(true);
    
    $this->setDisplayField('name');
    
    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
        'class' => 'TemplateableStatusEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>    ['platformAdmin'],
        'duplicate' => ['platformAdmin'],
        'edit' =>      ['platformAdmin'],
        'view' =>      ['platformAdmin']
      ],
      // Actions that are permitted on readonly entities (besides view)
      'readOnly' =>    ['duplicate'],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>       ['platformAdmin'],
        'index' =>     ['platformAdmin'],
        'select' =>    ['authenticatedUser']
      ]
    ]);
  }
  
  /**
   * Callback after model save.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface  $event   Event
   * @param  EntityInterface $entity  Entity (ie: Co)
   * @param  ArrayObject     $options Save options
   * @return bool                     True on success
   */

  public function afterSave(\Cake\Event\EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $options) {
    if($entity->isNew() && !empty($entity->id)) {
      // Run setup for new CO
      
      $this->setup($entity->id);
    }

    return true;
  }
  
  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-CO-2 The COmanage CO cannot be renamed or deleted
    // Note add() sets the rule for create+update, where addDelete() sets the rule additionally for delete
    $rules->add([$this, 'ruleIsCOmanageCO'],
                'isCOmanageCO',
                ['errorField' => 'name']);
    
    $rules->addDelete([$this, 'ruleIsCOmanageCO'],
                      'isCOmanageCO',
                      ['errorField' => 'name']);
                
    // AR-CO-3 Two COs cannot share the same name
// XXX CO-1736 In general, these checks should be case insensitive
// (ie: I shouldn't be able to create a CO called "comanage", similarly COUs etc)
// Also, with CO-1845 maybe unique ignores non-alphanumeric
    $rules->add($rules->isUnique(['name'], __d('error', 'exists', [__d('controller', 'Cos', [1])])));
    
    // AR-CO-5 A CO cannot be deleted if it is in Active status
    // This basically requires two steps to delete a CO (set to Suspended),
    // reducing the likelihood of accidentally deleting a CO.
    $rules->addDelete([$this, 'ruleIsActive'],
                      'isActive',
                      ['errorField' => 'status']);
    
    return $rules;
  }
  
  /*
  public function duplicate($id) {
    // XXX document AR-CO-4, use TableMetaTrait to determine which tables are configuration
  }*/
  
  /**
   * Find the COmanage CO.
   *
   * @since  COmanage Registry v5.0.0
   * @param  \Cake\ORM\Query $query Query
   * @return \Cake\ORM\Query        Query
   */
  
  public function findCOmanageCO(Query $query): Query {
    return $query->where(['lower(name)' => 'comanage']);
  }
  
  /**
   * Determine if this is a Read Only record.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity Cake Entity
   * @return boolean         true if the entity is read only, false otherwise
   */
  
  public function isReadOnly($entity): bool {
    // The COmanage CO is read only
    
    return $entity->isCOmanageCO();
  }

  /**
   * Application Rule to determine if the current entity is the COmanage CO.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleIsCOmanageCO($entity, $options): bool {
    // We want negative logic since we want to fail if we're editing the COmanage CO
    if($entity->isCOmanageCO()) {
      return __d('error', 'edit.comanage');
    }
    
    return true;
  }
  
  /**
   * Application Rule to determine if the current entity is not Active.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleIsActive($entity, $options): bool {
    // We want negative logic since we want to fail if the record is Active
    if($entity->status == TemplateableStatusEnum::Active) {
      return __d('error', 'delete.active');
    }
    
    return true;
  }
  
  /**
   * Perform initial setup for a CO.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int  $id CO ID
   * @return bool     True on success
   */
  
  public function setup(int $id): bool {
    $Types = TableRegistry::getTableLocator()->get('Types');
    
    // AR-Type-1 Set up the default values for extended types
    $Types->addDefaults($id);

    // Create the default groups
//    $this->CoGroup->addDefaults($coId);

    // Set up the default settings
    $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');
    
    $CoSettings->addDefaults($id);
    
    return true;
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return $validator           Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $validator->add(
      'name',
      'length',
      [ 'rule' => [ 'maxLength', 128 ] ]
    );
    $validator->add(
      'name',
      'content',
      [ 'rule'     => [ 'validateInput' ],
        'provider' => 'table' ]
    );
    $validator->notEmptyString('name');
    
    $validator->add(
      'description',
      'length',
      [ 'rule' => [ 'maxLength', 128 ] ]
    );
    $validator->add(
      'description',
      'content',
      [ 'rule'     => [ 'validateInput' ],
        'provider' => 'table' ]
    );
    $validator->allowEmptyString('description');
    
    $validator->add(
      'status',
      'content',
      [ 'rule' => [ 'inList', TemplateableStatusEnum::getConstValues() ] ]
    );
    $validator->notEmptyString('status');
    
    return $validator; 
  }
}