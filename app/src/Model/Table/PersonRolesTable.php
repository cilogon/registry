<?php
/**
 * COmanage Registry Person Roles Table
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

class PersonRolesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TypeTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  
  // Default "out of the box" types for this model. Entries here should be
  // given a default localization in app/resources/locales/*/defaultType.po
  protected $defaultTypes = [
    'affiliation' => [
      'affiliate',
      'alum',
      'employee',
      'faculty',
      'librarywalkin',
      'member',
      'staff',
      'student'
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
    $this->addBehavior('Timezone');
    
    // Person Roles are not configuration
    $this->setIsConfigurationTable(false);
    
    // Define associations
    $this->belongsTo('Cous');
    $this->belongsTo('People');
    $this->belongsTo('ManagerPeople')
         ->setClassName('People')
         ->setForeignKey('manager_person_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('manager_person');
    $this->belongsTo('SponsorPeople')
         ->setClassName('People')
         ->setForeignKey('sponsor_person_id')
         ->setProperty('sponsor_person');
    $this->belongsTo('Types')
         ->setForeignKey('affiliation_type_id')
         ->setProperty('affiliation_type');
    
    $this->hasMany('Addresses')
         ->setDependent(true);
    $this->hasMany('AdHocAttributes')
         ->setDependent(true);
    $this->hasMany('TelephoneNumbers')
         ->setDependent(true);
    $this->hasMany('HistoryRecords')
         ->setDependent(true);
    
    $this->setDisplayField('id');
    
    $this->setPrimaryLink('person_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    
    $this->setEditContains([
      'Addresses',
      'AdHocAttributes',
      'TelephoneNumbers',
      // contain results in a join when the relation is belongsTo (or hasOne),
      // and joining the same table twice makes the database unhappy, so we
      // force these to use multiple queries.
      'ManagerPeople' => ['Names' => ['queryBuilder' => function ($q) {
        return $q->where(['primary_name' => true]);
      }]],
      'SponsorPeople' => ['Names' => ['queryBuilder' => function ($q) {
        return $q->where(['primary_name' => true]);
      }]]
    ]);
    
    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
        'class' => 'StatusEnum'
      ],
      'affiliationTypes' => [
        'type' => 'type',
        'attribute' => 'PersonRoles.affiliation'
      ],
      'cous' => [
        'type' => 'select',
        'model' => 'Cous'
      ]
    ]);
  }
  
  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Person $entity Entity to generate display field for
   * @return string         Display field
   */
  
  public function generateDisplayField(\App\Model\Entity\PersonRole $entity): string {
    // Try to find something renderable
    
    if(!empty($entity->title)) {
      return $entity->title;
    }
    
// XXX else affiliation type if set, else cou name, else organization, else department
    
    return (string)$entity->id;
  }
  
  /**
   * Obtain an iterator for the set of Members in the specified COU.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int                  $couId  COU ID
   * @return PaginatedSqlIterator         Iterator for Person Roles
   */
  
  public function getMembers(int $couId): PaginatedSqlIterator {
    // We don't explicitly look at valid from/through, instead we expect that
    // Expiration Policies will correctly set status.
    $conditions = [
      'cou_id' => $couId,
      'status IS NOT' => StatusEnum::Deleted
    ];
    
    return new PaginatedSqlIterator($this, $conditions);
  }
  
  /**
   * Determine if the specified Person has a valid Person Role in the specified
   * COU. Note this function only looks at status, not validity dates.
   * 
   * @param  int  $personId Person ID
   * @param  int  $couId    COU ID
   * @return bool           True if the Person has at least one active Person Role, false otherwise
   */
  
  public function hasActive(int $personId, int $couId): bool {
    // We return true if the Person has at least one active Role in the
    // specified COU. We ignore validity dates, expecting instead that
    // expiration policies will correctly update the Role status as needed.
    
    // We need to examine the status of all roles in the COU, not just the current
    // one, to see if the person is eligible for the relevant members group.
    
    $roles = $this->find('all')
                  ->where(['person_id'  => $personId,
                           'cou_id'     => $couId])
                  ->all();
    
    foreach($roles as $role) {
      // Any one active role is sufficient
      
      if($role->isActive()) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Determine if the specified Person has any Person Role in the specified COU.
   * 
   * @param  int  $personId Person ID
   * @param  int  $couId    COU ID
   * @return bool           True if the Person has at least one Person Role, false otherwise
   */
  
  public function hasAny(int $personId, int $couId): bool {
    // We return true if the Person has at least one Role in the specified COU,
    // regardless of status.
    
    // We need to examine the status of any roles returned since a Deleted Role
    // does not count as "Any" Role.
    
    $roles = $this->find('all')
                  ->where(['person_id'  => $personId,
                           'cou_id'     => $couId])
                  ->all();
    
    if(empty($roles)) {
      return false;
    }
    
    foreach($roles as $role) {
      // Any non-deleted role is sufficient
      if($role->status != StatusEnum::Deleted) {
        return true;
      }
    }
    
    return false;
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
    
    $this->reconcileCouMembersGroupMemberships($entity);
    
    return true;
  }
  
  /**
   * Reconcile memberships in COU members groups based on the
   * PersonRole(s) for a Person and the COU(s) for those roles.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EntityInterface  $entity         PersonRole Entity
   * @param  bool             $provision      Whether to run provisioners
   * @param  bool             $personActive   If false, role is not eligible for Active Members Group
   * @throws InvalidArgumentException
   * @throws RuntimeException
   */

  public function reconcileCouMembersGroupMemberships(\Cake\Datasource\EntityInterface $entity, bool $provision=true, bool $personActive=true) {
    // First see if there is a COU associated with this Role.
    
    if(!$entity->cou_id) {
      if(!$entity->isNew()) {
        // If we're going from a COU to no COU we need to remove the automatic
        // group memberships (the inverse of below, where we go from no COU to
        // having a COU)
        
        $oldCouId = $entity->getOriginal('cou_id');
        
        if($oldCouId) {
          $this->llog('rule', "AR-PersonRole-1 Removing PersonRole " . $entity->id . " (Person " . $entity->person_id . ") from All Members Group for COU " . $oldCouId . " due to removal of Person Role from COU");
          $this->People->GroupMembers->syncAutomaticMembership(GroupTypeEnum::AllMembers, $oldCouId, $entity->person_id, false, $provision);
          $this->llog('rule', "AR-PersonRole-2 Removing PersonRole " . $entity->id . " (Person " . $entity->person_id . ") from Active Members Group for COU " . $oldCouId . " due to removal of Person Role from COU");
          $this->People->GroupMembers->syncAutomaticMembership(GroupTypeEnum::ActiveMembers, $oldCouId, $entity->person_id, false, $provision);
        }
      }
      
      // Since there is no COU associated with this Person Role, there is
      // nothing else to do
      
      return;
    }
    
    if(!$entity->person_id) {
      // We're probably deleting the CO
      return;
    }
    
    // We need to examine the status of all roles in the COU, not just the current
    // one, to see if the person is eligible for the relevant members group.
    
    $roles = $this->find('all')
                  ->where(['person_id'  => $entity->person_id,
                           'cou_id'     => $entity->cou_id])
                  ->all();
    
    // For $activeEligible, we need at least one active role
    $activeRole = false;
    
    // For $allEligible, we need at least one role not Deleted
    $allEligible = false;
    
    foreach($roles as $role) {
      if($role->isActive()) {
        $activeRole = true;
      }
      
      if($role->status != StatusEnum::Deleted) {
        $allEligible = true;
      }
    }
    
    $activeEligible = $personActive && $activeRole;
    
    // Create or remove memberships for the Active and All groups for this COU.
    
    $this->llog('rule', "AR-PersonRole-1 Syncing membership in All Members Group for COU " . $entity->cou_id . " for PersonRole " . $entity->id . " (Person " . $entity->person_id . "), eligibility=" . $allEligible);
    $this->People->GroupMembers->syncAutomaticMembership(GroupTypeEnum::AllMembers, $entity->cou_id, $entity->person_id, $allEligible, $provision);
    $this->llog('rule', "AR-PersonRole-2 Syncing membership in Active Members Group for COU " . $entity->cou_id . " for PersonRole " . $entity->id . " (Person " . $entity->person_id . "), eligibility=" . $activeEligible);
    $this->People->GroupMembers->syncAutomaticMembership(GroupTypeEnum::ActiveMembers, $entity->cou_id, $entity->person_id, $activeEligible, $provision);
    
    if(!$entity->isNew()) {
      // Remove group memberships if the COU ID (PersonRole moved) or Person ID
      // (PersonRole relinked) has changed.
      
      if($entity->get('cou_id') !== $entity->getOriginal('cou_id')) {
        // We must have a COU ID, since we checked above for the case where we don't
        $oldCouId = $entity->getOriginal('cou_id');
        
        if($oldCouId) {
          $this->llog('rule', "AR-PersonRole-1 Removing PersonRole " . $entity->id . " (Person " . $entity->person_id . ") from All Members Group for COU " . $oldCouId . " due to removal of Person Role from COU");
          $this->People->GroupMembers->syncAutomaticMembership(GroupTypeEnum::AllMembers, $oldCouId, $entity->person_id, false, $provision);
          $this->llog('rule', "AR-PersonRole-1 Removing PersonRole " . $entity->id . " (Person " . $entity->person_id . ") from All Members Group for COU " . $oldCouId . " due to removal of Person Role from COU");
          $this->People->GroupMembers->syncAutomaticMembership(GroupTypeEnum::ActiveMembers, $oldCouId, $entity->person_id, false, $provision);
        }
        // else no prior COU ID, nothing to do
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
    $schema = $this->getSchema();
    
    $validator->add('person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('person_id');
    
    $validator->add('cou_id', [
      'content' => ['rule' => 'isInteger']
    ]);
// XXX this should be dynamically set based on CO Settings
    $validator->allowEmptyString('cou_id');
    
    $validator->add('affiliation_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('affiliation_type_id');
    
    $validator->add('sponsor_person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('sponsor_person_id');
    
    $validator->add('manager_person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('manager_person_id');
    
    $this->registerStringValidation($validator, $schema, 'title', false);
    
    $this->registerStringValidation($validator, $schema, 'organization', false);
    
    $this->registerStringValidation($validator, $schema, 'department', false);
    
    $validator->add('valid_from', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('valid_from');
    
    $validator->add('valid_through', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('valid_through');
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', StatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    
    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');
    
    return $validator; 
  }
}