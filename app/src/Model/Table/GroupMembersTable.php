<?php
/**
 * COmanage Registry Group Members Table
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

use App\Model\Entity\GroupMember;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\GroupTypeEnum;
use \App\Lib\Enum\SuspendableStatusEnum;

class GroupMembersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\SearchFilterTrait;

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
    $this->addBehavior('Timezone');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);
    
    // Define associations
    $this->belongsTo('GroupNestings');
    $this->belongsTo('Groups');
    $this->belongsTo('People');
    
    $this->setDisplayField('id');
    
    $this->setPrimaryLink(['group_id', 'person_id']);
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');
    
    $this->setEditContains(['Groups', 'People.PrimaryName']);
    $this->setViewContains(['Groups', 'People.PrimaryName']);

    $this->setIndexContains([
      'GroupNestings' => 'Groups',
      'Groups', 
      'People.PrimaryName'
    ]);

    $this->setAutoViewVars([
      'cosettings' => [
        'type' => 'auxiliary',
        'model' => 'CoSettings'
      ],
      'types' => [
        'type' => 'auxiliary',
        'model' => 'Types'
      ]
    ]);

    $this->setPermissions([
  // XXX update for couAdmins, group owners, etc
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
        'deleted' =>  ['platformAdmin', 'coAdmin']
      ]
    ]);

    $this->setFilterConfig([
       'family' => [
           'type' => 'string',
           'model' => 'People.Names',
           'active' => true,
           'order' => 2
       ],
       'given' => [
           'type' => 'string',
           'model' => 'People.Names',
           'active' => true,
           'order' => 1
       ],
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
    // AR-GroupMember-1 A Person cannot have two manually created GroupMember
    // records for the same Group.
    $rules->addCreate([$this, 'ruleIsGroupMember'],
                      'isGroupMember',
                      ['errorField' => 'person_id']);
    
    return $rules;
  }

  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Person $entity Entity to generate display field for
   * @return string         Display field
   */

  public function generateDisplayField(GroupMember $entity): ?string {
    // Pull the group and person information to build a more useful display string.
    // Note because there is no setAddContains() (this is probably the only place
    // where it would be useful if it were even conceptually a thing) we can't assume
    // we have any entity information here.
    
    if(!empty($entity->person->primary_name)) {
      return __d('field', 'group_membership', [$entity->person->primary_name->full_name, $entity->group->name]);
    }

    return null;
  }
  
  /**
   * Determine if the specified Person is a member of the specified Group.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int     $groupId       Group ID
   * @param  int     $personId      Person ID
   * @param  bool    $direct        If true, the Person must be a direct member of the Group
   * @param  bool    $checkValidity If true, check valid_from and valid_through dates
   * @return bool                   true if Person is a member of Group, false otherwise
   */
  
  public function isMember(int  $groupId, 
                           int  $personId,
                           bool $direct=false,
                           bool $checkValidity=true): bool {
    // This function is here (instead of GroupsTable) because we need it for
    // rule validation on new GroupMember save.

    $query = $this->find()
                  ->where(['group_id' => $groupId])
                  ->where(['person_id' => $personId]);

    if($direct) {
// XXX need to add pipelines here eventually
      $query = $query->where(fn(QueryExpression $exp, Query $query) => $exp->isNull('group_nesting_id'));
    }

    if($checkValidity) {
      $queryCheckValidityExp = $this->checkValidity($query);
      $query = $query->where($queryCheckValidityExp);
    }

    $count = $query->count();
    
    // When !$direct, we could get more than one row back
    return ($count > 0);
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
    $person = $this->People->get($entity->person_id, contain: ['PrimaryName']);
    $group = $this->Groups->get($entity->group_id);
    
    $action = null;
    $langKey = '';
    $langKeySuffix = '';
    $commentParams = [
      (!empty($person->primary_name) ? $person->primary_name->full_name : "?"),
      $group->name
    ];
    
    if(!empty($entity->group_nesting_id)) {
      // We need to allow retrieval of archived records since we might be called
      // after the GroupNesting was deleted
      $nesting = $this->GroupNestings->get($entity->group_nesting_id, 
                                           contain: ['Groups'],
                                           archived: true);
      
      $langKeySuffix = '.nesting';
      $commentParams[] = $nesting->group->name;
      $commentParams[] = $entity->group_nesting_id;
    }
    
    if($entity->isNew()) {
      $action = ActionEnum::GroupMemberAdded;
      $langKey = 'GroupMembers.added';
    } elseif($entity->get('deleted')) {
      $action = ActionEnum::GroupMemberDeleted;
      $langKey = 'GroupMembers.deleted';
    } else {
      $action = ActionEnum::GroupMemberEdited;
      $langKey = 'GroupMembers.edited';
      $commentParams[] = $this->changesToString($entity);
    }
    
    $comment = __d('result', $langKey . $langKeySuffix, $commentParams);
    
    $this->recordHistory($entity, $action, $comment);

    // On save, we pull any nestings where this Group is the source and sync
    // memberships for the target. (Any membership changes should then recurse.)
    
    $groupNestings = $this->GroupNestings->find()
                          ->where(['GroupNestings.group_id' => $entity->group_id])
                          ->contain(['TargetGroups'])
                          ->all();
    
    foreach($groupNestings as $groupNesting) {
      $this->syncNestedMembership($entity->person_id, $groupNesting->target_group);
    }
    
    return true;
  }
  
  /**
   * Request provisioning.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int                      $id                   This table's entity ID to provision
   * @param  ProvisioningContextEnum  $context              Context in which provisioning is being requested
   * @param  int                      $provisioningTargetId If set, the Provisioning Target ID to request provisioning for (otherwise all)
   * @param  Job                      $job                  If called from a Job, the current Job entity
   * @throws InvalidArgumentException
   */

  public function requestProvisioning(
    int     $id,
    string  $context,
    ?int    $provisioningTargetId=null,
    ?Job    $job=null,
  ) {
    // For GroupMembers we need to request provisioning on both the Group and the Person.

    $gm = $this->get($id);

    $this->People->requestProvisioning(
      id: $gm->person_id,
      context: $context,
      provisioningTargetId: $provisioningTargetId,
      job: $job
    );

    $this->Groups->requestProvisioning(
      id: $gm->group_id,
      context: $context,
      provisioningTargetId: $provisioningTargetId,
      job: $job
    );
  }

  /**
   * Application Rule to determine if the Person is already a member of the Group.
   *
   * @since  COmanage Registyr v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleIsGroupMember($entity, $options) {
    // We don't allow the same Person to be manually added to the same Group
    // twice, though they could have a separate membership via Nestings or
    // EIS Pipelines.
    
    if($this->isMember($entity->group_id, $entity->person_id, true, false)) {
      // Pull the Person and Group name for the error message.
      $person = $this->People->get($entity->person_id, contain: ['PrimaryName']);
      $group = $this->Groups->get($entity->group_id);
      
      return __d('error', 'exists.GroupMember', [$person->primary_name->full_name, $group->name]);
    }
    
    return true;
  }
  
  /**
   * Sync an automatic group membership.
   *
   * @since  COmanage Registry v5.0.0
   * @param  GroupTypeEnum  $groupType    Type of Group to sync membership
   * @param  int            $couId        COU ID, or null for CO level groups
   * @param  int            $personId     Person ID of member
   * @param  bool           $eligible     Whether the person should be in the group
   * @param  bool           $provision    Whether to run provisioners
   * @throws InvalidArgumentException
   */

  public function syncAutomaticMembership(string  $groupType,
                                          ?int    $couId,
                                          int     $personId,
                                          bool    $eligible,
                                          bool    $provision=true) {
    // Find the CO from the Person
    $coId = $this->People->findCoForRecord($personId);
    
    if(!$coId) {
      throw new \InvalidArgumentException(__d('error', 'notfound', __d('controller', 'People')));
    }
    
    // Find the requested group
    $targetGroup = $this->Groups->find()
                                ->where([
                                  'co_id'      => $coId,
                                  'group_type' => $groupType,
                                  // $couId will be null for CO level groups
                                  'cou_id IS'  => $couId
                                ])
                                ->firstOrFail();
    
    // Is $personId already a member? We don't use $this->isMember because we
    // may delete this record, below.
    
    $memberEntity = $this->find()->where(['group_id' => $targetGroup->id, 'person_id' => $personId])->first();
    $isMember = !empty($memberEntity);
    
    $hAction = null;
    
    if($eligible && !$isMember) {
      // Add a membership
      
      $membership = [
        'group_id'  => $targetGroup->id,
        'person_id' => $personId
      ];
      
      $entity = $this->newEntity($membership);
      
// XXX need to make sure $provision is honored here
      $this->saveOrFail($entity, ['provision' => $provision]);
      $this->llog('rule', "Added automatic membership for Person ID $personId to Group ID " . $targetGroup->id);
    } elseif(!$eligible && $isMember) {
      // Remove the membership
      
      $this->delete($memberEntity);
      $this->llog('rule', "Removed automatic membership for Person ID $personId from Group ID " . $targetGroup->id);
    }
    // else nothing to do
  }
  
  public function syncNestedMembership(int  $personId,
                                       \Cake\Datasource\EntityInterface $targetGroup,
                                       //\Cake\ORM\ResultSet $groupNestings,
                                       //bool $eligible, // XXX still needed?
                                       bool $provision=true) {
    // The operation we perform (add or delete) may be inverted by the CoGroupNesting
    // configuration.

    // Our pseudologic for what to do here is as follows:
    //  t = isMemberOf($targetGroup)
    //  t' = shouldBeMemberOf($targetGroup)
    //
    //  if(t && !t') addTo($targetGroup)
    //  elseif(!t && t') removeFrom($targetGroup)

    // $coPersonId should be a member of $targetGroup if any of the following are true
    // (1) Nesting/Negate = false
    //     AND TargetGroup/Mode = any
    //     AND $sourceMember
    //     AND not a member of any source group for target where Nesting/Negate = true
    // (2) Nesting/Negate = false
    //     AND TargetGroup/Mode = all
    //     AND member of all source groups for target
    //     AND not a member of any source group for target where Nesting/Negate = true
    // (3) Nesting/Negate = true
    //     AND TargetGroup/Mode = any
    //     AND !$sourceMember
    //     AND member of any non-negated source group for target
    //     AND not a member of any source group for target where Nesting/Negate = true
    // (4) Nesting/Negate = true
    //     AND TargetGroup/Mode = all
    //     AND !$sourceMember
    //     AND member of all non-negated source groups for target
    //     AND not a member of any source group for target where Nesting/Negate = true
    
    // As of v5.0.0, we clarify that if a Person is a member of multiple source
    // Groups that convey nested membership into the target Group, we will create
    // one membership _for each nesting_, regardless of the nesting mode. ie:
    // if nesting_mode_all is true, then once the Person is a member of all
    // source groups, they will receive the same number of memberships.
    
    // Pull the set of nestings for the target group.
    
    $groupNestings = $this->GroupNestings->find()
                          ->where(['GroupNestings.target_group_id' => $targetGroup->id])
                          ->all();
    
    // We convert $groupNestings to an array to avoid any confusion with the
    // nested foreach() loops. Note that (unlike v4) we do not need to check for
    // suspended Group status here since Groups cannot be suspended if they are
    // nested (AR-Group-2), and cannot be nested if they are suspended (AR-GroupNesting-1).
    // (This prevents admins from inadvertantly messing things up.)
    foreach($groupNestings->toArray() as $groupNesting) {
      $shouldBe = false;      // Should $person be a member of $targetGroup?
      $negated = false;       // $person is ineligible for $targetGroup due to any negative membership
      $isAny = false;         // $person is a member of any (positive) source group for $targetGroup
      $isAll = false;         // $person is a member of all (positive) source groups for $targetGroup
      $isCurrent = false;     // $person is a member of $targetGroup due to $groupNesting
      
      // Walk all nestings to determine negation and current memberships. To track
      // $isAll, we need at least one positive membership. In other words, a Target
      // Group with only one Nesting, and that one Nesting is negative, does not
      // automatically make everybody else a member.
      $pAvail = 0;    // Available positive memberships
      $pCount = 0;    // Actual positive memberships

      // Don't conflict with the outer foreach...
      foreach($groupNestings->toArray() as $n) {
        if($n->negate) {
          // If this is the current nesting we don't need to look anything up
          //if((($n->id == $groupNesting->id) && $sourceMember)
            // ||
          if($this->isMember($n->group_id, $personId)) {
            $negated = true;
          }
        } else {
          $pAvail++;

          if($this->isMember($n->group_id, $personId)) {
            $isAny = true;
            $pCount++;
          }
        }
      }

      // We need at least one positive group to count as ALL
      $isAll = ($pAvail > 0 && $pCount == $pAvail);
      
      if(!$negated && !$targetGroup->nesting_mode_all && $isAny) {
        // Case (1) and (3)
        $shouldBe = true;
      } elseif(!$negated && $targetGroup->nesting_mode_all && $isAll) {
        // Case (2) and (4)
        $shouldBe = true;
      }

      // Is $personId already a member via this Nesting?
      $memberEntity = $this->find()
                           ->where([
                             'group_id'         => $targetGroup->id,
                             'person_id'        => $personId,
                             'group_nesting_id' => $groupNesting->id
                           ])
                           ->first();
      $isCurrent = !empty($memberEntity);

      if(!$isCurrent && $shouldBe) {
        // Add a GroupMember record associated with this Nesting
        
        $membership = [
          'group_id'          => $targetGroup->id,
          'person_id'         => $personId,
          'group_nesting_id'  => $groupNesting->id
        ];
        
        $entity = $this->newEntity($membership);
        
  // XXX need to make sure $provision is honored here
        $this->saveOrFail($entity, ['provision' => $provision]);
        $this->llog('rule', "Added nested membership for Person ID $personId to Group ID " . $targetGroup->id . " (Group Nesting ID " . $groupNesting->id . ")");
      } elseif($isCurrent && !$shouldBe) {
        // Remove the GroupMember associated with this Nesting
        
        $this->delete($memberEntity);
        $this->llog('rule', "Removed nested membership for Person ID $personId from Group ID " . $targetGroup->id . " (Group Nesting ID " . $groupNesting->id . ")");
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
    
    $validator->add('group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('group_id');
    
    $validator->add('person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('person_id');
    
    $validator->add('valid_from', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('valid_from');

    $validator->add('valid_through', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('valid_through');
    
    return $validator; 
  }


  /**
   * Save attributes for a person.
   *
   * This method is responsible for saving attributes for a given person.
   * It constructs an entity with the provided fields and ensures the entity
   * is saved successfully.
   *
   * @param int $personId The ID of the person to save attributes for.
   * @param array $fields An array of fields to update, where each field
   *                        includes an enrollment attribute and its value.
   *
   * @return GroupMember The saved entity representing the person's role.
   *
   */
  public function saveAttributeCollectorPetitionAttributes(int $personId, array $fields): GroupMember
  {
    $member = [
      'person_id'     => $personId,
    ];

    foreach($fields as $fld) {
      $member[$fld->enrollment_attribute->attribute] = $fld->value;
    }

    if(!isset($member['group_id'])) {
      return null;
    }

    return $this->saveOrFail($this->newEntity($member));
  }
}