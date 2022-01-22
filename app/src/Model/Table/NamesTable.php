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
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use \App\Lib\Enum\LanguageEnum;

class NamesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
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
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    // Names are not configuration
    $this->setIsConfigurationTable(false);
    
    // Define associations
    $this->belongsTo('People');
    $this->belongsTo('ExternalIdentity');
    $this->belongsTo('Types');
    
    $this->setDisplayField('full_name');
    
// XXX note primary link is external_identity_id when set...
    $this->setPrimaryLink('person_id');
    $this->setAllowLookupPrimaryLink(['primary']);
    $this->setRequiresCO(true);
    $this->setAcceptsCoId(true);
    
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
    // AR-Name-1 A Person must have exactly one Primary Name at all times.
    // To enforce this, if the current $entity is flagged Primary Name, AND
    // the current entity is new or was not previously the Primary Name, we look
    // for any other names on the same Person or External Identity that are
    // flagged Primary and unset them.
    
    if($entity->primary_name
       // If we have a parent, we're creating a changelog archive, which we don't want to modify
       && !$entity->name_id) {
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
    // AR-Name-4 Each Person or ExternalIdentity must have at least one name at
    // all times.
    $rules->addDelete([$this, 'ruleMinimumOneName'],
                      'minimumOneName',
                      // This rule is really an entity rule, not a field rule,
                      // but cake won't pass the error without a specific field
                      ['errorField' => 'id']);
    
    return $rules;
  }
  
  /**
   * Determine if this is a Read Only record.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity Cake Entity
   * @return boolean         true if the entity is read only, false otherwise
   */
// XXX this should move to the entity directly

  public function isReadOnly($entity) {
    // Names pipelined from an EIS are read only

    return !empty($entity->source_name_id);
  }
  
  /**
   * Obtain the primary name entity for a person.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int $personId Person ID
   * @return Name          Name Entity
   */
  
  public function primaryName(int $personId) {
    return $this->find()
                ->where(['person_id' => $personId,
                         'primary_name' => true])
                ->firstOrFail();
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
      return __d('error', 'Names.minimum');
    }
    
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
    // We need the current CO ID to dynamically set validation rules according
    // to CoSettings.
    
    if(!$this->curCoId) {
      throw new \InvalidArgumentException(__d('error', 'coid'));
    }
    
    $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');
    
    $settings = $CoSettings->find()->where(['co_id' => $this->curCoId])->firstOrFail();
    
    $permittedFields = $settings->name_permitted_fields_array();
    $requiredFields = $settings->name_required_fields_array();
    
    // One of CO Person ID or Org Identity ID is required
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
    
    if(in_array('honorific', $permittedFields)) {
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
      $validator->allowEmptyString('honorific');
    }
    
    if(in_array('given', $permittedFields)) {
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
      if(in_array('given', $requiredFields)) {
        $validator->notEmptyString('given');
      } else {
        $validator->allowEmptyString('given');
      }
    }
    
    if(in_array('middle', $permittedFields)) {
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
      $validator->allowEmptyString('middle');
    }
    
    if(in_array('family', $permittedFields)) {
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
      if(in_array('family', $requiredFields)) {
        $validator->notEmptyString('family');
      } else {
        $validator->allowEmptyString('family');
      }
    }
    
    if(in_array('suffix', $permittedFields)) {
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
      $validator->allowEmptyString('suffix');
    }
    
    $validator->add(
      'type_id',
      'content',
      [ 'rule' => 'isInteger' ]
    );
    $validator->notEmptyString('type_id');
    
    $validator->add(
      'language',
      'content',
      [ 'rule' => [ 'inList', LanguageEnum::getConstValues() ] ]
    );
    $validator->allowEmptyString('language');
    
    $validator->add(
      'primary_name',
      'content',
      [ 'rule' => [ 'boolean' ] ]
    );
    $validator->allowEmptyString('primary_name');
    
    $validator->add(
      'display_name',
      'length',
      [ 'rule' => [ 'maxLength', 256 ] ]
    );
    $validator->add(
      'display_name',
      'content',
      [ 'rule'     => [ 'validateInput' ],
        'provider' => 'table' ]
    );
    $validator->allowEmptyString('display_name');
    
    $validator->add(
      'source_name_id',
      'content',
      [ 'rule' => 'isInteger' ]
    );
    $validator->allowEmptyString('source_name_id');
    
    return $validator; 
  }
}