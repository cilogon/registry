<?php
/**
 * COmanage Registry Departments Table
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

class DepartmentsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TypeTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  // Default "out of the box" types for this model. Entries here should be
  // given a default localization in app/resources/locales/*/defaultType.po
  protected $defaultTypes = [
    'type' => [
      'department',
      'researchinstitute',
      'vo'
    ]
  ];

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.3.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Primary);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('Cous');
    $this->belongsTo('Types');

    $this->hasMany('Addresses')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('AdHocAttributes')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->belongsTo('AdministrativeGroups')
         ->setClassName('Groups')
         ->setForeignKey('administrative_group_id')
         ->setProperty('administrative_group');
    $this->hasMany('Contacts')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('EmailAddresses')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->belongsTo('LeadershipGroups')
         ->setClassName('Groups')
         ->setForeignKey('leadership_group_id')
         ->setProperty('leadership_group');
    $this->hasMany('Identifiers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->belongsTo('SupportGroups')
         ->setClassName('Groups')
         ->setForeignKey('support_group_id')
         ->setProperty('support_group');
    $this->hasMany('TelephoneNumbers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Urls')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->setDisplayField('name');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);

    $this->setEditContains([
      'Addresses',
      'AdHocAttributes',
      'Contacts',
      'EmailAddresses',
      'Identifiers',
      'TelephoneNumbers',
      'Urls'
    ]);
    $this->setViewContains([
      'Addresses',
      'AdHocAttributes',
      'Contacts',
      'EmailAddresses',
      'Identifiers',
      'TelephoneNumbers',
      'Urls'
    ]);

    $this->setAutoViewVars([
      'administrativeGroups' => [
        'type'  => 'select',
        'model' => 'Groups'
      ],
      'cous' => [
        'type' => 'select',
        'model' => 'Cous'
      ],
      'leadershipGroups' => [
        'type'  => 'select',
        'model' => 'Groups'
      ],
      'supportGroups' => [
        'type'  => 'select',
        'model' => 'Groups'
      ],
      'types' => [
        'type' => 'type',
        'attribute' => 'Departments.type'
      ]
    ]);

    // Enable the Model Specific REST API for this Table
    $this->enableMsrApi();
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'configure' =>  ['platformAdmin', 'coAdmin'],
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Request provisioning.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  int                      $id                   This table's entity ID to provision
   * @param  ProvisioningContextEnum  $context              Context in which provisioning is being requested
   * @param  int                      $provisioningTargetId If set, the Provisioning Target ID to request provisioning for (otherwise all)
   * @param  Job                      $job                  If called from a Job, the current Job entity
   * @param  array                    $passThroughData      Additional data to merge into the marshalled provisioning data
   * @throws InvalidArgumentException
   */

  public function requestProvisioning(
    int     $id,
    string  $context,
    ?int    $provisioningTargetId=null,
    ?Job    $job=null,
    ?array  $passThroughData=null
  ) {
    return;
  }
  
  /**
   * Perform a keyword search.
   *
   * @since  COmanage Registry v5.3.0
   * @param  int    $coId   CO ID to constrain search to
   * @param  string $q      String to search for
   * @param  int    $limit  Search limit
   * @return Array          Array of search results, as from find('all')
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
          'LOWER(Departments.name) LIKE' => '%' . strtolower($t) . '%'
        ]
      ];
    }

    return $this->find()
                ->where($whereClause)
                ->andWhere(['Departments.co_id' => $coId])
                ->orderBy(['Departments.name'])
                ->limit($limit)
                ->all();
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
    
    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');
    
    $validator->add('cou_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('cou_id');

    $this->registerStringValidation($validator, $schema, 'name', true);

    $validator->add('type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('type_id');

    $this->registerStringValidation($validator, $schema, 'description', false);

    $this->registerStringValidation($validator, $schema, 'saml_scope', false);

    $this->registerStringValidation($validator, $schema, 'logo_url', false);

    $validator->add('leadership_group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('leadership_group_id');

    $validator->add('administrative_group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('administrative_group_id');

    $validator->add('support_group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('support_group_id');
    
    return $validator; 
  }
}