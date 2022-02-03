<?php
/**
 * COmanage Registry Email Addresses Table
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

class EmailAddressesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TypeTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  // Default "out of the box" types for this model. Entries here should be
  // given a default localization in app/resources/locales/*/defaultType.po
  protected $defaultTypes = [
    'type' => [
      'delivery',
      'forwarding',
      'list',
      'official',
      'personal',
      'preferred',
      'recovery'
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
    
    // Identifiers are not configuration
    $this->setIsConfigurationTable(false);
    
    // Define associations
    $this->belongsTo('People');
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('Types');
    
    $this->setDisplayField('mail');
    
// XXX note primary link is external_identity_id when set...
    $this->setPrimaryLink('person_id');
    $this->setAllowLookupPrimaryLink(['primary']);
    $this->setRequiresCO(true);
    
    $this->setAutoViewVars([
      'types' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'primary' =>  ['platformAdmin', 'coAdmin'],
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
   * Callback after model save.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface  $event   Event
   * @param  EntityInterface $entity  Entity (ie: Co)
   * @param  ArrayObject     $options Save options
   * @return bool                     True on success
   */
    
  public function afterSave(\Cake\Event\EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $options): bool {
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
    // One of Person ID or External Identity ID is required
    $validator->add(
      'person_id',
      'content',
      [ 'rule' => 'isInteger' ]
    );
    $validator->notEmptyString('person_id', null, function($context) {
      return empty($context['data']['external_identity_id']);
    });
    
    $validator->add(
      'external_identity_id',
      'content',
      [ 'rule' => 'isInteger' ]
    );
    $validator->notEmptyString('external_identity_id', null, function($context) {
      return empty($context['data']['person_id']);
    });
    
    $validator->add(
      'mail',
      'length',
      [ 'rule' => [ 'maxLength', 256 ] ]
    );
    $validator->add(
      'mail',
      'content',
      [ 'rule' => [ 'email' ] ]
    );
    $validator->notEmptyString('mail');
    
    $validator->add(
      'type_id',
      'content',
      [ 'rule' => 'isInteger' ]
    );
    $validator->notEmptyString('type_id');
    
    $validator->add(
      'verified',
      'content',
      [ 'rule' => [ 'boolean' ] ]
    );
    $validator->allowEmptyString('verified');
    
    $validator->add(
      'description',
      'content',
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
      'source_email_address_id',
      'content',
      [ 'rule' => 'isInteger' ]
    );
    $validator->allowEmptyString('source_email_address_id');
    
    return $validator; 
  }
}