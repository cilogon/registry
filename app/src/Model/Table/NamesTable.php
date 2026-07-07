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

use Cake\Collection\Collection;
use Cake\Utility\Hash;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\LanguageEnum;
use \Cake\Event\EventInterface;
use \Cake\ORM\Query;
use \Cake\ORM\RulesChecker;
use \Cake\ORM\Table;
use \Cake\ORM\TableRegistry;
use \Cake\Validation\Validator;

class NamesTable extends Table {
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
      'alternate',
      'author',
      'fka',
      'official',
      'preferred'
    ]
  ];
    
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Normalization');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);
    
    // Define associations
    $this->belongsTo('People');
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('Types');
    $this->belongsTo('SourceNames')
         ->setClassName('Names')
         ->setForeignKey('source_name_id')
         ->setProperty('source_name');
    $this->hasMany('PipelinedNames')
         ->setClassName('Names')
         ->setForeignKey('source_name_id')
         ->setProperty('pipelined_name');

    $this->setDisplayField('full_name');
    
    $this->setPrimaryLink(['external_identity_id', 'person_id']);
    $this->setAllowLookupPrimaryLink(['primary', 'unfreeze']);
    $this->setRequiresCO(true);
    // Models that AcceptCoId should be explicitly added to AppController::beforeFilter()
    $this->setAcceptsCoId(true);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');
    $this->setEditContains(['ExternalIdentities', 'SourceNames']);

    $this->setAutoViewVars([
      'languages' => [
        'type' => 'enum',
        'class' => 'LanguageEnum'
      ],
      'types' => [
        'type' => 'type',
        'attribute' => 'Names.type'
      ]
    ]);

    $this->setNormalizableFields([
      'CoreNormalizer.CaseMixers' => [
        'honorific',
        'given',
        'middle',
        'family',
        'suffix'
      ],
      'CoreNormalizer.WhitespaceTrimmers' => [
        'honorific',
        'given',
        'middle',
        'family',
        'suffix'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'primary' =>  ['platformAdmin', 'coAdmin'],
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
   * Callback before data is marshaled into an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface  $event   beforeMarshal event
   * @param  ArrayObject     $data    Entity data
   * @param  ArrayObject     $options Callback options
   */

  public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options)
  {
    if(!empty($data['source_name_id'])) {
      // Source records may not assert primary name on the Person copy.
// XXX this implies an EIS name cannot be a primary name - document as an AR 
      $data['primary_name'] = false;
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
    // AR-Name-4 Each Person or ExternalIdentity must have at least one name at
    // all times.
    $rules->addDelete([$this, 'ruleMinimumOneName'],
                      'minimumOneName',
                      // This rule is really an entity rule, not a field rule,
                      // but cake won't pass the error without a specific field
                      ['errorField' => 'id']);
    
    // AR-Name-1 The Primary Name cannot be deleted.
    $rules->addDelete([$this, 'rulePrimaryNameDelete'],
                      'primaryNameDelete',
                      ['errorField' => 'primary_name']);
    
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
    
    // AR-Name-1 A Person must have exactly one Primary Name at all times.
    // To enforce this, if the current $entity is flagged Primary Name, AND
    // the current entity is new or was not previously the Primary Name, we look
    // for any other names on the same Person or External Identity that are
    // flagged Primary and unset them.
    
    if($entity->primary_name) {
      if($entity->isNew() || !$entity->getOriginal('primary_name')) {
        // We either have a brand new name flagged as primary, or a previously
        // existing name that has been updated to be primary. Unset any other primary_name.
        
        $where = array_merge(
          $entity->whereClause(),
          [
            'id IS NOT' => $entity->id,
            'primary_name' => true,
          ]
        );
        
        // We can use the ORM here since we are unsetting primary_name, which
        // means we won't get into an infinite callback loop. (Meanwhile, we do
        // want other callbacks, in particular ChangelogBehavior, to run.)
        
        $query = $this->find('all')->where($where);
        
        foreach($query->all() as $obj) {
          $obj->primary_name = false;
          $this->save($obj);
        }
      }
      
      $this->recordHistory($entity, ActionEnum::NamePrimary, __d('result', 'Names.primary_name'));
    }
    
    return true;
  }
  
  /**
   * Obtain the primary name entity for a person.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $id         Record ID (person_id or external_identity_id, NOT name_id)
   * @param  string $recordType Type of record to find primary name for, 'person' or 'external_identity'
   * @param  array  $options
   * @return Name               Name Entity
   */
  
  public function primaryName(int $id, string $recordType='person', array $options = []) {
    $query = empty($options) ? $this->find() : $this->find('all', ...$options);
    if($recordType == 'person') {
      // Return the Primary Name

      return $query->where(['person_id' => $id,
                          'primary_name' => true])
                   ->firstOrFail();
    } else {
      // Return the first name, whatever it is

      return $query->where(['external_identity_id' => $id])
                   ->firstOrFail();
    }
  }

  /**
   * Application Rule to determine if there is at least one Name associated
   * with the Person.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleMinimumOneName($entity, $options) {
    $count = $this->find()->where($entity->whereClause())->count();
    
    if($count == 1) {
      $this->llog(
        level: 'error',
        msg: "AR-Name-4 Each Person or ExternalIdentity must have at least one name at all times",
        id: $entity->id
      );
      return __d('error', 'Names.minimum');
    }
    
    return true;
  }

  /**
   * Application Rule to determine if the Primary Name is trying to be deleted.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function rulePrimaryNameDelete($entity, $options) {
    if($entity->primary_name) {
      $this->llog(
        level: 'error', 
        msg: "AR-Name-1 The Primary Name cannot be deleted",
        id: $entity->id
      );
      return __d('error', 'Names.primary_name.del');
    }
    
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

    $ret = array();

    // We take two loops through, the first time we only do a prefix search
    // (foo%). If that doesn't reach the search limit, we'll do an infix search
    // the second time around.

    $whereClause = [];

    foreach($tokens as $t) {
      $whereClause[1]['AND'][] = [
        'OR' => [
          'LOWER(Names.given) LIKE' => strtolower($t) . '%',
          'LOWER(Names.middle) LIKE' => strtolower($t) . '%',
          'LOWER(Names.family) LIKE' => strtolower($t) . '%'
        ]
      ];

      $whereClause[2]['AND'][] = [
        'OR' => [
          'LOWER(Names.given) LIKE' => '%' . strtolower($t) . '%',
          'LOWER(Names.middle) LIKE' => '%' . strtolower($t) . '%',
          'LOWER(Names.family) LIKE' => '%' . strtolower($t) . '%'
        ]
      ];
    }

    $results = $this->find()
                    ->where($whereClause[1])
                    ->andWhere(['People.co_id' => $coId])
                    ->orderBy(['Names.family', 'Names.given', 'Names.middle'])
                    ->limit($limit)
                    ->contain(['People' => 'PrimaryName'])
                    ->all();
    
    if($results->count() < $limit) {
      $results2 = $this->find()
                       ->where($whereClause[2])
                       ->andWhere(['People.co_id' => $coId])
                       ->orderBy(['Names.family', 'Names.given', 'Names.middle'])
                       ->limit($limit - $results->count())
                       ->contain(['People' => 'PrimaryName'])
                       ->all();
      
      $results = $results->append($results2);
    }

    return $results;
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
    
    $permittedFields = $settings->name_permitted_fields_array();
    $requiredFields = $settings->name_required_fields_array();
    
    $this->registerPrimaryKeyValidation($validator, $this->getPrimaryLinks());
    
    foreach(['honorific', 'given', 'middle', 'family', 'suffix'] as $f) {
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
    
    $validator->add('type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('type_id');
    
    $validator->add('language', [
      'content' => ['rule' => ['inList', LanguageEnum::getConstValues()]]
    ]);
    $validator->allowEmptyString('language');
    
    $validator->add('primary_name', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('primary_name');
    
    $this->registerStringValidation($validator, $schema, 'display_name', false);
    
    $validator->add('frozen', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('frozen');

    $validator->add('source_name_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('source_name_id');
    
    return $validator; 
  }


  /**
   * Save attributes for a Person entity.
   *
   * @param int $personId ID of the person for whom attributes are being saved.
   * @param int|null $roleId ID of the associated role (if any).
   * @param string  $parentModel Parent model Name.
   * @param array $fields Array of fields and their values to be saved.
   * @return bool                Returns true on successful save, throws exception otherwise.
   * @throws \Cake\ORM\Exception\PersistenceFailedException If the entity could not be saved.
   */
  public function saveAttributeCollectorPetitionAttributes(int $personId, ?int $roleId, string $parentModel, array $fields): bool
  {
    $name = [
      'person_id'     => $personId,
      // Set this to true and delegate the confirmation to the localAfterSave callback
      'primary_name'  => true
    ];

    foreach($fields as $fld) {
      $name[$fld->column_name] = $fld->value;
      $name['type_id'] = $fld->enrollment_attribute->attribute_type;
      $name['language'] = $fld->enrollment_attribute->attribute_language;
    }

    $this->saveOrFail($this->newEntity($name));
    return true;
  }
}
