<?php
/**
 * COmanage Registry Cous Table
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

use App\Lib\Enum\StatusEnum;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

use \App\Lib\Enum\ProvisioningEligibilityEnum;

class CousTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionableTrait;
  use \App\Lib\Traits\SearchFilterTrait;
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
    $this->addBehavior('Tree');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Cos');
    // AR-COU-2 A COU may not be deleted if it has any children.
    $this->belongsTo('Cous')
         ->setForeignKey('parent_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('parent');
    
    // AR-COU-6 If a COU is deleted, the special groups associated with the COU will also be deleted.
    $this->hasMany('Groups')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    // AR-COU-1 A COU may not be deleted if it has any members.
    $this->hasMany('PersonRoles');
    $this->hasMany('SyncCouPipelines')
         ->setClassName('Pipelines')
         ->setForeignKey('sync_cou_id');
    $this->hasMany('SyncReplaceCouPipelines')
         ->setClassName('Pipelines')
         ->setForeignKey('sync_replace_cou_id');
    
    $this->setDisplayField('name');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'parentIds' => [
        'type'  => 'parent'  // Even though the type is parent we refer to the parent_id
                             // which is an integer
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);

    $this->setFilterConfig([
     'identifier' => [
       'type' => 'string',
       'model' => 'Identifiers',
       'active' => true,
       'order' => 4
     ],
     'parent_id' => [
       // We want to keep the default column configuration and add extra functionality.
       // Here the extra functionality is additional to select options since the parent_id
       // is of type select
       // XXX If the extras key is present, no other provided key will be evaluated. The rest
       //     of the configuration will be expected from the TableMetaTrait::filterMetadataFields()
       'extras' => [
         'options' => [
           'isnotnull' => __d('operation','any'),
           'isnull' => __d('operation','none'),
           __d('information','table.list', 'COUs') => '@DATA@',
         ]
       ]
     ]
   ]);
  }
  
  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-CO-3 Two COUs within the same CO cannot share the same name
    $rules->add($rules->isUnique(['name', 'co_id'], __d('error', 'exists', [__d('controller', 'Cous', [1])])));
    
    // This is not an Application Rule per se, but the parent_id must be a valid
    // potential parent
    $rules->add([$this, 'rulePotentialParent'],
                'potentialParent',
                ['errorField' => 'parent_id']);
    
    return $rules;
  }

  /**
   * Get the Parent COU list(Suitable for dropdown)
   *
   * @param   int  $coId   CO ID
   *
   * @return array    List of [id, name] Parent COUs
   * @since  COmanage Registry v5.0.0
   */
  public function getParents(int $coId): array
  {
    $subquery = $this->find();
    $subquery = $subquery->where(['co_id' => $coId])
                   ->where(fn(QueryExpression $exp, Query $subquery) => $exp->isNotNull('parent_id'))
                   ->select(['parent_id'])
                   ->distinct();

    $query = $this->find('list')
                  ->where(fn(QueryExpression $exp, Query $query) => $exp->in('id', $subquery))
                  ->distinct()
                  ->select(['id', 'name']);
    $results = $query->toArray();
    return $results;
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

  public function localAfterSave(\Cake\Event\EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $options) {
    if(!empty($entity->id)) {
      if($entity->isNew()) {
        // Run setup for new COU
        
        $this->setup(id: $entity->id, coId: $entity->co_id);
      } elseif($entity->getOriginal('name') != $entity->get('name')) {
        // AR-COU-5 The name was changed, so we may need to update the system groups
        
        $this->Groups->addDefaults(coId: $entity->co_id, couId: $entity->id, rename: true);
      }
    }

    if($entity->isNew() && !empty($entity->id)) {
      // Run setup for new COU
      
      $this->setup(id: $entity->id, coId: $entity->co_id);
    }

    return true;
  }
  
  /**
   * Marshal object data for provisioning.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int $id  Entity ID
   * @return array    An array of provisionable data and eligibility
   */

  public function marshalProvisioningData(int $id): array {
    $ret = [];
    // We need the archived record on delete to properly deprovision
    $ret['data'] = $this->get($id, ['archived' => true]);

    // Provisioning Eligibility is
    // - Deleted if the changelog deleted flag is true
    // - Eligible otherwise (COUs don't currently have a suspended status)

    $ret['eligibility'] = ProvisioningEligibilityEnum::Eligible;

    if($ret['data']->deleted) {
      $ret['eligibility'] = ProvisioningEligibilityEnum::Deleted;
    }

    return $ret;
  }

  /**
   * Assemble the set of potential parent COUs.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int  $coId      CO ID
   * @param  int  $id        COU ID to determine potential parents of, or null for any (or a new) COU
   * @param  bool $hierarchy Render the hierarchy in the name
   * @return Array     Array of COU IDs and COU Names
   * @todo Make a TreeTrait and move the function there
   */
  
  public function potentialParents(int $coId, int $id=null, bool $hierarchy=false) {
    // Note prior to v5 we filtered child COUs, meaning a COU couldn't be reassigned
    // to be a child of a current child. It's not clear why we imposed that restriction.
    
    $query = null;
    
    if($hierarchy) {
      $query = $this->find('treeList', ['spacer' => '-']);
    } else {
      $query = $this->find('list');
    }
    
    $query = $query->where(['co_id' => $coId])
                  // true overrides the default Cake order for treeList so we get
                  // our items sorted alphabetically instead of by tree ID
                   ->order(['name' => 'ASC'], true);
    
    if($id) {
      $query = $query->where(['id <>' => $id]);
    }
    
    return $query->toArray();
  }
  
  /**
   * Application Rule to determine if the parent ID is a potential parent.
   *
   * @since  COmanage Registyr v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function rulePotentialParent($entity, $options) {
    // We want negative logic since we want to fail if we're editing the COmanage CO
    if(!empty($entity->parent_id)) {
      $potentialParents = $this->potentialParents(
        coId: $entity->co_id,
        id: (!empty($entity->id) ? $entity->id : null)
      );
      
      if(!isset($potentialParents[$entity->parent_id])) {
        return __d('error', 'cou.parent');
      }
    }
    
    return true;
  }
  
  /**
   * Perform initial setup for a COU.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int  $id   COU ID
   * @param  int  $coId CO ID
   * @return bool       True on success
   */
  
  public function setup(int $id, int $coId): bool {
    // AR-COU-4 Create the default groups
    $this->Groups->addDefaults(coId: $coId, couId: $id);
    
    return true;
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
    
    $this->registerStringValidation($validator, $schema, 'name', true);
    
    $this->registerStringValidation($validator, $schema, 'description', false);
    
    $validator->add('parent_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('parent_id');
    
    $validator->add('lft', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('lft');
    
    $validator->add('rght', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('rght');
    
    return $validator; 
  }
}