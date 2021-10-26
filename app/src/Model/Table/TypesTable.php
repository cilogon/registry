<?php
/**
 * COmanage Registry Types Table
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
use \App\Lib\Enum\EduPersonAffiliationEnum;
use \App\Lib\Enum\SuspendableStatusEnum;

class TypesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  // use \App\Lib\Traits\RulesTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
// XXX note not all models are implemented yet...
//     add note to JIRA for commented out models
//     - uncomment attribute here
//     - implement Table::defaultTypes
  protected $testVar = "123";

  protected $supportedAttributes = [
//    'Address.type',
//    'Department.type',
//    'PersonRole.affiliation',
//    'EmailAddress.type',
//    'Identifier.type',
    'Names.type',
//    'Organization.type',
//    'TelephoneNumber.type',
//    'Url.type'
  ];
  
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
    
    // Types are configuration
    $this->setIsConfigurationTable(true);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->hasMany('identifiers');
    $this->hasMany('Names');
// XXX add other MVEA models
    
    $this->setDisplayField('display_name');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setAllowUnkeyedPrimaryLink(['restore']);
    
    $this->setAutoViewVars([
      'attributes' => [
        'type' => 'array',
        'array' => $this->supportedAttributes
      ],
      'edupersonaffiliations' => [
        'type' => 'enum',
        'class' => 'EduPersonAffiliationEnum'
      ],
      'statuses' => [
        'type' => 'enum',
        'class' => 'SuspendableStatusEnum'
      ]
    ]);
  }
  
  /**
   * Add the default types for an attribute.
   *
   * @since  COmanage Registry v0.9.2
   * @param  int    $coId      CO ID
   * @param  string $attribute Attribute, of the form Model.attribute
   * @return bool              True on success
   * @throws InvalidArgumentException
   * @throws PersistenceFailedException
   */

  public function addDefault(int $coId, string $attribute) {
    // Make sure $attribute is valid
    
    if(!in_array($attribute, $this->supportedAttributes)) {
      throw new \InvalidArgumentException(__('registry.er.unknown', [$attribute]));
    }

    // Split $attribute
    $attr = explode('.', $attribute, 2);
    
    // We need the appropriate model for $attribute to manipulate the default types
    // $table = (eg) NamesTable
    $table = TableRegistry::getTableLocator()->get($attr[0]);
    
    // The current set of types for this model, of the form value => display_name
    $current = $table->availableTypes($coId, $attribute);
    
    // The default types for this model, of the same form
    $modelDefault = $table->defaultTypes($attr[1]);

    // Construct a set of arrays that we'll convert to entities to save
    $records = [];
    
    foreach($modelDefault as $value => $displayName) {
      if(!array_key_exists($value, $current)) {
        $records[] = [
          'co_id'         => $coId,
          'attribute'     => $attribute,
          'display_name'  => $displayName,
          'value'         => $value,
          'status'        => SuspendableStatusEnum::Active
        ];
      }
    }
    
    // Convert the arrays to entities
    $entities = $this->newEntities($records);

    // throws PersistenceFailedException on failure
    $this->saveManyOrFail($entities);
    
    return true;
  }

  /**
   * Add all default values for extended types for the specified CO.
   *
   * @since  COmanage Registry v0.9.2
   * @param  int  $coId CO ID
   * @return bool       True on success
   * @throws RuntimeException
   */

  public function addDefaults(int $coId) {
    foreach(array_values($this->supportedAttributes) as $t) {
      try {
        $this->addDefault($coId, $t);
      }
      catch(\Exception $e) {
        throw new \RuntimeException($e->getMessage());
      }
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
    // AR-Type-2 A Type cannot be deleted once used at least one time
    $rules->addDelete([$this, 'ruleTypeInUse'],
                      'typeInUse',
                      ['errorField' => 'type_id']);
    
    return $rules;
  }
  
  /**
   * Determine if this type is in use.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity Entity to be validated
   * @return boolean         true if the entity is in use, false otherwise
   */
  
  public function typeInUse($entity) {
    $attr = explode('.', $entity->attribute, 2);
    
    // Pull the table for this attribute, then see if there are any records
    // where the column matches the requested type ID
    
    $table = TableRegistry::getTableLocator()->get($attr[0]);
    
    // We include changelog-archived records for referential integrity... if a
    // record was created that references this type and then was subsequently
    // deleted, it is still considered "in use".
    
    $count = $table->find('all', ['archived' => true])
                   ->where([$attr[1]."_id" => $entity->id])
                   ->count();
    
    return $count != 0;
  }
  
  /**
   * Application Rule to determine if the requested type is in use.
   *
   * @since  COmanage Registyr v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleTypeInUse($entity, $options) {
    if($this->typeInUse($entity)) {
      return __('registry.er.Types.inuse', [$entity->value]);
    }
    
    return true;
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $validator->add(
      'co_id',
      'content',
      [ 'rule' => 'isInteger' ]
    );
    $validator->notEmpty('co_id');
    
    $validator->add(
      'attribute',
      'content',
      [ 'rule' => [ 'inList', $this->supportedAttributes ] ]
    );
    $validator->notEmpty('attribute');
    
    $validator->add(
      'value',
      'length',
      [ 'rule' => [ 'maxLength', 32 ] ]
    );
    $validator->add(
      'value',
      'content',
      [ 'rule' => [ 'custom', '/^[a-zA-Z0-9\-\.]+$/' ] ]
    );
    $validator->notEmpty('value');
    
    $validator->add(
      'display_name',
      'length',
      [ 'rule' => [ 'maxLength', 64 ] ]
    );
    $validator->add(
      'display_name',
      'content',
      [ 'rule'     => [ 'validateInput' ],
        'provider' => 'table' ]
    );
    $validator->notEmpty('display_name');
    
    $validator->add(
      'edupersonaffiliation',
      'content',
      [ 'rule' => [ 'inList', [ 
        EduPersonAffiliationEnum::Affiliate,
        EduPersonAffiliationEnum::Alum,
        EduPersonAffiliationEnum::Employee,
        EduPersonAffiliationEnum::Faculty,
        EduPersonAffiliationEnum::LibraryWalkIn,
        EduPersonAffiliationEnum::Member,
        EduPersonAffiliationEnum::Staff,
        EduPersonAffiliationEnum::Student
      ] ] ]
    );
    $validator->allowEmpty('edupersonaffiliation');
    
    $validator->add(
      'status',
      'content',
      [ 'rule' => [ 'inList', [ 
        SuspendableStatusEnum::Active,
        SuspendableStatusEnum::Suspended
      ] ] ]
    );
    $validator->notEmpty('status');
    
    return $validator; 
  }
}