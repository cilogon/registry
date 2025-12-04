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

use App\Model\Entity\Person;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\AuthenticatorStatusEnum;
use \App\Lib\Enum\GroupTypeEnum;
use \App\Lib\Enum\ProvisioningEligibilityEnum;
use \App\Lib\Enum\StatusEnum;
use \App\Lib\Enum\SuspendableStatusEnum;
use \App\Lib\Util\PaginatedSqlIterator;
use \App\Lib\Util\StringUtilities;

class PeopleTable extends Table {
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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Primary);
    
    // Define associations
    $this->belongsTo('Cos');
    
    $this->hasOne('PrimaryName')
         ->setClassName('Names')
         // We have to explicitly set the foreign key here so that the relations
         // ManagerPeople and SponsorPeople (in PersonRolesTable) get the correct
         // foreign key into Names table when pulling data via contains (as in
         // marshalProvisioningData(), below)
         ->setForeignKey('person_id')
         ->setConditions(['PrimaryName.primary_name' => true]);
    $this->hasMany('Names')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Addresses')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('AdHocAttributes')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('ApplicationStates')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('AuthenticatorStatuses')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('EmailAddresses')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('ExternalIdentities')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    // For "adopted" External Identities
    $this->hasMany('ExtIdentitySourceRecords')
         ->setForeignKey('adopted_person_id')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('GroupMembers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('HistoryRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Identifiers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('JobHistoryRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('ActorNotifications')
         ->setClassName('Notifications')
         ->setForeignKey('actor_person_id')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('ResolverNotifications')
         ->setClassName('Notifications')
         ->setForeignKey('resolver_person_id')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('SubjectNotifications')
         ->setClassName('Notifications')
         ->setForeignKey('subject_person_id')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('PersonRoles')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Petitions')
         ->setDependent(true)
         ->setCascadeCallbacks(true)
         ->setForeignKey('enrollee_person_id');
    $this->hasMany('Pronouns')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('ProvisioningHistoryRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('TelephoneNumbers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Urls')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
// XXX can we change this to Name?
    $this->setDisplayField('id');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    $this->setAllowLookupPrimaryLink(['provision']);
    $this->setAllowUnkeyedPrimaryLink(['pick']);
    
// XXX does some of this stuff really belong in the controller?
    $this->setEditContains([
      'PrimaryName',
      'Addresses',
      'AdHocAttributes',
      'EmailAddresses',
      'Identifiers',
      'Names',
      //'PersonRoles',
      'Pronouns',
      'TelephoneNumbers',
      'Urls'
    ]);
    $this->setIndexContains(['PrimaryName']);
    $this->setViewContains([
      'PrimaryName',
      'Addresses',
      'AdHocAttributes',
      'EmailAddresses',
      'Identifiers',
      'Names',
      //'PersonRoles',
      'Pronouns',
      'TelephoneNumbers',
      'Urls'
    ]);
    $this->setPickerContains([
      'EmailAddresses',
      'Identifiers',
      'PrimaryName',
    ]);

    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
        'class' => 'StatusEnum'
      ],
      'types' => [
        'type' => 'type',
        'attribute' => 'Names.type'
      ],
      'cous' => [
        'type' => 'select',
        'model' => 'Cous'
      ],
    ]);
    
    // XXX expand/revise this as needed to work best with looking up the related models
    $this->setFilterConfig([
      'family' => [
        'type' => 'string',
        'model' => 'Names',
        'active' => true,
        'order' => 3
      ],
      'middle' => [
        'type' => 'string',
        'model' => 'Names',
        'active' => true,
        'order' => 2
      ],
      'given' => [
        'type' => 'string',
        'model' => 'Names',
        'active' => true,
        'order' => 1
      ],
      'mail' => [
        'type' => 'string',
        'model' => 'EmailAddresses',
        'active' => true,
        'order' => 5
      ],
      'identifier' => [
        'type' => 'string',
        'model' => 'Identifiers',
        'active' => true,
        'order' => 4
      ],
      'timezone' => [
        'type' => 'field',
        'model' => 'People',
        'active' => false,
        'order' => 99
      ],
      'cou_id' => [
        'type' => 'select',
        'model' => 'PersonRoles.Cous',
        'active' => true,
        'order' => 7
      ],
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
          'PersonRoles' => ['index'],
          'ExternalIdentities' => ['index'],
        ],
        // What model will have a counter-badge after the tab title
        'counter' => ['PersonRoles', 'ExternalIdentities']
      ]
    );

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
// See also CFM-126
      'entity' => [
        'delete'    => ['platformAdmin', 'coAdmin'],
        'edit'      => ['platformAdmin', 'coAdmin'],
        'provision' => ['platformAdmin', 'coAdmin'],
        // selfMember removed pending further discussion around permissions
        // 'view'      => ['platformAdmin', 'coAdmin', 'selfMember']
        'view'      => ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
        'pick' =>     ['platformAdmin', 'coAdmin'],
      ],
      // Related models whose permissions we'll need, typically for table views
      'related' => [
        'table' => [
          'Addresses',
          'AdHocAttributes',
          'AuthenticatorStatuses',
          'Names',
          'EmailAddresses',
          'ExternalIdentities',
          'HistoryRecords',
          'IdentifierAssignments',
          'Identifiers',
          'Notifications',
          'PersonRoles',
          'ProvisioningTargets',
          'TelephoneNumbers',
          'Urls'
        ],
      ]
    ]);
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
  
  public function beforeDelete(\Cake\Event\Event $event, $entity, \ArrayObject $options) {
// XXX we are effectively reimplementing expunge logic here, maybe move it to
//     a new protected PeopleTable::expunge() function (called only from here)?
    // If we were only dealing with hard delete, we wouldn't need implementedEvents()
    // below, because ChangelogBehavior ignores hard deletes.

    // Whether soft or hard deleting, we need to remove Automatic Group Memberships
    // before we delete the Person, or lookups performed while managing those
    // group memberships will fail.

    $this->reconcileCoMembersGroupMemberships(entity: $entity, deleted: true);

    if(isset($options['useHardDelete']) 
       && $options['useHardDelete']
       && $entity->id > 0) {
      // Hard delete, so clear out any foreign keys pointing to this Person.
      // This will also clear foreign keys from archived changelog records.
      
      $this->PersonRoles->updateAll(
        [ 'manager_person_id' => null ],
        [ 'manager_person_id' => $entity->id ]
      );

      $this->PersonRoles->updateAll(
        [ 'sponsor_person_id' => null ],
        [ 'sponsor_person_id' => $entity->id ]
      );

      // Manually delete any names, since the validation rules will fail on cascade.
      $this->Names->deleteAll(
        [ 'person_id' => $entity->id ]
      );
    } else {
      // Manually delete any names, since the validation rules will fail on cascade.
      // Since this isn't a hard delete we can't use deleteAll since we need
      // ChangelogBehavior to fire.

      $names = $this->Names->find()->where(['person_id' => $entity->id])->all();

      foreach($names as $n) {
        $this->Names->delete($n, ['checkRules' => false]);
      }
    }

    $event->setResult(true);
  }

  /**
   * Customized finder for the Index Population View
   *
   * @param   Query  $query    Cake ORM Query
   *
   * @return Query Cake ORM Query
   * @since  COmanage Registry v5.0.0
   */
  public function findIndexed(Query $query): Query {
    return $query->select([
                            'People.id',
                            'PrimaryName.given',
                            'PrimaryName.family',
                            'People.status',
                            'People.created',
                            'People.modified',
                            'People.timezone',
                            'People.date_of_birth'
                          ])
                  ->distinct();
  }

  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Person $entity Entity to generate display field for
   * @return string         Display field
   */
  
  public function generateDisplayField(Person $entity): string {
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
      'status IS NOT' => StatusEnum::Archived
    ];
    
    return new PaginatedSqlIterator($this, $conditions);
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
    
  public function localAfterSave(\Cake\Event\EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $options): bool {
    $this->recordHistory($entity);
    
    // XXX implement this eventually?
    //$provision = (isset($options['provision']) ? $options['provision'] : true);
    
    if(!$entity->deleted) {
      // If the entity was deleted we handled this in beforeDelete, above
      $this->reconcileCoMembersGroupMemberships($entity);
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

    $ret['data'] = $this->get($id,
      // We need archives for handling deleted records
      archived: 'true',
      contain: [
        'PrimaryName' => [ 'Types' ],
        'Addresses' => [ 'Types' ],
        'AdHocAttributes',
        'EmailAddresses' => [ 'Types' ],
/* External Identities are not provisionable
        'ExternalIdentities' => [
          'Addresses' => [ 'Types' ],
          'AdHocAttributes',
          'EmailAddresses' => [ 'Types' ],
          'ExternalIdentityRoles' => [
            'Addresses' => [ 'Types' ],
            'AdHocAttributes',
            'TelephoneNumbers' => [ 'Types' ],
            'Types'
          ],
          'Identifiers' => [ 'Types' ],
          'Names' => [ 'Types' ],
          'Pronouns',
          'TelephoneNumbers' => [ 'Types' ],
          'Urls' => [ 'Types' ]
        ],*/
        'GroupMembers' => [ 'Groups' ],
        'Identifiers' => [ 'Types' ],
        'Names' => [ 'Types' ],
        'PersonRoles' => [
          'Addresses' => [ 'Types' ],
          'AdHocAttributes',
          'Cous',
          'ManagerPeople' => [ 'PrimaryName' ],
          'SponsorPeople' => [ 'PrimaryName' ],
          'TelephoneNumbers' => [ 'Types' ],
          'Types'
        ],
        'Pronouns',
        'TelephoneNumbers' => [ 'Types' ],
        'Urls' => [ 'Types' ]
      ]
    );

    // Provisioning Eligibility is
    // - Deleted if the changelog deleted flag is true OR status is Archived
    // - Eligible if entity->isActive()
    // - Ineligible otherwise

    // Most statuses don't provision anything
    $ret['eligibility'] = ProvisioningEligibilityEnum::Deleted;

    // We filter various attributes depending on the status of the record.

    if($ret['data']->deleted || $ret['data']->status == StatusEnum::Archived) {
      $ret['eligibility'] = ProvisioningEligibilityEnum::Deleted;

      // For deleted or archived records, we remove everything except names
      // and identifiers, which might be useful for error reporting and record keeping.
      // Unlike Ineligible, we *don't* keep the All Members groups.

      $ret['data']->ad_hoc_attributes = [];
      $ret['data']->addresses = [];
      $ret['data']->email_addresses = [];
      $ret['data']->external_identities = [];
      $ret['data']->group_members = [];
      $ret['data']->group_owners = [];
      $ret['data']->person_roles = [];
      $ret['data']->pronouns = [];
      $ret['data']->telephone_numbers = [];
      $ret['data']->urls = [];
    } elseif($ret['data']->isActive()) {
      $ret['eligibility'] = ProvisioningEligibilityEnum::Eligible;

      // For Eligible, we still need to remove Person Roles and Group Memberships
      // that are invalid, and Identifiers that are suspended.

      $personRoles = [];

      foreach($ret['data']->person_roles as $pr) {
        if($pr->isValid()) {
          $personRoles[] = $pr;
        }
      }

      $ret['data']->person_roles = $personRoles;

      $groupMembers = [];

      foreach($ret['data']->group_members as $gm) {
        if($gm->isValid()) {
          $groupMembers[] = $gm;
        }
      }

      $ret['data']->group_members = $groupMembers;

      $identifiers = [];

      foreach($ret['data']->identifiers as $ident) {
        if($ident->status == SuspendableStatusEnum::Active) {
          $identifiers[] = $ident;
        }
      }

      $ret['data']->identifiers = $identifiers;

      // Pull the set of available Authenticator Plugins and query them
      // for additional attributes to add to the provisioning data.

      $Authenticators = TableRegistry::getTableLocator()->get('Authenticators');

      $authenticators = $Authenticators->find()
                                       ->where([
                                        'co_id' => $ret['data']->co_id,
                                        'status' => SuspendableStatusEnum::Active
                                       ])
                                       // Plugins expect their configuration as part of
                                       // the status call
                                       ->contain($Authenticators->getPluginRelations())
                                       ->all();

      foreach($authenticators as $authenticator) {
        // Start with the Authenticator Status for this Authenticator for this Person.
        // Only Authenticators in Active status are eligible for provisioning.

        $status = $Authenticators->AuthenticatorStatuses->getForPerson($authenticator, $id);

        if($status->status == AuthenticatorStatusEnum::Active) {
          // Now ask the Plugin for the Provisioning data. Note a given Plugin may be
          // instantiated more than once, in which case we'll call marshallProvisioningData
          // more than once (with different configuration information). We'll need to
          // merge the results together.

          $APlugin = TableRegistry::getTableLocator()->get($authenticator->plugin);

          // We expect an array of entities rather than a ResultSet (which would be
          // easily obtainable by a find()) in order to give Plugins more flexibility
          // in how they assemble the records.

          $entityData = $APlugin->marshalProvisioningData($authenticator, $id);

          // Determine the entity name in order to populate the provisiosing data.
          // We can calculate this because (unlike other Plugin types) there are
          // naming conventions for Authenticators.

          $entityKey = Inflector::tableize(StringUtilities::PluginModel($Authenticators->authenticatorEntityName($authenticator->plugin)));

          if(!empty($ret['data']->$entityKey)) {
            // We already have data from a previous instantiation of the same plugin,
            // merge the results together
            $ret['data']->$entityKey = arary_merge($ret['data']->$entityKey, $entityData);
          } else {
            $ret['data']->$entityKey = $entityData;
          }
        }
      }
    } else {
      $ret['eligibility'] = ProvisioningEligibilityEnum::Ineligible;
      // For Ineligible records, we remove the items that may be used for eligibilities,
      // specifically group memberships/ownerships and PersonRoles. We leave the
      // All Members group in place. We also remove any suspended Identifiers.

      $groupMembers = [];

      foreach($ret['data']->group_members as $gm) {
        if($gm->group->isAllMembers()) {
          $groupMembers[] = $gm;
        }
      }

      $ret['data']->group_members = $groupMembers;

      $identifiers = [];

      foreach($ret['data']->identifiers as $ident) {
        if($ident->status == SuspendableStatusEnum::Active) {
          $identifiers[] = $ident;
        }
      }

      $ret['data']->identifiers = $identifiers;

      $ret['data']->group_owners = [];
      $ret['data']->person_roles = [];
    }

    return $ret;
  }

  /**
   * Recalculate Person status based on Person Roles status.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int      $id   Person ID
   * @return string         New Person status  
   */

  public function recalculateStatus(int $id): ?string {
    $newStatus = null;

    // Start by pulling the roles for this person, along with the Person record

    $person = $this->get($id, contain: 'PersonRoles');

    if(!empty($person->person_roles)) {
      foreach($person->person_roles as $role) {
        if(!$newStatus) {
          // This is the first role, just set the new status to it

          $newStatus = $role->status;
        } else {
          // Check if this role's status is more preferable than the current status

          if(StatusEnum::rank($role->status) > StatusEnum::rank($newStatus)) {
            $newStatus = $role->status;
          }
        }
      }
    }

    if($newStatus) {
      if($newStatus != $person->status) {
        // Locked status cannot be recalculated. This isn't an error, per se.
        if($person->status == StatusEnum::Locked) {
          $this->llog('trace', 'Not recalculating Person " . $person->id . " status since the record is locked');
          return $person->status;
        }

        // Update the Person status
        $oldStatus = $person->status;
        $person->status = $newStatus;
        $this->save($person);

        // Record history
        $this->recordHistory(
          entity:   $person,
          action:   ActionEnum::PersonStatusRecalculated,
          comment:  __d('result', 
                        'People.status.recalculated', 
                        [__d('enumeration', 'StatusEnum.'.$oldStatus), 
                         __d('enumeration', 'StatusEnum.'.$newStatus)])
        );

        // We shouldn't need to manually trigger provisioning here since we'll typically
        // be called via PersonRole::afterSave(), which will be called by some other
        // context (StandardController, Pipelines, etc) that will manage provisioning
        // after the PersonRole save (to the calling context's perspective) is finished.
//  $this->requestProvisioning(id: $obj->id, context: ProvisioningContextEnum::Automatic);
      }
      // else nothing to do, status is unchanged
    }
    // else no roles, leave status unchanged

    return $newStatus;
  }

  /**
   * Reconcile memberships in CO members groups based on the Person entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EntityInterface  $entity         Person Entity
   * @param  bool             $provision      Whether to run provisioners
   * @param  bool             $deleted        Whether $entity should be treated as deleted
   * @throws InvalidArgumentException
   * @throws RuntimeException
   */

  public function reconcileCoMembersGroupMemberships(
    \Cake\Datasource\EntityInterface $entity, 
    bool $provision=true,
    bool $deleted=false
  ) {
    // This is similar to PersonRole::reconcileCouMembersGroupMemberships.

    $activeEligible = !$deleted && $entity->isActive();
    $allEligible = !$deleted && ($entity->status != StatusEnum::Archived);
    
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


  /**
   * Save attributes for a person entity.
   *
   * @param int $personId The ID of the person entity to update.
   * @param array $fields An array of fields to update, where each field is expected to have an
   *                        `enrollment_attribute` with an `attribute` property, and a `value` property containing the value to update.
   * @return Person The updated person entity after saving.
   * @since  COmanage Registry v5.1.0
   */
  public function saveAttributeCollectorPetitionAttributes(int $personId, array $fields): Person
  {
    $person = $this->get($personId);
    foreach ($fields as $field) {
      $attribute = $field->enrollment_attribute->attribute;
      $value = $field->value;
      if($attribute === 'date_of_birth' && is_string($value)) {
        // While we're here, make sure it's in YYYY-MM-DD format. This should fail
        // if the inbound attribute is invalid.
        $dob = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if($dob) {
          $value = $dob->format('Y-m-d');
        }
      }
      $person->$attribute = $value;
    }

    return $this->saveOrFail($person);
  }
}