<?php
/**
 * COmanage Registry People Table
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

class PeopleTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\RulesTrait;
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
    
    // CO People are not configuration
    $this->setIsConfigurationTable(false);
    
    // Define associations
    $this->belongsTo('Cos');
    
    $this->hasOne('PrimaryName', [
           'className' => 'Names'
         ])
         ->setConditions(['PrimaryName.primary_name' => true]);
    $this->hasMany('Names')
         ->setDependent(true);
    
// XXX can we change this to Name?
    $this->setDisplayField('id');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['canvas']);
    
// XXX does some of this stuff really belong in the controller?
    $this->setEditContains(['PrimaryName']);
    $this->setIndexContains(['PrimaryName']);
    
    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
        'class' => 'StatusEnum'
      ],
      'types' => [
        'type' => 'type',
        'where' => ['attribute' => 'Name.type']
      ]
    ]);
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
      'status',
      'content',
      [ 'rule' => [ 'inList', StatusEnum::getConstValues() ]]
/*      [ 'rule' => [ 'inList', [ 
        TemplateableStatusEnum::Active,
        TemplateableStatusEnum::Suspended,
        TemplateableStatusEnum::Template
      ] ] ]*/
    );
    $validator->notEmpty('status');
    
    $validator->add(
      'timezone',
      'content',
      [ 'rule' => [ 'validateTimeZone' ],
        'provider' => 'table' ]
    );
    $validator->allowEmpty('timezone');
    
    $validator->add(
      'date_of_birth',
      'content',
      [ 'rule' => 'date' ]
    );
    $validator->allowEmpty('date_of_birth');
    
    return $validator; 
  }
}