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

use Cake\Event\EventInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use \App\Lib\Enum\SuspendableStatusEnum;

class IdentifiersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);
    
    // Define associations
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('Groups');
    $this->belongsTo('People');
    $this->belongsTo('ProvisioningTargets');
    $this->belongsTo('Types');
    $this->belongsTo('SourceIdentifiers')
         ->setClassName('Identifiers')
         ->setForeignKey('source_identifier_id')
         ->setProperty('source_identifier');

    $this->setDisplayField('identifier');
    
    $this->setPrimaryLink(['external_identity_id', 'group_id', 'person_id']);
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');
    $this->setAllowLookupPrimaryLink(['unfreeze']);
    $this->setEditContains(['ExternalIdentities', 'SourceIdentifiers']);

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
        'unfreeze' => ['platformAdmin', 'coAdmin'],
        'view' => ['platformAdmin', 'coAdmin', 'selfMember'],
      ],
      // Actions that are permitted on readonly entities (besides view)
      'readOnly' =>   ['unfreeze'],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
        'deleted' =>  ['platformAdmin', 'coAdmin']
      ],
      // Related models whose permissions we'll need, typically for table views
      'related' => [
        'table' => [
          'AuthenticationEvents'
        ]
      ]
    ]);
  }
  
  /**
   * Callback before data is marshaled into an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface  $event   beforeMarshal event
   * @param  ArrayObject     $data    Entity data
   * @param  ArrayObject     $options Callback options
   */

  public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options)
  {
    if(empty($data['status'])) {
      // Set a default status of Active if not otherwise set (eg: via EIS/Pipelines)
      $data['status'] = SuspendableStatusEnum::Active;
    }
  }

  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */

  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-Identifier-2 An Identifier must be unique for its Type and Entity (Person
    // or Group) within the CO.
    $rules->add([$this, 'ruleUniqueIdentifier'],
                'uniqueIdentifier',
                ['errorField' => 'identifier']);

    return $rules;
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
   * Look up a Person ID from an identifier and identifier type ID.
   * Only active Identifiers can be used for lookups.
   *
   * @param   int       $typeId      Identifier Type ID
   * @param   string    $identifier  Identifier
   *
   * @return int                Person ID
   * @since  COmanage Registry v5.0.0
   */

  public function lookupPerson(int $typeId, string $identifier): int {
    // Note this function signature is intentionally the same as
    // EmailAddresses::lookupPerson()
    $id = $this->find()
               ->where([
                'identifier'  => $identifier,
                'type_id'     => $typeId,
                'status'      => SuspendableStatusEnum::Active,
                'person_id IS NOT NULL'
               ])
               ->firstOrFail();

    return $id->person_id;
  }

  /**
   * Look up a Person from a login Identifier within a CO. Because login Identifiers
   * can be of any type, no type ID is required.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $coId       CO ID
   * @param  string $identifier Identifier
   * @return int                Person ID
   * @throws Cake\Datasource\Exception\RecordNotFoundException
   */

  public function lookupPersonByLogin(int $coId, string $identifier): int {
    $id = $this->find()
               ->where([
                 'Identifiers.identifier'  => $identifier,
                 'Identifiers.login'       => true,
                 'Identifiers.status'      => SuspendableStatusEnum::Active,
                 'Identifiers.person_id IS NOT NULL'
               ])
               ->matching('People', function ($q) use($coId) {
                 return $q->where(['People.co_id' => $coId]);
               })
               ->firstOrFail();

    return $id->person_id;
  }
  
  /**
   * Application Rule to determine if an Identifier is already in use.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */

  public function ruleUniqueIdentifier($entity, $options) {
    // Uniqueness constraints only apply to People and Groups

    // In v4 we created a txn to ensure consistency, but it looks like Cake actually
    // starts a transaction, so it appears we don't need to do that here.

    if(!empty($entity->person_id) || !empty($entity->group_id)) {
      if($entity->isNew() 
         || $entity->isDirty('identifier') 
         || $entity->isDirty('type_id')) {
        // We need the Type configuration to see if uniqueness is case insensitive
        $type = $this->Types->get($entity->type_id);

        // Note we specifically do NOT check status, since a Suspended Identifier
        // will still prevent duplicate assignment. (AR-Identifier-3)
        $whereClause = [
          // type_id will imply CO ID, so we don't need to check it explicitly
          'type_id'     => $entity->type_id,
          // We ignore any Identifier from an EIS when determining uniqueness
          'source_identifier_id IS NULL'
        ];

        if(isset($type->case_insensitive) && $type->case_insensitive) {
          $whereClause['LOWER(identifier)'] = strtolower($entity->identifier);
        } else {
          $whereClause['identifier'] = $entity->identifier;
        }

        // We need to only check Identifiers attached to the same type of Entity
        if(!empty($entity->person_id)) {
          $whereClause[] = 'person_id IS NOT NULL';
        } elseif(!empty($entity->group_id)) {
          $whereClause[] = 'group_id IS NOT NULL';
        }

        $identifier = $this->find()
                           ->where($whereClause)
                           ->epilog('FOR UPDATE')
                           ->first();
        
        if(!empty($identifier)) {
          $inusect = !empty($identifier->person_id) ? __d('controller', 'People', 1) : __d('controller', 'Groups', 1);
          $inuseid = !empty($identifier->person_id) ? $identifier->person_id : $identifier->group_id;

          // If we fail in the middle of Identifier Assignment this returned message
          // will get lost/superceded by a rollback error
          $this->llog('rule', "AR-Identifier-2 Identifier " . $identifier->identifier . " is already in use on $inusect ID $inuseid");

          return __d('error', 
                     'Identifiers.exists',
                     [$inusect, $inuseid]);
        }
      }
    }

    return true;
  }

  /*
   * Lookup a Person ID from a login identifier. Only active Identifiers can
   * be used for lookups.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $identifier Identifier
   * @param  int    $coId       CO ID
   * @return int                Person ID or null
   */

  public function lookupPersonForLogin(string $identifier, int $coId): ?int {
    $id = $this->find()
               ->where([
                'Identifiers.identifier'  => $identifier,
                'Identifiers.status'      => SuspendableStatusEnum::Active,
                'Identifiers.login'       => true,
                'Identifiers.person_id IS NOT NULL'
               ])
               ->matching('People', function ($q) use ($coId) {
                return $q->where(['People.co_id' => $coId]);
               })
               ->firstOrFail();

    return $id->person_id ?? null;
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
    return $this->find()
                ->where([
                  'Identifiers.identifier' => $q,
                  'OR' => [
                    'People.co_id' => $coId,
                    'Groups.co_id' => $coId
                  ]
                ])
                ->limit($limit)
                ->contain(['People' => 'PrimaryName', 'Groups'])
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
    
    $validator->add('frozen', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('frozen');

    $validator->add('source_identifier_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('source_identifier_id');
    
    return $validator; 
  }
}