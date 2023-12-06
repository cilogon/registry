<?php
/**
 * COmanage Registry External Identities Table
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
use \App\Lib\Enum\ExternalIdentityStatusEnum;

class ExternalIdentitiesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);
    
    // Define associations
    $this->belongsTo('People');
    
// External Identities do not have Primary Names
//    $this->hasOne('PrimaryName')
//         ->setClassName('Names');
//         ->setConditions(['PrimaryName.primary_name' => true]);
    $this->hasMany('Names')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Addresses')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('AdHocAttributes')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('EmailAddresses')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('ExternalIdentityRoles')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('ExtIdentitySourceRecords')
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
    $this->hasMany('Pronouns')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('TelephoneNumbers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Urls')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setDisplayField('id');
    
    $this->setPrimaryLink('person_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    
    $this->setEditContains([
      'Addresses',
      'AdHocAttributes',
      'EmailAddresses',
      'Identifiers',
      'Names',
      //'ExternalIdentityRoles',
      'Pronouns',
      'TelephoneNumbers',
      'Urls'
    ]);

    $this->setIndexContains(['Names']);

    $this->setViewContains([
      'Addresses',
      'AdHocAttributes',
      'EmailAddresses',
      'ExtIdentitySourceRecords' => ['ExternalIdentitySources'],
      'Identifiers',
      'Names',
      'Pronouns',
      'TelephoneNumbers',
      'Urls'
    ]);

    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
// XXX maybe this (and EIRoles) should be SuspendableStatusEnum?
        'class' => 'StatusEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
// See also CFM-126
// XXX need to add couAdmin, eventually
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
   * Callback before model delete.
   *
   * @since  COmanage Registry v5.0.0
   * @param  CakeEventEvent $event   The beforeDelete event
   * @param                 $entity  Entity
   * @param  ArrayObject    $options Options
   * @return boolean                 True on success
   */
  
  public function beforeDelete(\Cake\Event\Event $event, $entity, \ArrayObject $options) {
    // If we were only dealing with hard delete, we wouldn't need implementedEvents()
    // below, because ChangelogBehavior ignores hard deletes.

    // Manually delete any names, since the validation rules will fail on cascade.
    // Since this isn't a hard delete we can't use deleteAll since we need
    // ChangelogBehavior to fire.

    $names = $this->Names->find()->where(['external_identity_id' => $entity->id])->all();

    foreach($names as $n) {
      $this->Names->delete($n, ['checkRules' => false]);
    }

    return true;
  }

  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentity $entity Entity to generate display field for
   * @return string                   Display field
   */
  
  public function generateDisplayField(\App\Model\Entity\ExternalIdentity $entity): string {
    return $entity->names[0]->full_name;
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

    return true;
  }

  /**
   * Recalculate External Identity status based on External Identity Roles status.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int      $id   External Identity ID
   * @return string         New External Identity status  
   */

  public function recalculateStatus(int $id): ?string {
    $newStatus = null;

    // Start by pulling the roles for this External Identity, along with the EI record

    $externalIdentity = $this->get($id, ['contain' => 'ExternalIdentityRoles']);

    if(!empty($externalIdentity->external_identity_roles)) {
      foreach($externalIdentity->external_identity_roles as $role) {
        if(!$newStatus) {
          // This is the first role, just set the new status to it

          $newStatus = $role->status;
        } else {
          // Check if this role's status is more preferable than the current status

          if(ExternalIdentityStatusEnum::rank($role->status) > ExternalIdentityStatusEnum::rank($newStatus)) {
            $newStatus = $role->status;
          }
        }
      }
    }

    if($newStatus) {
      if($newStatus != $externalIdentity->status) {
        // Update the External Identity status
        $oldStatus = $externalIdentity->status;
        $externalIdentity->status = $newStatus;
        $this->save($externalIdentity);

        // Record history
        $this->recordHistory(
          entity:   $externalIdentity,
          action:   ActionEnum::PersonStatusRecalculated,
          comment:  __d('result', 
                        'ExternalIdentities.status.recalculated', 
                        [__d('enumeration', 'ExternalIdentityStatusEnum.'.$oldStatus), 
                         __d('enumeration', 'ExternalIdentityStatusEnum.'.$newStatus)])
        );
      }
      // else nothing to do, status is unchanged
    }
    // else no roles, leave status unchanged

    return $newStatus;
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
    
    $this->registerPrimaryKeyValidation($validator, $this->getPrimaryLinks());
    
    $this->registerStringValidation($validator, $schema, 'source_key', true);

    $validator->add('status', [
      'content' => ['rule' => ['inList', ExternalIdentityStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    
    $validator->add('date_of_birth', [
      'content' => ['rule' => 'date']
    ]);
    $validator->allowEmptyString('date_of_birth');
    
    return $validator; 
  }
}