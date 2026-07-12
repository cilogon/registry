<?php
/**
 * COmanage Registry Group Nestings Table
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
use \App\Lib\Enum\GroupTypeEnum;
use \App\Lib\Enum\SuspendableStatusEnum;

class GroupNestingsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Provide the default layout
   *
   * @since  COmanage Registry v5.0.0
   * @return string  Type of redirect
   */
  public function getLayout(string $action = ''): string {
    return match($action) {
      'add','edit','view','deleted' => 'iframe',
      default => 'default'
    };
  }
  
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
    $this->belongsTo('Groups');
    $this->belongsTo('TargetGroups')
         ->setClassName('Groups')
         ->setForeignKey('target_group_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('target_group');
    
    $this->hasMany('GroupMembers');
    
    $this->setDisplayField('id');
    
    $this->setPrimaryLink('group_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['queue']);
    $this->setAllowLookupRelatedPrimaryLink(['queue' => ['group_id']]);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');

    $this->setEditContains(['Groups', 'GroupMembers', 'TargetGroups']);
    
    $this->setIndexContains(['Groups', 'GroupMembers', 'TargetGroups']);
    
    // Enable the Model Specific REST API for this Table
    $this->enableMsrApi();
    
    $this->setPermissions([
// XXX update for couAdmins, group owners, etc
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'queue' =>    ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
        'deleted' =>  ['platformAdmin', 'coAdmin']
      ]
    ]);

    // XXX Keeping for functionality reference
//    $this->setAutoViewVars([
//     'groupMembers' => [
//       'type' => 'auxiliary',
//       'model' => 'GroupMembers',
//       'whereEval' => [
//         // Where Clause column name
//         'GroupMembers.group_id' => [
//           // Chain of methods that will construct the whereClause condition value
//           // Method that accepts no parameters
//           'getRequest',
//           // Method that accepts only one parameter
//           // getQuery(name: 'group_id')
//           'getQuery' => [
//             'name' =>'group_id'
//           ]
//         ]
//       ]
//     ]]);

  }
  
  /**
   * Obtain the set of groups available as a nesting target group for the
   * specified source.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int   $groupId Group ID (of source Group)
   * @return array          Array of available target Groups, as returned by find('list')
   */
  
  public function availableGroups(int $groupId): array {
    // Find the CO for $id. This will throw an exception if not found.
    $sourceGroup = $this->Groups->get($groupId);
    
    // We don't remove groups that are already nested from the list -- we'll
    // catch those in rule validation.
    
    return $this->Groups->find('list')
                        ->where([
                          'Groups.co_id'      => $sourceGroup->co_id,
                          // AR-Group-Nesting-1 Only Active groups may be nested
                          'Groups.status'     => SuspendableStatusEnum::Active,
                          // AR-Group-Nesting-2 A group may not nest into itself
                          'Groups.id IS NOT'  => $groupId,
                          // AR-Group-Nesting-3 Automatic groups cannot be targets
                          'OR' => [
                            'Groups.group_type NOT IN' => [GroupTypeEnum::ActiveMembers, GroupTypeEnum::AllMembers],
                            // Unclear why null values don't qualify for NOT IN...
                            'Groups.group_type IS' => null
                          ]
                        ])
                        ->orderBy(['Groups.name' => 'ASC'])
                        ->toArray();
  }
  
  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // Since the groups in the nesting can't be changed after creation, these
    // rules only need apply on new entity creation.
    
    // AR-Group-Nesting-1 Only Active groups may be nested
    
    $rules->addCreate([$this, 'ruleIsActive'],
                      'isActive',
                      ['errorField' => 'target_group_id']);
    
    // AR-Group-Nesting-2 A group may not nest into itself
    
    $rules->addCreate([$this, 'ruleIsNotSource'],
                      'isNotSource',
                      ['errorField' => 'target_group_id']);
    
    // AR-Group-Nesting-3 A group may not nest into an Automatic group
    
    $rules->addCreate([$this, 'ruleIsNotAutomatic'],
                      'isNotAutomatic',
                      ['errorField' => 'target_group_id']);
    
    // AR-Group-Nesting-4 A group may not nest into the same target group
    // more than once.
    
    $rules->addCreate([$this, 'ruleAlreadyNested'],
                      'alreadyNested',
                      ['errorField' => 'target_group_id']);
    
    // AR-Group-Nesting-5 Group Nestings may not loop
    
    $rules->addCreate([$this, 'ruleLoops'],
                      'loops',
                      ['errorField' => 'target_group_id']);
    
    return $rules;
  }
  
  /**
   * Check for loops in Group Nestings.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $groupId    Source Group ID
   * @param  int    $targetId   Target Group ID
   * @return bool               true if no loop is detected, false otherwise
   */
  
  protected function checkLoops(int $groupId, int $targetId): bool {
    // Pull the set of nestings for which $targetId is the source.
    
    $nestings = $this->find('all')
                     ->where(['GroupNestings.group_id' => $targetId])
                     ->all();
    
    foreach($nestings as $n) {
      // If _this_ target_group_id matches $groupId then we have a loop.
      // We also fail if this target_group_id is nested into $groupId.
// XXX do we need to maxrecursion this?
      
      if($n->target_group_id == $groupId
         || !$this->checkLoops($groupId, $n->target_group_id)) {
        return false;
      }
    }
    
    return true;
  }
  
  /**
   * Determine if the requested group already nests into the specified target.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $groupId    Source Group ID
   * @param  int    $targetId   Target Group ID
   * @return bool               True if $groupId does not already nest into $targetId, false otherwise
   */
  
  protected function checkParents(int $groupId, int $targetId): bool {
    // Pull the set of nestings for which $groupId is the source.
    
    $nestings = $this->find('all')
                     ->where(['GroupNestings.group_id' => $groupId])
                     ->all();
    
    foreach($nestings as $n) {
      // We fail if this nesting points to $targetId, or if any nestings
      // for _this_ target_group_id point to $targetId
// XXX do we need to maxrecursion this?
      if($n->target_group_id == $targetId
         || !$this->checkParents($n->target_group_id, $targetId)) {
        return false;
      }
    }
    
    return true;
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
    $this->Groups->reconcile($entity->target_group_id, $options['job'] ?? null);
    
    return true;
  }
  
  /**
   * Application Rule to determine if the source group is already nested in the target.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleAlreadyNested($entity, $options) {
    // The first (easy) check is if there is a direct pair already recorded
    
    $count = $this->find('all')
                  ->where([
                    'GroupNestings.group_id'        => $entity->group_id,
                    'GroupNestings.target_group_id' => $entity->target_group_id
                  ])
                  ->count();
    
    if($count > 0 || !$this->checkParents($entity->group_id, $entity->target_group_id)) {
      return __d('error', 'GroupNestings.exists');
    }
    
    return true;
  }
  
  /**
   * Application Rule to determine if the target group is Active.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleIsActive($entity, $options) {
    if(!empty($entity->target_group_id)) {
      // get() throws an Exception if not found
      $group = $this->Groups->get($entity->target_group_id);
      
      if($group->status != SuspendableStatusEnum::Active) {
        return __d('error', 'GroupNestings.active', [$group->name]);
      }
    }
    
    return true;
  }
  
  /**
   * Application Rule to determine if the target group is (not) the source group.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleIsNotAutomatic($entity, $options) {
    if(!empty($entity->target_group_id)) {
      // get() throws an Exception if not found
      $group = $this->Groups->get($entity->target_group_id);
      
      if($group->isAutomatic()) {
        return __d('error', 'GroupNestings.automatic', [$group->name]);
      }
    }
    
    return true;
  }
  
  /**
   * Application Rule to determine if the target group is (not) the source group.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleIsNotSource($entity, $options) {
    if($entity->group_id == $entity->target_group_id) {
      return __d('error', 'GroupNestings.same');
    }
    
    return true;
  }
  
  /**
   * Application Rule to determine if the target group is already nested into the
   * source group.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleLoops($entity, $options) {
    if(!$this->checkLoops($entity->group_id, $entity->target_group_id)) {
      return __d('error', 'GroupNestings.loop');
    }
    
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
    
    $validator->add('group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('group_id');
    
    $validator->add('target_group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('target_group_id');
    
    $validator->add('negate', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('negate');
    
    return $validator; 
  }
}