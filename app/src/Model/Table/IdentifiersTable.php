<?php
/**
 * COmanage Registry Identifiers Table
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
use \App\Lib\Enum\SuspendableStatusEnum;

class IdentifiersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TypeTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  
  // Default "out of the box" types for this model. Entries here should be
  // given a default localization in app/resources/locales/*/defaultType.po
  protected $defaultTypes = [
    'type' => [
      'badge',
      'enterprise',
      'eppn',
      'eptid',
      'epuid',
      'gid',
      'mail',
      'national',
      'network',
      'oidcsub',
      'openid',
      'orcid',
      'provisioningtarget',
      'reference',
      'pairwiseid',
      'subjectid',
      'sorid',
      'uid'
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
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('Groups');
    $this->belongsTo('People');
    $this->belongsTo('Types');
    
    $this->setDisplayField('identifier');
    
    $this->setPrimaryLink(['external_identity_id', 'group_id', 'person_id']);
    $this->setAllowLookupPrimaryLink(['primary']);
    $this->setRequiresCO(true);
    
    $this->setAutoViewVars([
      'types' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'statuses' => [
        'type' => 'enum',
        'class' => 'TemplateableStatusEnum'
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
      ],
      // Related models whose permissions we'll need, typically for table views
      'related' => [
        'AuthenticationEvents'
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
    
    $this->registerPrimaryKeyValidation($validator, $this->getPrimaryLinks());
    
    $this->registerStringValidation($validator, $schema, 'identifier', true);
    $validator->add('identifier', [
      // Identifier must have at least one non-space character in order to avoid
      // errors (eg: with provisioning ldap)
      'content' => ['rule'    => ['notBlank'],
                    'message' => __d('error', 'input.blank')]
    ]);
    
    $validator->add('type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('type_id');
    
    $validator->add('login', [
      'content' => ['rule' => ['boolean']]
    ]);
    
    // AR-Identifier-1 Login Identifiers can only be attached to People
    $validator->add('login', 'loginPersonIdentifier', [
      'rule' => function ($value, array $context) {
        if($value && empty($context['data']['person_id'])) {
          return __d('error', 'Identifiers.login');
        }
        
        return true;
      }
    ]);
    
    $validator->allowEmptyString('login');
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    
    $validator->add('source_identifier_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('source_identifier_id');
    
    return $validator; 
  }
}