<?php
/**
 * COmanage Registry Group Owners Table
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
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\SuspendableStatusEnum;

class GroupOwnersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);
    
    // Define associations
    $this->belongsTo('Groups');
    $this->belongsTo('People');
    
    $this->setDisplayField('id');
    
    $this->setPrimaryLink('group_id');
    $this->setRequiresCO(true);

    $this->setEditContains(['Groups', 'People.PrimaryName']);
    
    $this->setIndexContains(['Groups', 'People.PrimaryName']);
    
    $this->setPermissions([
// XXX update for couAdmins, group owners, etc
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     false,
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
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
// XXX This isn't explicitly an Application Rule, should it be?
// XXX This isn't a great error message, GroupMember has a better one but needs additional
//     context to construct it
    $rules->add($rules->isUnique(['person_id', 'group_id'], __d('error', 'exists', [__d('controller', 'GroupOwners', [1])])));
    
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
    // Pull the related entities for HistoryRecord comment creation.
    $person = $this->People->get($entity->person_id, ['contain' => ['PrimaryName']]);
    $group = $this->Groups->get($entity->group_id);
    
    if($entity->isNew()) {
      $action = ActionEnum::GroupOwnerAdded;
      $comment = __d('result', 'GroupOwners.added', [$person->primary_name->full_name, $group->name]);
    } elseif($entity->get('deleted')) {
      $action = ActionEnum::GroupOwnerDeleted;
      $comment = __d('result', 'GroupOwners.deleted', [$person->primary_name->full_name, $group->name]);
    } else {
      // GroupOwners can't currently be edited
//      $action = ActionEnum::GroupOwnerEdited;
//      $comment = __d('result', 'GroupOwners.edited', [$person->primary_name->full_name, $group->name, $this->changesToString($entity)]);
    }
    
    $this->recordHistory($entity, $action, $comment);
    
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
    
    $validator->add('person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('person_id');
    
    return $validator; 
  }
}