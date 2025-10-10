<?php
/**
 * COmanage Registry Groups Table
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

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use \App\Lib\Util\PaginatedSqlIterator;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\GroupTypeEnum;
use \App\Lib\Enum\ProvisioningEligibilityEnum;
use \App\Lib\Enum\StatusEnum;
use \App\Lib\Enum\SuspendableStatusEnum;

class GroupsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionableTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  use \App\Lib\Traits\TabTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TreeTrait {
    potentialParents as traitPotentialParents;
  }
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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Primary);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('Cous');
    $this->belongsTo('Groups')
         ->setForeignKey('parent_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('parent');
    
    // Most Groups (except other Owners groups) have an Owner Group,
    // which we should define with a hasOne relation (and a foreign key
    // like owners_for_group_id). However, this doesn't intuitively define
    // the relationship (having owners_group_id point to the Owners group
    // is more obvious than having the Owners group fk back to the original),
    // and also makes it more expensive to query the database, so we use
    // a belongsTo relation instead. (This also aligns with Cou::parent_id.)
    // The downside of this is we have to manually cascade deletes to the Owners
    // group, since cascades don't travers belongsTo.
    $this->belongsTo('OwnersGroup')
         ->setClassName('Groups')
         ->setForeignKey('owners_group_id')
         ->setProperty('owners_group');
    
    // And the inverse relation, for the Owners Group
    $this->hasOne('OwnersForGroup')
         ->setClassName('Groups')
         ->setForeignKey('owners_group_id');

    $this->hasMany('ChildGroups')
         ->setClassName('Groups')
         ->setForeignKey('parent_id');
    $this->hasMany('EnrollmentFlowSteps')
         ->setForeignKey('notification_group_id');
    $this->hasMany('GroupMembers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('GroupNestings')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('HistoryRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Identifiers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('ProvisioningHistoryRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('ProvisioningTargets')
         ->setForeignKey('provisioning_group_id');

    $this->setDisplayField('name');
    
    $this->setPrimaryLink('co_id');
    $this->setAllowLookupPrimaryLink(['provision', 'reconcile']);
    $this->setRequiresCO(true);
    
    $this->setEditContains([
      'ChildGroups',
      'Identifiers',
      // For an Owners Group, the group it manages owners for
      'OwnersForGroup',
      // For a regular group, the Owners Group
      'OwnersGroup',
      'GroupMembers'
    ]);

    $this->setViewContains([
      'ChildGroups',
      'Identifiers',
      // For an Owners Group, the group it manages owners for
      'OwnersForGroup',
      // For a regular group, the Owners Group
      'OwnersGroup',
      'GroupMembers'
     ]);

    // XXX Also used by SearchBlocks
    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
        'class' => 'SuspendableStatusEnum'
      ],
      'groupTypes' => [
        'type' => 'enum',
        'class' => 'GroupTypeEnum'
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
      ],
      'parents' => [
        'type'  => 'parent'
      ]
    ]);

    $this->setFilterConfig([
      'identifier' => [
        'type' => 'string',
        'model' => 'Identifiers',
        'active' => true,
        'order' => 4
      ],
      'person_id' => [
        'type' => 'integer',
        'model' => 'GroupMembers',
        'active' => true,
        'order' => 5,
        'picker' => [
          'type' => 'person',
          'configuration' => [
            // For the Groups Filtering block we want to
            // pick/GET from the entire CO pool of people
            'action' => 'GET',
            // The co configuration will fall through the default configuration
            'for' => 'co'
          ]
        ]
      ]
    ]);

    $this->setTabsConfig(
      [
        // Ordered list of Tabs
        'tabs' => ['Groups', 'GroupMembers', 'GroupNestings'],
        // What actions will inlcude the subnavigation header
        'action' => [
          // If a model renders in a subnavigation mode in edit/view mode, it cannot
          // render in index mode for the same use case/context
          // XXX edit should go first.
          'Groups' => ['edit', 'view'],
          'GroupMembers' => ['index'],
          'GroupNestings' => ['index'],
        ],
        // What model will have a counter-badge after the tab title
        'counter' => ['GroupMembers']
      ]
    );

    $this->setPermissions([
  // XXX update for couAdmins, etc
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'provision' =>  ['platformAdmin', 'coAdmin'],
        'reconcile' =>  ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that are permitted on readonly entities (besides view)
      'readOnly' =>    ['reconcile'],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        // Note that self service Group creation will be implemented via
        // a Dashboard widget (CFM-316) and NOT via this index page
        'index' =>    ['platformAdmin', 'coAdmin']
      ],
      // Related models whose permissions we'll need, typically for table views
      'related' => [
        'table' => [
// XXX As a first pass, this (combined with the implementation in AppController::calculatePermissions)
//     will render a link to group-members?group_id=X for all groups in the index view
//     groups?co_id=2. This may or may not be right in the long term, eg for private
//     groups. Maybe it's OK for now, since all groups are visible to all members of the CO.
          'GroupMembers',
          'GroupNestings',
          'HistoryRecords',
          'IdentifierAssignments',
          'Identifiers',
          'ProvisioningTargets'
        ],
      ]
    ]);
  }
  
  /**
   * Add the system groups for a CO or COU. (AR-CO-6, AR-COU-4)
   *
   * @since  COmanage Registry v5.0.0
   * @param  int   $coId    CO ID
   * @param  int   $couId   COU ID
   * @param  bool  $rename  If true, rename any existing groups
   * @return bool           True on success
   * @throws InvalidArgumentException
   * @throws RuntimeException
   * @throws PersistenceFailedException
   */

  public function addDefaults(int $coId, int $couId=null, bool $rename=false): bool {
    // Pull the name of the CO/COU
    
    $Cos = TableRegistry::getTableLocator()->get('Cos');
    
    try {
      $co = $Cos->get($coId);
    }
    catch(\Cake\Datasource\Exception\RecordNotFoundException $e) {
      throw new \InvalidArgumentException(__d('error', __d('controller', 'Cos', [1])));
    }
    
    $couName = null;

    if($couId) {
      $Cous = TableRegistry::getTableLocator()->get('Cous');
      
      try {
        $cou = $Cous->get($couId);
      }
      catch(\Cake\Datasource\Exception\RecordNotFoundException $e) {
        throw new \InvalidArgumentException(__d('error', 'notfound', [__d('controller', 'Cous', [1])]));
      }
      
      $couName = $cou->name;
    }
    
    // The names get prefixed "CO" or "CO:COU:<couname>", as appropriate
    
    $defaultGroups = [
      ':admins' => [
        'group_type'  => GroupTypeEnum::Admins,
        'auto'        => false,
        'description' => __d('field', 'Groups.desc.admins', [$couName ?: $co->name]),
        'open'        => false,
        'status'      => SuspendableStatusEnum::Active,
        'cou_id'      => ($couId ?: null)
      ],
      ':approvers' => array(
        'group_type'  => GroupTypeEnum::Approvers,
        'auto'        => false,
        'description' => __d('field', 'Groups.desc.approvers', [$couName ?: $co->name]),
        'open'        => false,
        'status'      => SuspendableStatusEnum::Active,
        'cou_id'      => ($couId ?: null)
      ),
      ':members:active' => [
        'group_type'  => GroupTypeEnum::ActiveMembers,
        'auto'        => true,
        'description' => __d('field', 'Groups.desc.members.active', [$couName ?: $co->name]),
        'open'        => false,
        'status'      => SuspendableStatusEnum::Active,
        'cou_id'      => ($couId ?: null)
      ],
      ':members:all' => [
        'group_type'  => GroupTypeEnum::AllMembers,
        'auto'        => true,
        'description' => __d('field', 'Groups.desc.members', [$couName ?: $co->name]),
        'open'        => false,
        'status'      => SuspendableStatusEnum::Active,
        'cou_id'      => ($couId ?: null)
      ]
    ];

    if(!$couId) {
      // Registry MFA Exempt group only exists at the CO level

      $defaultGroups[':mfaexempt'] = [
        'group_type'  => GroupTypeEnum::MfaExempt,
        'auto'        => false,
        'description' => __d('field', 'Groups.desc.mfaexempt', [$couName]),
        'open'        => false,
        'status'      => SuspendableStatusEnum::Active,
        'cou_id'      => null
      ];
    }
    
    foreach($defaultGroups as $suffix => $attrs) {
      // Construct the full group name
      $gname = "CO" . ($couName ? ":COU:".$couName : "") . $suffix;

      // See if there is already a group with this type for this CO. Note this implies
      // two COUs can't have the same name either (since we'll construct automatic Groups
      // for each COU based on its name), but that's covered by AR-COU-3.

      $grp = $this->find()
                  ->where([
                    'Groups.co_id'      => $coId,
                    'Groups.group_type' => $attrs['group_type'],
                    'Groups.cou_id IS'  => $couId ?: null
                  ])
                  ->first();
      
      if(!$grp) {
        // No existing group, create a new one
        
        $entity = $this->newEntity($attrs);
        $entity->co_id = $coId;
        $entity->name = $gname;
        
        if(!$this->save($entity)) {
          throw new \RuntimeException(__d('error', 'save', ['GroupsTable::addDefaults']));
        }
      } elseif($rename) {
        // We already have an entity, so just update the fields we need to change
        $grp->name = $gname;
        $grp->description = $attrs['description'];
        
        if(!$this->save($grp)) {
          throw new \RuntimeException(__d('error', 'save', ['GroupsTable::addDefaults']));
        }
      }
    }

    return true;
  }

  /**
   * Callback after data is marshaled into an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface  $event   afterMarshal event
   * @param  EntityInterface $entity  Entity
   * @param  ArrayObject     $data    Original request data
   * @param  ArrayObject     $options Callback options
   */

  public function afterMarshal(
    EventInterface $event, 
    EntityInterface $entity, 
    \ArrayObject $data, 
    \ArrayObject $options
  ) {
    // The inbound $data will in general not include the Group Type because it's not
    // provided in the form data, and isn't supposed to change anyway. 

    if(empty($data['group_type']) && empty($entity->group_type)) {
      // If no group_type was set, this is a Standard Group, so fill in the field.
      $data['group_type'] = GroupTypeEnum::Standard;
      $entity->group_type = GroupTypeEnum::Standard;
    }
  }

  /**
   * Callback before model delete.
   *
   * @since  COmanage Registry v5.0.0
   * @param  CakeEventEvent $event   The beforeDelete event
   * @param                 $entity  Entity
   * @param  ArrayObject    $options Options
   * @return boolean                 True on success
   */

  public function beforeDelete(EventInterface $event, $entity, \ArrayObject $options) {
    // AR-Group-8 When a Group is deleted, its corresponding Owners Group is also deleted.
    if(!empty($entity->owners_group_id)) {
      $ownersGroup = $this->get($entity->owners_group_id);
      $this->delete($ownersGroup);

      // We leave the foreign key in place on $entity in case someone decides
      // to look at the archived data.
    }

    $event->setResult(true);
  }

  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-Group-1 Two Groups within the same CO and with the same Parent (if set) 
    // cannot share the same name.
    $rules->addUpdate([$this, 'ruleFQNameUnique'],
                      'checkFQNameUnique',
                      ['errorField' => 'name']);
    
    // AR-Group-2 A Group cannot be set to Suspended if it is nested into a
    // Target Group or is a Target Group for a nesting. This and AR-Group-3
    // are to avoid unexpected consequences from implicitly undoing a nesting...
    // the administrator must do that first.
    $rules->addUpdate([$this, 'ruleIsNested'],
                      'isNestedUpdate',
                      ['errorField' => 'status']);
    
    // AR-Group-3 A Group cannot be deleted if it is nested into a Target Group
    // or is a Target Group for a nesting
    $rules->addDelete([$this, 'ruleIsNested'],
                      'isNestedDelete',
                      ['errorField' => 'status']);
    
    // AR-Group-4 The name, description, and status of a Group of type Owners
    // cannot be manually changed.
    $rules->addUpdate([$this, 'ruleOwnerIsModified'],
                      'ownerDescriptionModified',
                      ['errorField' => 'description']);
    $rules->addUpdate([$this, 'ruleOwnerIsModified'],
                      'ownerNameModified',
                      ['errorField' => 'name']);
    $rules->addUpdate([$this, 'ruleOwnerIsModified'],
                      'ownerStatusModified',
                      ['errorField' => 'status']);
    
    // Similarly, the group_type cannot be changed for any Group
    $rules->addUpdate([$this, 'ruleTypeIsModified'],
                      'typeModified',
                      ['errorField' => 'group_type']);
    
    // AR-Group-9 Standard Group names may not use colons (:).
    // AR-Group-10 Standard Groups may not be named CO.
    $rules->addUpdate([$this, 'ruleNameSyntax'],
                      'checkNameSyntax',
                      ['errorField' => 'name']);

    // AR-Group-11 Only Standard Groups may have parents.
    // AR-Group-12 Only Standard Groups may be parents.
    $rules->addUpdate([$this, 'ruleAreStandard'],
                      'checkParentsStandard',
                      ['errorField' => 'parent_id']);

    // AR-Group-13 A Group may not be deleted if it has any children.
    $rules->addDelete([$this, 'ruleHasChildren'],
                      'hasChildrenDelete',
                      ['errorField' => 'parent_id']);

    // This is not an Application Rule per se, but the parent_id must be a valid
    // potential parent
    $rules->add([$this, 'rulePotentialParent'],
                'potentialParent',
                ['errorField' => 'parent_id']);

    return $rules;
  }

  /**
   * Create an Owners Group for the requested Group.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Group  $group  Group Entity to create an Owners Group for
   * @return int            Owners Group ID
   * @throws PersistenceFailedException
   */

  public function createOwnersGroup($group): int {
    if($group->isOwners()) {
      throw new \InvalidArgumentException("Group is already an Owners Group");
    }

    $ownerGroup = $this->newEntity([
      'co_id'       => $group->co_id,
      'cou_id'      => $group->cou_id,
      // For now we just prefix everything with the same string, but maybe
      // we want to be smarter for System Groups?
      'name'        => 'CO:owners:' . $group->name,
      'description' => __d('field', 'Groups.owners.desc.affix', [$group->name]),
      'open'        => false,
      'status'      => SuspendableStatusEnum::Active,
      'group_type'  => GroupTypeEnum::Owners
    ]);

    // AR-Group-6 Groups of type Owners cannot be provisioned.
    $this->saveOrFail($ownerGroup, ['archive' => false]);

    // Update the original Group with a pointer to this one
    $group->owners_group_id = $ownerGroup->id;

    $this->saveOrFail($group, ['archive' => false]);

    return $ownerGroup->id;
  }
  
  /**
   * Find a CO's Administrators group.
   *
   * @since  COmanage Registry v5.0.0
   * @param  \Cake\ORM\Query $query   Query
   * @param  array           $options Options: co_id (required)
   * @return \Cake\ORM\Query          Query
   */
  
  public function findAdminGroup(Query $query, array $options): Query {
    return $query->where([
      'co_id'       => $options['co_id'],
      'cou_id IS'   => null,
      'status'      => SuspendableStatusEnum::Active,
      'group_type'  => GroupTypeEnum::Admins
    ]);
  }
  
  /**
   * Find a CO's MFA Exemption Group.
   *
   * @since  COmanage Registry v5.2.0
   * @param  \Cake\ORM\Query $query   Query
   * @param  array           $options Options: co_id (required)
   * @return \Cake\ORM\Query          Query
   */
  
  public function findMfaExemptGroup(Query $query, array $options): Query {
    return $query->where([
      'co_id'       => $options['co_id'],
      'cou_id IS'   => null,
      'status'      => SuspendableStatusEnum::Active,
      'group_type'  => GroupTypeEnum::MfaExempt
    ]);
  }

  /**
   * Get the Admin Group for a CO.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int $coId CO ID
   * @return int       Group ID
   */
  
  public function getAdminGroupId(int $coId): int {
    $g = $this->find('adminGroup', co_id: $coId)->firstOrFail();

    return $g->id;
  }
  
  /**
   * Obtain the fully qualified name for the Group, which will include all
   * parent names, separated by colons.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Group  $group  Owners Group entity
   * @return string         Fully qualified Group name
   */

  public function getFullyQualifiedName($group): string {
    // We solve this with recursion, yay!

    if(!empty($group->parent_id)) {
      $parent = $this->get($group->parent_id);

      return $this->getFullyQualifiedName($parent) . ":" . $group->name;
    } else {
      return $group->name;
    }
  }
  
  /**
   * Get the MFA Exemption Group for a CO.
   *
   * @since  COmanage Registry v5.2.0
   * @param  int $coId CO ID
   * @return int       Group ID
   */
  
  public function getMfaExemptGroupId(int $coId): int {
    $g = $this->find('mfaExemptGroup', ['co_id' => $coId])->firstOrFail();
    
    return $g->id;
  }
  
  /**
   * Get the Approvers Group for a CO or COU.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int  $coId   CO ID
   * @param  int  $couId  COU ID
   * @return int          Group ID
   * @throws \Cake\Datasource\Exception\RecordNotFoundException
   */

  public function getApproversGroupId(?int $coId=null, ?int $couId=null): int {
    $whereClause = [
      'status'      => SuspendableStatusEnum::Active,
      'group_type'  => GroupTypeEnum::Approvers
    ];

    if($coId) {
      $whereClause['co_id'] = $coId;
    }

    if($couId) {
      $whereClause['cou_id'] = $couId;
    } else {
      // Make sure not to pull the CO level Approvers group
      $whereClause['cou_ID IS'] = null;
    }

    $group = $this->find()->where($whereClause)->firstOrFail();

    return $group->id;
  }

  /**
   * Obtain an iterator for all members of the requested Group.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id             Group ID
   * @param  int    $groupNestingId If provided, only members due to this Group Nesting ID
   * @param  bool   $valid          If true, only return members with valid validity dates
   * @param  bool   $active         If true, only return members with an active Person status
   * @param  bool   $activeGroup    If true, the Group itself must be Active
   * @return PaginatedSqlIterator   Iterator for GroupMembers
   * @throws InvalidArgumentException
   */
  
  public function getMembers(
    int   $id,
    int   $groupNestingId=null,
    bool  $valid=true,
    bool  $active=true,
    bool  $activeGroup=true
  ): PaginatedSqlIterator {
    $conditions = [
      'group_id' => $id,
    ];

    $filter = null;

    if($activeGroup) {
      // Do a separate check on the Group itself since we only need to do that once

      $group = $this->get($id);

      if($group->status != SuspendableStatusEnum::Active) {
        throw new \InvalidArgumentException(__d('error', 'inactive', [__d('controller', 'Groups', [1]), $id]));
      }
    }
    
    if($groupNestingId) {
      $conditions['group_nesting_id'] = $groupNestingId;
    }

    if($active || $valid) {
      // We handle these constraints via filters to avoid reproducing application
      // logic in multiple places. This means the Paginator count will be an upper
      // bound instead of an exact count, but we don't need that here.

      // Retrieve the PeopleTable once and pass it to the callback to use.
      $People = TableRegistry::getTableLocator()->get('People');

      $filter = function ($entity) use ($People, $active, $valid) {
        // $entity is a GroupMember object from the result set.

        if($active) {
          // Retrieve the Person to check its status

          // person_id is a required field, so this get() shouldn't fail.
          // If it does, an exception will be thrown.
          $person = $People->get($entity->person_id);

          if(!$person->isActive()) {
            return false;
          }
        }

        if($valid && !$entity->isValid()) {
          return false;
        }

        return true;
      };
    }
    
    return new PaginatedSqlIterator(
      table: $this->GroupMembers->getTarget(), 
      conditions: $conditions,
      filter: $filter
    );
  }
  
  /**
   * Get Members of this Group who are Members due to the specified Group
   * Nesting ID.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int                  $id             Group ID
   * @param  int                  $groupNestingId Group Nesting ID
   * @return PaginatedSqlIterator                 Iterator for GroupMembers
   */
  
  public function getMembersViaNesting(int $id, int $groupNestingId): PaginatedSqlIterator {
    return $this->getMembers($id, $groupNestingId, false, false, false);
  }
  
  /**
   * Define the table's implemented events.
   * 
   * @since  COmanage Registry v5.0.0
   */

  public function implementedEvents(): array {
    $events = parent::implementedEvents();

    // We need to adjust our beforeDelete priority to run before ChangelogBehavior's.
    $events['Model.beforeDelete'] = [
      'callable' => 'beforeDelete',
      'priority' => 1
    ];

    return $events;
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
    
  public function localAfterSave(EventInterface $event, EntityInterface $entity, \ArrayObject $options): bool {
    if($entity->isNew()) {
      $action = ActionEnum::GroupAdded;
      $comment = __d('result', 'Groups.added', [$entity->name]);
    } elseif($entity->get('deleted')) {
      $action = ActionEnum::GroupDeleted;
      $comment = __d('result', 'Groups.deleted', [$entity->name]);
    } else {
      $action = ActionEnum::GroupEdited;
      $comment = __d('result', 'Groups.edited', [$entity->name, $this->changesToString($entity)]);
    }
    
    $this->recordHistory($entity, $action, $comment);
    
    if(!$entity->isOwners()) {
      if($entity->isNew()) {
        // When a new Group is created, create the owners Group for it.
        // This includes automatic Groups.

        $this->createOwnersGroup($entity);
      } elseif(!$entity->get('deleted')) {
        // If a Group is updated, we may need to update the same attributes
        // in the Owners Group.

        $this->updateOwnersGroup($entity);
      }
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

    $ret['data'] = $this->get($id, [
      // We need archives for handling deleted records
      'archived' => 'true',
      'contain' => [
        'GroupMembers',
        'Identifiers'
      ]
    ]);
    
    // Provisioning Eligibility is
    // - Deleted if the changelog deleted flag is true
    // - Eligible if the status is Active and the group type is not Owners
    // - Ineligible otherwise

    $ret['eligibility'] = ProvisioningEligibilityEnum::Ineligible;

    // We filter various attributes depending on the status of the record.

    if($ret['data']->deleted) {
      $ret['eligibility'] = ProvisioningEligibilityEnum::Deleted;

      // For deleted or archived records, we remove all Group Members,
      // but we leave the Identifiers in place.

      $ret['data']->group_members = [];
    } elseif($ret['data']->status == SuspendableStatusEnum::Active
             && !$ret['data']->isOwners()) {
      $ret['eligibility'] = ProvisioningEligibilityEnum::Eligible;

      // For Eligible, we still need to remove Group Memberships that are
      // invalid, and Identifiers that are suspended.

      $groupMembers = [];

      foreach($ret['data']->group_members as $gm) {
        if($gm->isValid()) {
          $groupMembers[] = $gm;
        }
      }

      $ret['data']->group_members = $groupMembers;

      $identifiers = [];

      foreach($ret['data']->identifiers as $id) {
        if($id->status == SuspendableStatusEnum::Active) {
          $identifiers[] = $id;
        }
      }

      $ret['data']->identifiers = $identifiers;
    } else {
      $ret['eligibility'] = ProvisioningEligibilityEnum::Ineligible;
      // For Ineligible records, we remove the group memberships, and
      // any suspended Identifiers.

      $ret['data']->group_members = [];
      
      $identifiers = [];

      foreach($ret['data']->identifiers as $id) {
        if($id->status == SuspendableStatusEnum::Active) {
          $identifiers[] = $id;
        }
      }

      $ret['data']->identifiers = $identifiers;
    }

    return $ret;
  }

  /**
   * Assemble the set of potential parent Groups.
   *
   * @since  COmanage Registry v5.2.0
   * @param  int    $coId      CO ID
   * @param  int    $id        Group ID to determine potential parents of, or null for any (or a new) Group
   * @param  bool   $hierarchy Render the hierarchy in the name
   * @param  array  $where     Additional conditions for filtering potential parents
   * @return array           Array of Group IDs and Group Names
   * @todo Make a TreeTrait and move the function there
   */
  
  public function potentialParents(
    int $coId,
    int $id=null,
    bool $hierarchy=false,
    array $where=[]
  ): array {
    // We generally want the same functionality as TreeTrait::potentialParents, but
    // only Standard Groups may have parents, so if $id is provided check it first.

    if($id) {
      $entity = $this->get($id);

      if($entity->group_type != GroupTypeEnum::Standard) {
        return [];
      }
    }

    // Additionally, we filter out non-Standard Groups to reduce noise in the UI
    // (even though normally we'd want to include ineligible entities to avoid UX issues).

    return $this->traitPotentialParents($coId, $id, $hierarchy, ['group_type' => GroupTypeEnum::Standard]);
  }

  /**
   * Reconcile the members of an automatic or nested Group.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $id   Group ID
   */
  
  public function reconcile(int $id) {
    $group = $this->get($id);
    
    if($group->isAutomatic()) {
      $this->reconcileAutomaticGroup($group);
    } else {
      $this->reconcileNestedMemberships($group);
    }
  }
  
  /**
   * Reconcile the members of an automatic Group.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EntityInterface $entity  Group
   */
  
  protected function reconcileAutomaticGroup(EntityInterface $entity) {
    // In order to handle very large groups, we can't pull the full set of
    // members into memory. Instead, we use the paginated iterator. This
    // involves two passes.
    
    // First, we pull the current members of the Group, and for each member
    // make sure they are still eligible.
    
    $iterator = $this->getMembers($entity->id, null, false, false, false);
    
    foreach($iterator as $k => $groupMember) {
      if(!empty($entity->cou_id)) {
        if($entity->group_type == GroupTypeEnum::ActiveMembers) {
          // If $groupMember is not an active member of cou_id, remove the membership
          if(!$this->Cous->PersonRoles->hasActive($groupMember->person_id, $entity->cou_id)) {
            $this->llog('rule', "AR-PersonRole-2 Reconciliation removing membership for Person ID " . $groupMember->person_id . " from Group ID " . $groupMember->group_id);
            $this->GroupMembers->delete($groupMember);
          }
        } else {
          // If $groupMember does not have any role in cou_id, remove the membership
          if(!$this->Cous->PersonRoles->hasAny($groupMember->person_id, $entity->cou_id)) {
            $this->llog('rule', "AR-PersonRole-1 Reconciliation removing membership for Person ID " . $groupMember->person_id . " from Group ID " . $groupMember->group_id);
            $this->GroupMembers->delete($groupMember);
          }
        }
      } else {
        // Look at the Person record
        $person = $this->GroupMembers->People->get($groupMember->person_id);
        
        if($entity->group_type == GroupTypeEnum::ActiveMembers) {
          if(!$person || !$person->isActive()) {
            $this->llog('rule', "AR-Person-2 Reconciliation removing membership for Person ID " . $groupMember->person_id . " from Group ID " . $groupMember->group_id);
            $this->GroupMembers->delete($groupMember);
          }
        } else {
          if(!$person || $person->status == StatusEnum::Archived) {
            $this->llog('rule', "AR-Person-1 Reconciliation removing membership for Person ID " . $groupMember->person_id . " from Group ID " . $groupMember->group_id);
            $this->GroupMembers->delete($groupMember);
          }
        }
      }
    }
    
    // Second, we pull the members of the CO/COU and make sure they have the
    // correlated membership.
    
    if(!empty($entity->cou_id)) {
      // This won't return roles in Archived status, but returns all others
      $iterator = $this->Cous->PersonRoles->getMembers($entity->cou_id);
      
      foreach($iterator as $k => $personRole) {
        if($entity->group_type == GroupTypeEnum::AllMembers
           || $personRole->isActive()) {
          // Check if the Person is already a member of the Group
          if(!$this->GroupMembers->isMember($entity->id, $personRole->person_id)) {
            // Add the membership
            
            $membership = [
              'group_id'  => $entity->id,
              'person_id' => $personRole->person_id
            ];
            
            $gmEntity = $this->GroupMembers->newEntity($membership);
            
            $this->GroupMembers->saveOrFail($gmEntity);
            $this->llog('rule', ($entity->group_type == GroupTypeEnum::AllMembers ? "AR-PersonRole-2" : "AR-PersonRole-1") . " Reconciliation added automatic membership for Person ID " . $personRole->person_id . " to Group ID " . $entity->id);
          }
        }
      }
    } else {
      $iterator = $this->People->getMembers($entity->co_id);
      
      foreach($iterator as $k => $person) {
        if($entity->group_type == GroupTypeEnum::AllMembers
           || $person->isActive()) {
          // Add the membership
          
          $membership = [
            'group_id'  => $entity->id,
            'person_id' => $person->id
          ];
          
          $entity = $this->GroupMembers->newEntity($membership);
          
          $this->GroupMembers->saveOrFail($entity);
          $this->llog('rule', ($entity->group_type == GroupTypeEnum::AllMembers ? "AR-Person-2" : "AR-Person-1") . " Reconciliation added automatic membership for Person ID " . $person->id . " to Group ID " . $entity->id);
        }
      }
    }

    return;
  }
  
  /**
   * Reconcile the members of a nested Group.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EntityInterface $entity  Group
   */
  
  protected function reconcileNestedMemberships(EntityInterface $entity) {
    // When a new GroupNesting is saved, we're called on the _target_.

    // Start by pulling the Group Nestings for this Group. We'll only go one level deep.
    
    $groupNestings = $this->GroupNestings->find()
                          ->where(['GroupNestings.target_group_id' => $entity->id])
                          ->all();
    
    // First iterate through the current members of the target group (who are
    // members due to one of the nestings) and recheck their eligibility. This
    // will remove anyone who is no longer eligible.
    
    // We convert $groupNestings to an array for the outer loop to ensure we don't
    // have conflicts with the next loop
    foreach($groupNestings->toArray() as $groupNesting) {
      $iterator = $this->getMembersViaNesting($groupNesting->target_group_id, $groupNesting->id);

      foreach($iterator as $k => $targetGroupMember) {
        $this->GroupMembers->syncNestedMembership($targetGroupMember->person_id,
                                                  $entity);
      }
    }
  
    // Next, for each nesting iterate through the members of that nesting and
    // recheck their eligibility. This will add in anyone who is now eligible.
    // (We do this second since the first iteration might shrink the population
    // to check here.)
    
    foreach($groupNestings->toArray() as $groupNesting) {
      $iterator = $this->getMembers($groupNesting->group_id, null, false, false, false);
      
      foreach($iterator as $k => $sourceGroupMember) {
        $this->GroupMembers->syncNestedMembership($sourceGroupMember->person_id,
                                                  $entity);
      }
    }
    
    return true;
  }

  /**
   * Application Rule to determine if both the Parent and Child are Standard Groups.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleAreStandard($entity, $options) {
    // We check that both child and parent are Standard Groups.

    if(!empty($entity->parent_id)) {
      if($entity->group_type != GroupTypeEnum::Standard) {
        return __d('error', 'Groups.child.standard');
      }

      $parentEntity = $this->get($entity->parent_id);

      if($parentEntity->group_type != GroupTypeEnum::Standard) {
        return __d('error', 'Groups.parent.standard');
      }
    }
    
    return true;
  }

  /**
   * Application Rule to determine if the Fully Qualified Group name is unique.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */

  public function ruleFQNameUnique($entity, $options) {
    // Check that the proposed fully qualified name is not already in use.
    // This basically just means checking other Groups with the same parent_id,
    // or if there is no parent_id the same co_id.

    // name is indexed with co_id, so we'll always include co_id in the query
    // even though it's redundant when parent_id is also specified. Annoying, nulls
    // are specified differently.

    // Only Standard Groups are namespaced
    if($entity->group_type == GroupTypeEnum::Standard) {
      $whereClause = [
        'co_id' => $entity->co_id,
        'name' => $entity->name
      ];

      if($entity->parent_id) {
        $whereClause['parent_id'] = $entity->parent_id;
      } else {
        $whereClause['parent_id IS'] = null;
      }

      $group = $this->find()
                    ->where($whereClause)
                    ->first();
      
      if(!empty($group)) {
        return __d('error', 'Groups.name.inuse', [$group->id, $group->parent_id]);
      }
    }

    return true;
  }

  /**
   * Application Rule to determine if the Group name has an invalid syntax.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */

  public function ruleNameSyntax($entity, $options) {
    // We don't allow (1) colons anywhere in the name, because this can create conflicts
    // with fully qualified names (as used in Owners Group name construction) or (2) a
    // name consisting of exactly "CO" (since that prefix is reserved for special Groups,
    // and it would be possible to create a CO:foo fully qualified name.).

    if($entity->group_type == GroupTypeEnum::Standard) {
      if(str_contains($entity->name, ":")) {
        return __d('error', 'Groups.name.colon');
      }

      if($entity->name == "CO") {
        return __d('error', 'Groups.name.co');
      }
    }

    return true;
  }

  /**
   * Application Rule to determine if the group has children.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleHasChildren($entity, $options) {
    $count = $this->find('all')
                  ->where(['parent_id' => $entity->id])
                  ->count();
    
    if($count > 0) {
      return __d('error', 'Groups.children', [$count]);
    }

    return true;
  }
  
  /**
   * Application Rule to determine if the group is nested.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleIsNested($entity, $options) {
    // We check that the subject group is either a source or a target, but
    // only if the $entity status is Suspended.
    
    if($entity->status == SuspendableStatusEnum::Suspended) {
      $count = $this->GroupNestings->find('all')
                                   ->where([
                                     'OR' => [
                                       'GroupNestings.group_id' => $entity->id,
                                       'GroupNestings.target_group_id' => $entity->id
                                     ]
                                   ])
                                   ->count();
      
      if($count > 0) {
        return __d('error', 'Groups.nested');
      }
    }
    
    return true;
  }
  
  /**
   * Application Rule to determine if a non-modifiable Owners Group field was modified.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleOwnerIsModified($entity, $options) {
    if(!$entity->isOwners()) {
      return true;
    }

    // We'll check the field specified in $options['errorField']
    if($entity->isDirty($options['errorField'])) {
      return __d('error', 'fields.read_only', $options['errorField']);
    }

    return true;
  }

  /**
   * Application Rule to determine if the Group Type was changed.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleTypeIsModified($entity, $options) {
    if($entity->isDirty('group_type')
       // For some reason the field is flagged as dirty on update
       // (presumably when we update owners_group_id in localAfterSave)
       // so we need to compare the original value
       && $entity->get('group_type') != $entity->getOriginal('group_type')) {
      return __d('error', 'fields.read_only', 'group_type');
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
          'LOWER(Groups.name) LIKE' => '%' . strtolower($t) . '%'
        ]
      ];
    }

    return $this->find()
                ->where($whereClause)
                ->andWhere(['Groups.co_id' => $coId])
                ->orderBy(['Groups.name'])
                ->limit($limit)
                ->all();
  }

  /**
   * Update the attributes of an Owners Group, based on the attributes of the
   * related primary Group.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Group  $group  Owners Group entity
   * @return int            Owners Group ID
   * @throws PersistenceFailedException
   */

  public function updateOwnersGroup($group): int {
    if($group->isOwners()) {
      throw new \InvalidArgumentException(__d('error', 'Groups.owners.desc.affix'));
    }

    $ownerGroup = $this->get($group->owners_group_id);

    // We synchronize name, description, and status. Because special Groups
    // (including Owners Groups) exist in a flat structure, the name must be
    // fully qualified.
    $ownerGroup->name = 'CO:owners:' . $this->getFullyQualifiedName($group);
    $ownerGroup->description = $group->name . " Owners";
    $ownerGroup->status = $group->status;

    // We need to disable rule checking since these fields are not normally modifiable
    $this->saveOrFail($ownerGroup, ['checkRules' => false]);

    return $ownerGroup->id;
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
    
    $validator->add('cou_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('cou_id');
    
    $this->registerStringValidation($validator, $schema, 'name', true);
    
    $this->registerStringValidation($validator, $schema, 'description', false);
    
    $validator->add('open', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('open');
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    
    $validator->add('group_type', [
      'content' => ['rule' => ['inList', GroupTypeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('group_type');
    
    $validator->add('nesting_mode_all', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('nesting_mode_all');

    // This will be null for Owner Groups, which don't have further Owner Groups
    $validator->add('owner_group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('owner_group_id');

    
    return $validator; 
  }
}