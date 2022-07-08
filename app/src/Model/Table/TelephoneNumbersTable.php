<?php
/**
 * COmanage Registry Telephone Numbers Table
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

use \Cake\ORM\Table;
use \Cake\ORM\TableRegistry;
use \Cake\Validation\Validator;

class TelephoneNumbersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TypeTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  
  // Default "out of the box" types for this model. Entries here should be
  // given a default localization in app/resources/locales/*/defaultType.po
  protected $defaultTypes = [
    'type' => [
      'campus',
      'fax',
      'home',
      'mobile',
      'office'
    ]
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
    
    // Telephone Numbers are not configuration
    $this->setIsConfigurationTable(false);
    
    // Define associations
    $this->belongsTo('People');
    $this->belongsTo('PersonRoles');
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('ExternalIdentityRoles');
    $this->belongsTo('Types');
    
    $this->setDisplayField('number');
    
    $this->setPrimaryLink(['external_identity_id', 'external_identity_role_id', 'person_id', 'person_role_id']);
    $this->setRequiresCO(true);
    $this->setAcceptsCoId(true);
    
    $this->setAutoViewVars([
      'types' => [
        'type' => 'type',
        'attribute' => 'TelephoneNumbers.type'
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
    
  public function localAfterSave(\Cake\Event\EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $options): bool {
    $this->recordHistory($entity);
    
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
    
    // We need the current CO ID to dynamically set validation rules according
    // to CoSettings.

    if(!$this->curCoId) {
      throw new \InvalidArgumentException(__d('error', 'coid'));
    }

    $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');

    $settings = $CoSettings->find()->where(['co_id' => $this->curCoId])->firstOrFail();

    $permittedFields = $settings->telephone_number_permitted_fields_array();
    
    $this->registerPrimaryKeyValidation($validator, $this->getPrimaryLinks());
    
    foreach(['country_code', 'area_code', 'number', 'extension'] as $f) {
      if(in_array($f, $permittedFields)) {
        $this->registerStringValidation($validator, $schema, $f, ($f == 'number'));
      }
    }
    
    $this->registerStringValidation($validator, $schema, 'description', false);
    
    $validator->add('type_id', [
      'content' => ['rule'     => 'isInteger']
    ]);
    $validator->notEmptyString('type_id');
    
    $validator->add('source_telephone_number_id', [
      'content' => [ 'rule'    => 'isInteger' ]
    ]);
    $validator->allowEmptyString('source_telephone_number_id');
    
    return $validator; 
  }
}