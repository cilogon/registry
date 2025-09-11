<?php
/**
 * COmanage Registry Addresses Table
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

use \App\Lib\Enum\LanguageEnum;
use \Cake\ORM\Table;
use \Cake\ORM\TableRegistry;
use \Cake\Validation\Validator;

class AddressesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionableTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TypeTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  // Default "out of the box" types for this model. Entries here should be
  // given a default localization in app/resources/locales/*/defaultType.po
  protected $defaultTypes = [
    'type' => [
      'campus',
      'home',
      'office',
      'postal'
    ]
  ];

  // Default permitted Fields. Used for the Attribute Collection
  private $permittedFields = ['locality', 'state', 'postal_code', 'country', 'street', 'room'];
    
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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);
    
    // Define associations
    $this->belongsTo('People');
    $this->belongsTo('PersonRoles');
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('ExternalIdentityRoles');
    $this->belongsTo('Types');
    $this->belongsTo('SourceAddresses')
         ->setClassName('Addresses')
         ->setForeignKey('source_address_id')
         ->setProperty('source_address');
    $this->hasMany('PipelinedAddresses')
         ->setClassName('Addresses')
         ->setForeignKey('source_address_id')
         ->setProperty('pipelined_address');
        
    $this->setDisplayField('street');
    
    $this->setPrimaryLink(['external_identity_id', 'external_identity_role_id', 'person_id', 'person_role_id']);
    $this->setRequiresCO(true);
    // Models that AcceptCoId should be explicitly added to AppController::beforeFilter()
    $this->setAcceptsCoId(true);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');
    $this->setAllowLookupPrimaryLink(['unfreeze']);
    $this->setEditContains(['ExternalIdentities', 'ExternalIdentityRoles', 'SourceAddresses']);
    
    $this->setAutoViewVars([
      'languages' => [
        'type' => 'enum',
        'class' => 'LanguageEnum'
      ],
      'types' => [
        'type' => 'type',
        'attribute' => 'Addresses.type'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'unfreeze' => ['platformAdmin', 'coAdmin'],
        'view' => ['platformAdmin', 'coAdmin', 'selfMember'],
      ],
      // Actions that are permitted on readonly entities (besides view)
      'readOnly' =>   ['unfreeze'],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin', 'selfMember'],
        'deleted' =>  ['platformAdmin', 'coAdmin']
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
   * Perform a keyword search.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $coId   CO ID to constrain search to
   * @param  string $q      String to search for
   * @param  int    $limit  Search limit
   * @return Array          Array of search results, as from find('all)
   */

  public function search(int $coId, string $q, int $limit) {
    // Tokenize $q on spaces
    $tokens = explode(" ", $q);

    // We take two loops through, the first time we only do a prefix search
    // (foo%). If that doesn't reach the search limit, we'll do an infix search
    // the second time around.

    $whereClause = [];

    foreach($tokens as $t) {
      $whereClause['AND'][] = [
        'OR' => [
          'LOWER(Addresses.street) LIKE' => '%' . strtolower($t) . '%'
        ]
      ];
    }

    return $this->find()
                ->where($whereClause)
                ->andWhere(['People.co_id' => $coId])
                ->orderBy(['Addresses.street'])
                ->limit($limit)
                ->contain([
                  'People' => 'PrimaryName',
                  'PersonRoles' => [
                    'People' => 'PrimaryName'
                  ]
                ])
                ->all();
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
    
    $requiredFields = $settings->address_required_fields_array();
    
    $this->registerPrimaryKeyValidation($validator, $this->getPrimaryLinks());
    
    // CO Settings determines if these fields are required
    $validator->add('street', [
      'filter'  => ['rule'     => ['validateInput'],
                    'provider' => 'table']
    ]);
    if(in_array('street', $requiredFields)) {
      $validator->notEmptyString('street');
    } else {
      $validator->allowEmptyString('street');
    }

    foreach(['locality', 'state', 'postal_code', 'country'] as $f) {
      $validator->add($f, [
        'size'    => ['rule'     => ['validateMaxLength', ['column' => $schema->getColumn($f)]],
                      'provider' => 'table'],
        'filter'  => ['rule'     => ['validateInput'],
                      'provider' => 'table']
      ]);
      if(in_array($f, $requiredFields)) {
        $validator->notEmptyString($f);
      } else {
        $validator->allowEmptyString($f);
      }
    }
    
    $this->registerStringValidation($validator, $schema, 'room', false);
    
    $this->registerStringValidation($validator, $schema, 'description', false);
    
    $validator->add('language', [
      'content' => ['rule' => ['inList', LanguageEnum::getConstValues()]]
    ]);
    $validator->allowEmptyString('language');
    
    $validator->add('type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('type_id');
    
    $validator->add('frozen', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('frozen');

    $validator->add('source_address_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('source_address_id');
    
    return $validator; 
  }

  /**
   * Get the hardcoded list of the Default Permitted Fields
   *
   * @since  COmanage Registry v5.0.0
   * @return  array  List of permitted fields
   */
  public function getPermittedFields(): array {
    return $this->permittedFields;
  }


  /**
   * Save attributes based on the provided data.
   *
   * This method saves the attributes associated with a person or a person role.
   *
   * @since  COmanage Registry v5.1.0
   * @param int $personId ID of the person
   * @param int|null $roleId ID of the person role (nullable)
   * @param string $parentModel Model to associate the attributes with ('Person' or 'PersonRole')
   * @param array $fields Array of attribute fields to save
   * @return bool                  True on successful save
   * @throws \InvalidArgumentException If required parameters are missing
   * @throws \Cake\ORM\Exception\PersistenceFailedException If the entity could not be saved
   */
  public function saveAttributeCollectorPetitionAttributes(int $personId, ?int $roleId, string $parentModel, array $fields): bool
  {
    $address = [];

    if($parentModel === 'Person') {
      if(empty($personId)) {
        throw new \InvalidArgumentException(__d('error', 'personId'));
      }
      $address['person_id'] = $personId;
    } elseif ($parentModel === 'PersonRole') {
      if(empty($roleId)) {
        throw new \InvalidArgumentException(__d('error', 'person_role_id'));
      }
      $address['person_role_id'] = $roleId;
    }

    foreach($fields as $fld) {
      $address[$fld->column_name] = $fld->value;
      $address['type_id'] = $fld->enrollment_attribute->attribute_type;
    }

    $this->saveOrFail($this->newEntity($address));

    return true;
  }
}