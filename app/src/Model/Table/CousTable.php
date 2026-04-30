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

use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Validation\Validator;

use \App\Lib\Enum\GroupTypeEnum;
use \App\Lib\Enum\StatusEnum;
use \App\Lib\Enum\ProvisioningEligibilityEnum;
use \App\Lib\Util\StringUtilities;
use \App\Lib\Util\TableUtilities;

class CousTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\ClonableTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionableTrait{
    requestProvisioning as traitRequestProvisioning;
  }
  use \App\Lib\Traits\RuleTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TreeTrait;
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
    $this->addBehavior('Clonable');
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
    
    $this->hasMany('EnrollmentFlows');
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
      'parents' => [
        'type'  => 'parent'
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
    // AR-COU-1 A COU may not be deleted if it has any members.
    $rules->addDelete([$this, 'ruleHasMembers'],
                      'hasMembersDelete',
                      ['errorField' => 'status']);

    // AR-COU-2 A COU may not be deleted if it has any children.
    $rules->addDelete([$this, 'ruleHasChildren'],
                      'hasChildrenDelete',
                      ['errorField' => 'parent_id']);

    // AR-COU-3 Two COUs within the same CO cannot share the same name
    $rules->add([$this, 'ruleIsCaseInsensitiveUnique'],
                'isUnique',
                ['errorField' => 'name', 'fields' => ['name', 'co_id']]);
    
    // This is not an Application Rule per se, but the parent_id must be a valid
    // potential parent
    $rules->add([$this, 'rulePotentialParent'],
                'potentialParent',
                ['errorField' => 'parent_id']);
    
    // AR-GMR-6 The same UUID cannot be assigned to multiple objects within the same CO.
    $rules->add([$this, 'ruleUuidUnique'],
                'uuidUnique',
                ['errorField' => 'uuid']);
    
    return $rules;
  }

  /**
   * Get the set of entities that are to be cloned after $original.
   * 
   * The returned array may include both UUIDs (strings) and PaginatedSqlIterators,
   * where the Iterator returns only clonable entities.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  EntityInterface $original Current entity being cloned
   * @return array                     Array of UUIDs and/or PaginatedSqlIterators
   */

  public function getCloneSuccessors(
    \Cake\Datasource\EntityInterface $original
  ): array {
    // Pull the set of non-automatic COU Groups and return their UUIDs

    $clonableGroups = $this->Groups
                           ->find('nonAutomaticGroups', cou_id: $original->id)
                           ->all();
    
    return $clonableGroups->extract('uuid')->toArray();
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
    if(isset($options['clone']) && $options['clone']) {
      // If we're in the middle of cloning, don't run setup or addDefaults.
      // This is because CloneCommand needs to specially handle the data source
      // and UUID syncing, and in edge cases it's possible that addDefaults() is not
      // the right behavior because a COU was created before additional default Groups
      // were added (though this should be pretty rare).
      return;
    }
    
    if(!empty($entity->id)) {
      if($entity->isNew()) {
        // Run setup for new COU
        
        $this->setup(id: $entity->id, coId: $entity->co_id);
      } elseif($entity->getOriginal('name') != $entity->get('name')) {
        // AR-COU-5 The name was changed, so we may need to update the system groups
        
        $this->Groups->addDefaults(coId: $entity->co_id, couId: $entity->id, rename: true);
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
    // We need the archived record on delete to properly deprovision
    $ret['data'] = $this->get($id, archived: true);

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
   * Check for any dependencies that must be in place before cloning begins.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  EntityInterface  $clone            Cloned entity
   * @param  string           $targetDataSource Target DataSource connection name
   */

  public function postClone(
    \Cake\Datasource\EntityInterface $clone,
    string $targetDataSource='default'
  ) {
    // We need to call addDefaults on the Target datasource, but only for
    // automatic Groups. (Non-automatic Groups are handled by getCloneSuccessors.)

    $TargetGroups = TableUtilities::getTableWithDataSource(
      tableName: "Groups",
      connectionName: $targetDataSource
    );

    $TargetGroups->addDefaults(
      coId: $clone->co_id,
      couId: $clone->id,
      rename: true,   // Allow renaming on updates
      autoOnly: true,
      provision: false,
      clone: true,
      dataSource: $targetDataSource
    );
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
    // We need to handle the special COU Groups manually, depending on whether this is
    // a delete operation or an add. This is going to result in some duplicate work,
    // but that's the tradeoff to work within the existing set of callbacks.

    $couData = $this->marshalProvisioningData($id);

    if($couData['eligibility'] == ProvisioningEligibilityEnum::Deleted) {
      // This is a delete operation. Per AR-COU-6, the special groups associated with the
      // COU also need to be deleted. This has already happened via Cake's dependency
      // deletion, ie
      //
      // (1) StandardController::delete() deletes the COU entity
      // (2) Cake cascades that delete to the Groups with a matching cou_id
      // (3) StandardController::delete() calls (de)provisioning on the COU, but nothing
      //     calls (de)provisioning on the Groups.
      //
      // Our workaround is to find the deleted groups and then request provisioning
      // for them. Once that's done, we'll use the standard trait behavior to handle
      // the COU deletion.

      // (This is really a general problem for deleting cascaded provisionable models,
      // but it only manifests here currently, so we haven't implemented a general solution.)


      $groups = $this->Groups->find('all', archived: true)->where(['cou_id' => $id])->all();

      foreach($groups as $g) {
        $this->llog('trace', "Forcing reprovisioning of deleted Group " . $g->id . " following deletion of COU " . $id);

        $this->Groups->requestProvisioning(
          $g->id,
          $context,
          $provisioningTargetId,
          $job
        );
      }

      $this->traitRequestProvisioning($id, $context, $provisioningTargetId, $job);
    } elseif($couData['eligibility'] == ProvisioningEligibilityEnum::Eligible) {
      // We generally want the standard functionality. In addition, when a new COU is created,
      // we also create default Groups, and GroupsTable::addDefault will attempt to provision
      // then. This is fine for updates, but for new COUs the sequence of calls is
      //
      // (1) StandardController::add() saves new COU
      // (2) CousTable::localAfterSave() calls setup
      // (3) GroupsTable::addDefaults() creates the new Groups and tries to provision them,
      //     but the COU hasn't been provisoned yet, so this may or may not work (depending
      //     on the Provisioner)
      // (4) StandardController::add() runs provisioning on the new COU
      //
      // Our workaround is to pull all COU related Groups and reprovision them here.
      // We do this on both adds and updates because we don't haev the context anymore
      // for whether $id is new.

      $this->traitRequestProvisioning($id, $context, $provisioningTargetId, $job);

      $groups = $this->Groups->find()->where(['cou_id' => $id])->all();

      foreach($groups as $g) {
        $this->llog('trace', "Forcing reprovisioning of Group " . $g->id . " following provisioning of COU " . $id);

        $this->Groups->requestProvisioning(
          $g->id,
          $context,
          $provisioningTargetId,
          $job
        );
      }
    }
  }

  /**
   * Application Rule to determine if the COU has children.
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
      return __d('error', 'Cous.children', [$count]);
    }

    return true;
  }

  /**
   * Application Rule to determine if the COU has members.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleHasMembers($entity, $options) {
    $count = $this->PersonRoles
                  ->find('all')
                  ->where(['cou_id' => $entity->id])
                  ->count();
    
    if($count > 0) {
      return __d('error', 'Cous.members', [$count]);
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

    $this->registerClonableValidation($validator, $schema);
    
    return $validator; 
  }
}