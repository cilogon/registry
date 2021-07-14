<?php
/**
 * COmanage Registry Names Table
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

class NamesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
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
    
    // Names are not configuration
    $this->setIsConfigurationTable(false);
    
    // Define associations
    $this->belongsTo('CoPeople');
    $this->belongsTo('OrgIdentity');
    
// XXX can we make this a function (generateCn)?
    $this->setDisplayField('given');
    
    $this->setPrimaryLink('co_person_id');
    $this->setRequiresCO(true);
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    // One of CO Person ID or Org Identity ID is required
// XXX Test this via the API?
    $validator->add(
      'co_person_id',
      'content',
      [ 'rule' => 'isInteger' ]
    );
    $validator->notEmpty('co_person_id', null, function($context) {
      return empty($context['data']['org_identity_id']);
    });
    
    $validator->add(
      'org_identity_id',
      'content',
      [ 'rule' => 'isInteger' ]
    );
    $validator->notEmpty('org_identity_id', null, function($context) {
      return empty($context['data']['co_person_id']);
    });
    
    $validator->add(
      'honorific',
      'length',
      [ 'rule' => [ 'maxLength', 32 ] ]
    );
    $validator->add(
      'honorific',
      'content',
      [ 'rule'     => [ 'validateInput' ],
        'provider' => 'table' ]
    );
    $validator->allowEmpty('honorific');
    
    $validator->add(
      'given',
      'length',
      [ 'rule' => [ 'maxLength', 128 ] ]
    );
    $validator->add(
      'given',
      'content',
      [ 'rule'     => [ 'validateInput' ],
        'provider' => 'table' ]
    );
    $validator->notEmpty('given');
    
    $validator->add(
      'middle',
      'length',
      [ 'rule' => [ 'maxLength', 128 ] ]
    );
    $validator->add(
      'middle',
      'content',
      [ 'rule'     => [ 'validateInput' ],
        'provider' => 'table' ]
    );
    $validator->allowEmpty('middle');
    
    $validator->add(
      'family',
      'length',
      [ 'rule' => [ 'maxLength', 128 ] ]
    );
    $validator->add(
      'family',
      'content',
      [ 'rule'     => [ 'validateInput' ],
        'provider' => 'table' ]
    );
    $validator->allowEmpty('family');
    
    $validator->add(
      'suffix',
      'length',
      [ 'rule' => [ 'maxLength', 32 ] ]
    );
    $validator->add(
      'suffix',
      'content',
      [ 'rule'     => [ 'validateInput' ],
        'provider' => 'table' ]
    );
    $validator->allowEmpty('suffix');
    
// XXX need to do something to validate type (test via API)
    
    $validator->add(
      'language',
      'content',
      [ 'rule' => [ 'validateLanguage' ],
        'provider' => 'table' ]
    );
    $validator->allowEmpty('language');
    
    $validator->add(
      'primary_name',
      'content',
      [ 'rule' => [ 'boolean' ] ]
    );
    $validator->allowEmpty('primary_name');
    
    $validator->add(
      'source_name_id',
      'content',
      [ 'rule' => 'isInteger' ]
    );
    $validator->allowEmpty('source_name_id');
    
    return $validator; 
  }
}