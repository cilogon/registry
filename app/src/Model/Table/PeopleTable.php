<?php
/**
 * COmanage Registry People Table
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
use \App\Lib\Enum\StatusEnum;
use \App\Lib\Util\PaginatedSqlIterator;

class PeopleTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\SearchFilterTrait;

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
    
    // CO People are not configuration
    $this->setIsConfigurationTable(false);
    
    // Define associations
    $this->belongsTo('Cos');
    
    $this->hasOne('PrimaryName')
         ->setClassName('Names')
         ->setConditions(['PrimaryName.primary_name' => true]);
    $this->hasMany('Names')
         ->setDependent(true);
    $this->hasMany('Addresses')
         ->setDependent(true);
    $this->hasMany('AdHocAttributes')
         ->setDependent(true);
    $this->hasMany('EmailAddresses')
         ->setDependent(true);
    $this->hasMany('GroupMembers')
         ->setDependent(true);
    $this->hasMany('GroupOwners')
         ->setDependent(true);
    $this->hasMany('HistoryRecords')
         ->setDependent(true);
    $this->hasMany('Identifiers')
         ->setDependent(true);
    $this->hasMany('PersonRoles')
         ->setDependent(true);
    $this->hasMany('TelephoneNumbers')
         ->setDependent(true);
    $this->hasMany('Urls')
         ->setDependent(true);
    
// XXX can we change this to Name?
    $this->setDisplayField('id');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    
// XXX does some of this stuff really belong in the controller?
    $this->setEditContains([
      'PrimaryName',
      'Addresses',
      'AdHocAttributes',
      'EmailAddresses',
      'Identifiers',
      'Names',
      'PersonRoles',
      'TelephoneNumbers',
      'Urls'
    ]);
    $this->setIndexContains(['PrimaryName']);
    
    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
        'class' => 'StatusEnum'
      ],
      'types' => [
        'type' => 'type',
        'attribute' => 'Names.type'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
// See also CFM-126
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
    
    // XXX implement this eventually?
    //$provision = (isset($options['provision']) ? $options['provision'] : true);
    
    $this->reconcileCoMembersGroupMemberships($entity);
    
    return true;
  }
  
  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Person $entity Entity to generate display field for
   * @return string         Display field
   */
  
  public function generateDisplayField(\App\Model\Entity\Person $entity): string {
    if(empty($entity->primary_name)) {
      throw new \InvalidArgumentException(__d('error', 'Names.primary_name'));
    }
    
    return $entity->primary_name->full_name;
  }
  
  /**
   * Obtain an iterator for the set of Members in the specified CO.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int                  $coId CO ID
   * @return PaginatedSqlIterator       Iterator for People
   */
  
  public function getMembers(int $coId): PaginatedSqlIterator {
    $conditions = [
      'co_id' => $coId,
      'status IS NOT' => StatusEnum::Deleted
    ];
    
    return new PaginatedSqlIterator($this, $conditions);
  }
  
  /**
   * Reconcile memberships in CO members groups based on the Person entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EntityInterface  $entity         Person Entity
   * @param  bool             $provision      Whether to run provisioners
   * @throws InvalidArgumentException
   * @throws RuntimeException
   */

  public function reconcileCoMembersGroupMemberships(\Cake\Datasource\EntityInterface $entity, bool $provision=true) {
    // This is similar to PersonRole::reconcileCouMembersGroupMemberships.
    
    $activeEligible = $entity->isActive();
    $allEligible = $entity->status != StatusEnum::Deleted;
    
    // Update the automatic CO groups
    $this->llog('rule', "AR-Person-1 Syncing membership in All Members Group for CO " . $entity->co_id . " for Person " . $entity->id . ", eligibility=" . $allEligible);
    $this->GroupMembers->syncAutomaticMembership(GroupTypeEnum::AllMembers, null, $entity->id, $allEligible, $provision);
    $this->llog('rule', "AR-Person-2 Syncing membership in Active Members Group for CO " . $entity->co_id . " for Person " . $entity->id . ", eligibility=" . $activeEligible);
    $this->GroupMembers->syncAutomaticMembership(GroupTypeEnum::ActiveMembers, null, $entity->id, $activeEligible, $provision);
    
    // Pull the Person Roles for this Person. Note if COUs are not in use this
    // will be a bit of extra work, but probably not worth worrying about.
    
    $personRoles = $this->PersonRoles->find('all')
                                     ->where(['person_id' => $entity->id])
                                     ->all();
    
    foreach($personRoles as $role) {
      if(!empty($role->cou_id)) {
        // If the Person is not $allEligible, then no COU groups are eligible either.
        
        if($allEligible) {
          // If a Person has multiple roles in the same COU, we'll be doing a bit
          // of extra work in calling reconcileCouMembersGroupMemberships multiple
          // times, since it will correctly handle multiple roles in the same COU
          // in a single call.
          
          $this->PersonRoles->reconcileCouMembersGroupMemberships($role, $provision, $activeEligible);
        } else {
          // Make sure there are no memberships for this COU
          $this->GroupMembers->syncAutomaticMembership(GroupTypeEnum::AllMembers, $role->cou_id, $entity->id, false, $provision);
          $this->GroupMembers->syncAutomaticMembership(GroupTypeEnum::ActiveMembers, $role->cou_id, $entity->id, false, $provision);
        }
      }
    }
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', StatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    
    $validator->add('timezone', [
      'content' => ['rule' => ['validateTimeZone'],
                    'provider' => 'table' ]
    ]);
    $validator->allowEmptyString('timezone');
    
    $validator->add('date_of_birth', [
      'content' => ['rule' => 'date']
    ]);
    $validator->allowEmptyString('date_of_birth');
    
    return $validator; 
  }
}