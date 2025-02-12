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

use App\Model\Entity\PersonRole;
use Cake\Event\EventInterface;
use \Cake\I18n\FrozenTime;
use Cake\ORM\Entity;
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
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionableTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TypeTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  use \App\Lib\Traits\TabTrait;

  // Default "out of the box" types for this model. Entries here should be
  // given a default localization in app/resources/locales/*/defaultType.po
  protected $defaultTypes = [
    'affiliation_type' => [
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

  // Cache status info if we automatically recalculated the status in beforeMarshal
  protected $autoStatus = null;
  
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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);
    
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
    $this->belongsTo('SourceExternalIdentityRoles')
         ->setClassName('ExternalIdentityRoles')
         ->setForeignKey('source_external_identity_role_id')
         ->setProperty('source_external_identity_role');
    $this->belongsTo('Types')
         ->setForeignKey('affiliation_type_id')
         ->setProperty('affiliation_type');
    
    $this->hasMany('Addresses')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('AdHocAttributes')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('TelephoneNumbers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('HistoryRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setDisplayField('title');
    
    $this->setPrimaryLink('person_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    $this->setAllowLookupPrimaryLink(['unfreeze']);

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
      }]],
      'SourceExternalIdentityRoles'
    ]);
    
    $this->setViewContains([
      'SourceExternalIdentityRoles'
    ]);
    
    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
        'class' => 'StatusEnum'
      ],
      'affiliationTypes' => [
        'type' => 'type',
        'attribute' => 'PersonRoles.affiliation_type'
      ],
      'cous' => [
        'type' => 'select',
        'model' => 'Cous'
      ],
      // Required for peoplePicker
      'cosettings' => [
        'type' => 'auxiliary',
        'model' => 'CoSettings'
      ],
      // Required for peoplePicker
      'types' => [
        'type' => 'auxiliary',
        'model' => 'Types'
      ]
    ]);

    $this->setTabsConfig(
      [
        // Ordered list of Tabs
        'tabs' => ['People', 'PersonRoles', 'ExternalIdentities'],
        // What actions will inlcude the subnavigation header
        'action' => [
          // If a model renders in a subnavigation mode in edit/view mode, it cannot
          // render in index mode for the same use case/context
          // XXX edit should go first.
          'People' => ['edit', 'view'],
          'PersonRoles' => ['edit', 'view', 'index'],
          'ExternalIdentities' => ['index'],
        ],
        // What model will have a counter-badge after the tab title
        'counter' => ['PersonRoles', 'ExternalIdentities']
      ]
    );

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
// See also CFM-126
// XXX need to add couAdmin, eventually
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'unfreeze' => ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that are permitted on readonly entities (besides view)
      'readOnly' =>   ['unfreeze'],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
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
    // Perform validity date/status reconciliation. status should always be set,
    // but we'll check for it just in case.
    if(!empty($data['status'])) {
      // Note that $data['id'] will _not_ be set, even for updates, so we can't directly
      // reference the Person Role ID in log records

      // AR-PersonRole-4 A Person Role with a Valid From date in the future and a status
      // of Active, Expired, or Grace Period will be given a status of Pending Activation,
      // unless the Person Role is frozen. A Person Role in Pending Activation status with
      // a valid from date in the past will be given a status of Active.

      if(!empty($data['valid_from'])) {
        $validFrom = new FrozenTime($data['valid_from']);

        if($validFrom->isPast()
           && $data['status'] == StatusEnum::PendingActivation) {
          if(empty($data['frozen']) || !$data['frozen']) {
            $this->autoStatus = [ 'from' => $data['status'], 'to' => StatusEnum::Active ];
            $this->llog('rule', "AR-PersonRole-4 Updating status on Person Role for Person " . $data['person_id'] . " from Pending Activation to Active");
            $data['status'] = StatusEnum::Active;
          } else {
            $this->llog('trace', 'Not recalculating status on Person Role for Person ' . $data['person_id'] . ' since the record is frozen');
          }
        } elseif($validFrom->isFuture()
           && in_array($data['status'], [StatusEnum::Active,
                                         StatusEnum::Expired,
                                         StatusEnum::GracePeriod])) {
          if(empty($data['frozen']) || !$data['frozen']) {
            $this->autoStatus = [ 'from' => $data['status'], 'to' => StatusEnum::PendingActivation ];
            $this->llog('rule', "AR-PersonRole-4 Updating status on Person Role for Person " . $data['person_id'] . " from " . $data['status'] . " to Pending Activation");
            $data['status'] = StatusEnum::PendingActivation;
          } else {
            $this->llog('trace', 'Not recalculating status on Person Role for Person ' . $data['person_id'] . ' since the record is frozen');
          }
        }
      }

      // AR-PersonRole-5 A Person Role with a Valid Through date in the past and a status
      // of Active, Grace Period, or Pending Activation will be given a status of Expired,
      // unless the Person Role is frozen. A Person Role in Expired status with a valid
      // from date in the future will be given a status of Active.

      if(!empty($data['valid_through'])) {
        $validThrough = new FrozenTime($data['valid_through']);

        if($validThrough->isFuture()
           && $data['status'] == StatusEnum::Expired) {
          if(empty($data['frozen']) || !$data['frozen']) {
            $this->autoStatus = [ 'from' => $data['status'], 'to' => StatusEnum::Active ];
            $this->llog('rule', "AR-PersonRole-5 Updating status on Person Role for Person " . $data['person_id'] . " from Expired to Active");
            $data['status'] = StatusEnum::Active;
          } else {
            $this->llog('trace', 'Not recalculating status on Person Role for Person ' . $data['person_id'] . ' since the record is frozen');
          }
        } elseif($validThrough->isPast()
           && in_array($data['status'], [StatusEnum::Active,
                                         StatusEnum::GracePeriod,
                                         StatusEnum::PendingActivation])) {
          if(empty($data['frozen']) || !$data['frozen']) {
            $this->autoStatus = [ 'from' => $data['status'], 'to' => StatusEnum::Expired ];
            $this->llog('rule', "AR-PersonRole-5 Updating status on Person Role for Person " . $data['person_id'] . " from " . $data['status'] . " to Pending Expired");
            $data['status'] = StatusEnum::Expired;
          } else {
            $this->llog('trace', 'Not recalculating status on Person Role for Person ' . $data['person_id'] . ' since the record is frozen');
          }
        }
      }
    }

    $re = '/^.*\(ID: (\d+)\)$/m';
    // The data coming from the API is an int while the data coming from the UI is a string. The
    // latter is produced by the people picker
    if(!empty($data['sponsor_person_id']) && !is_int($data['sponsor_person_id']) && !ctype_digit($data['sponsor_person_id'])) {
      preg_match_all($re, $data['sponsor_person_id'], $matchesSponsor, PREG_SET_ORDER, 0);
      $data['sponsor_person_id'] = $matchesSponsor[0][1];
    }

    if(!empty($data['manager_person_id']) && !is_int($data['manager_person_id']) && !ctype_digit($data['manager_person_id'])) {
      preg_match_all($re, $data['manager_person_id'], $matchesManager, PREG_SET_ORDER, 0);
      $data['manager_person_id'] = $matchesManager[0][1];
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
    // AR-PersonRole-6 If both valid from and valid through dates are provided for
    // a Person Role, the valid from date must be earlier than the valid through date.

    $rules->add([$this, 'ruleDatesSequential'],
                'datesSequential',
                ['errorField' => 'valid_from']);

    return $rules;
  }

  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Person $entity Entity to generate display field for
   * @return string         Display field
   */
  
  public function generateDisplayField(PersonRole $entity): string {
    // Try to find something renderable
    
    if(!empty($entity->title)) {
      return $entity->title;
    }
    
// XXX else affiliation type if set, else cou name, else organization, else department
    
    return (string)$entity->id;
  }
  
  /**
   * Get information related to automatic status recalculation.
   * 
   * @since  COmanage Registry v5.0.0
   * @return array    'from': Old status, 'to': New status
   */

  public function getAutoStatus(): ?array {
    return $this->autoStatus;
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
      'status IS NOT' => StatusEnum::Archived
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
    
    // We need to examine the status of any roles returned since an Archived Role
    // does not count as "Any" Role.
    
    $roles = $this->find('all')
                  ->where(['person_id'  => $personId,
                           'cou_id'     => $couId])
                  ->all();
    
    if(empty($roles)) {
      return false;
    }
    
    foreach($roles as $role) {
      // Any non-archived role is sufficient
      if($role->status != StatusEnum::Archived) {
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
    
    if($entity->isDirty('status')) {
      $this->People->recalculateStatus($entity->person_id);
    }

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
    
    // For $allEligible, we need at least one role not Archived
    $allEligible = false;
    
    foreach($roles as $role) {
      if($role->isActive()) {
        $activeRole = true;
      }
      
      if($role->status != StatusEnum::Archived) {
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
   * Application Rule to determine if validity dates are sequential
   *
   * @param   Entity  $entity   Entity to be validated
   * @param   array   $options  Application rule options
   *
   * @return bool|string true if the Rule check passes, false otherwise
   * @since  COmanage Registry v5.0.0
   */

  public function ruleDatesSequential($entity, array $options): bool|string {
    // This rule only applies if both valid_from and valid_through are set.

    if(!empty($entity->valid_from) && !empty($entity->valid_through)) {
      $diff = $entity->valid_from->diff($entity->valid_through);

      if($diff->invert) {
        return __d('error', 'PersonRoles.valid_from.after');
      }
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

    // We take two loops through, the first time we only do a prefix search
    // (foo%). If that doesn't reach the search limit, we'll do an infix search
    // the second time around.

    $whereClause = [];

    foreach($tokens as $t) {
      $whereClause['AND'][] = [
        'OR' => [
          'LOWER(PersonRoles.title) LIKE' => '%' . strtolower($tokens[0]) . '%',
          'LOWER(PersonRoles.organization) LIKE' => '%' . strtolower($tokens[0]) . '%',
          'LOWER(PersonRoles.department) LIKE' => '%' . strtolower($tokens[0]) . '%'
        ]
      ];
    }

    return $this->find()
                ->where($whereClause)
                ->andWhere(['People.co_id' => $coId])
                ->limit($limit)
                ->contain(['People' => 'PrimaryName'])
                ->all();
  }

  /**
   * Determine the source foreign key attribute for this table, for tables that
   * have Pipelined attributes from External Identities to People.
   * 
   * @since  COmanage Registry v5.0.0
   * @return string     Source name field (eg: source_name_id)
   */

  public function sourceForeignKey(): string {
    // PersonRoles doesn't follow the standard pattern
    return "source_external_identity_role_id";
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

    $validator->add('source_external_identity_role_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('source_external_identity_role_id');
    
    $validator->add('frozen', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('frozen');

    return $validator; 
  }


  /**
   * Save attributes for a person.
   * @param int $personId ID of the person for whom attributes are being saved
   * @param array $fields Array of fields/attributes to be saved
   *
   * @return PersonRole The newly saved Role
   * @throws \Cake\Datasource\Exception\RecordNotFoundException If an issue occurs during saving
   * @since  COmanage Registry v5.1.0
   */
  public function saveAttributes(int $personId, array $fields): PersonRole
  {
    $dateFormat = 'yyyy-MM-dd HH:mm:ss';

    $role = [
      'person_id'     => $personId,
    ];

    foreach($fields as $fld) {
      $attribute = $fld->enrollment_attribute->attribute;
      $value = $fld->value;
      if(
        ($attribute === 'valid_from' || $attribute === 'valid_through')
        && is_string($value)
      ) {
        $dob =  FrozenTime::parse($value);
        $value = $dob->i18nFormat($dateFormat);
      }
      $role[$attribute] = $value;
    }

    return $this->saveOrFail($this->newEntity($role));
  }
}