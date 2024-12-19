<?php
/**
 * COmanage Registry External Identity Roles Table
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
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\ExternalIdentityStatusEnum;

class ExternalIdentityRolesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);
    
    // Define associations
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('Types')
         ->setForeignKey('affiliation_type_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('affiliation_type');
    
    $this->hasMany('Addresses')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('AdHocAttributes')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('PersonRoles')
         ->setForeignKey('source_external_identity_role_id')
         ->setProperty('source_external_identity_role');
         // We don't want these to cascade deletes, see beforeDelete()
    $this->hasMany('TelephoneNumbers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('HistoryRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setDisplayField('title');
    
    $this->setPrimaryLink('external_identity_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    
    $this->setEditContains([
      'Addresses',
      'AdHocAttributes',
      'TelephoneNumbers'
    ]);
  
    $this->setViewContains([
      'Addresses',
      'AdHocAttributes',
      'TelephoneNumbers'
    ]);
    
    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
        'class' => 'StatusEnum'
      ],
      'affiliationTypes' => [
        'type' => 'type',
        'attribute' => 'PersonRoles.affiliation_type'
      ]
    ]);

    $this->setTabsConfig(
      [
        // Ordered list of Tabs
        'tabs' => ['People', 'PersonRoles', 'ExternalIdentities'],
        // What actions will include the subnavigation header
        'action' => [
          // If a model renders in a subnavigation mode in edit/view mode, it cannot
          // render in index mode for the same use case/context
          // XXX edit should go first.
          'People' => ['edit', 'view'],
          'PersonRoles' => ['index'],
          'ExternalIdentities' => ['index'],
        ],
        // What model will have a counter-badge after the tab title
        'counter' => ['PersonRoles', 'ExternalIdentities'],
        'nested' => [
          // Ordered list of Tabs
          'tabs' => ['ExternalIdentities', 'ExternalIdentityRoles'],
          // What actions will include the subnavigation header
          'action' => [
            // If a model renders in a subnavigation mode in edit/view mode, it cannot
            // render in index mode for the same use case/context
            // XXX edit should go first.
            'ExternalIdentities' => ['edit', 'view'],
            'ExternalIdentityRoles' => ['edit', 'view', 'index'],
          ],
          // What model will have a counter-badge after the tab title
          'counter' => ['ExternalIdentityRoles'],
        ]
      ]
    );

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
// See also CFM-126
// XXX need to add couAdmin, eventually
      'entity' => [
        'delete' =>   false,
        'edit' =>     false,
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false,
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
    // AR-ExternalIdentityRole-1 When an External Identity Role is deleted via a Pipeline
    // action, any associated Person Role will be set to the status as configured in the
    // associated Pipeline, and any associated MVEAs will be deleted from the Person Role.

    // Note the above does _not_ currently apply to manual deletions. It could, and
    // we could walk EIR -> EI -> EISR -> EIS -> Pipeline to get the appropriate
    // configuration, but for now at least we let manual operations require further
    // manual work.
    
    // Is there a Person Role associated with this EI Role?
    if(!empty($entity->id)) {
      $prole = $this->PersonRoles->find()
                                 ->where(['PersonRoles.source_external_identity_role_id' => $entity->id])
                                 ->contain(['AdHocAttributes', 'Addresses', 'TelephoneNumbers'])
                                 ->first();
      
      if(!empty($prole)) {
        // Unset the foreign key to the source EI Role so we don't cascade
        // deletes or otherwise mess things up.

        $this->llog('trace', "Removing link from PersonRole " . $prole->id . " to source ExternalIdentityRole " . $entity->id);
        $prole->source_external_identity_role_id = null;

        if(!empty($options['sync_status_on_delete'])) {
          // Update the associated Person Role, unless it is Frozen

          if(isset($prole->frozen) && $prole->frozen) {
            $this->llog('trace', "Refusing to update frozen Person Role " . $prole->id . " from deleted External Identity Role " . $entity->id);
          } else {
            // Update the status in accordance with the Pipeline configuration
            $this->llog('rule', "AR-ExternalIdentityRole-1 Updating status on PersonRole " . $prole->id . " to " . $options['sync_status_on_delete'] . " following deletion of source ExternalIdentityRole " . $entity->id);

            $prole->status = $options['sync_status_on_delete'];

            // Delete the MVEAs associated with this Person Role. We do this here
            // rather than in syncPerson since we're doing all the other work here.
            foreach([
              'Addresses', 
              'AdHocAttributes', 
              'TelephoneNumbers'
            ] as $eirmodel) {
              $aeirmodel = Inflector::underscore($eirmodel);

              if(!empty($prole->$aeirmodel)) {
                foreach($prole->$aeirmodel as $aeirentity) {
                  $this->llog('rule', "AR-ExternalIdentityRole-1 Deleted $aeirmodel " . $aeirentity->id . " for Person Role " . $prole->id);
                  $this->PersonRoles->$eirmodel->deleteOrFail($aeirentity);
                }
              }
            }
          }
        }

        $this->PersonRoles->saveOrFail($prole);
      }
    }

    $this->recordHistory(entity: $entity, action: ActionEnum::MVEADeleted);
    
    return true;
  }

  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Person $entity Entity to generate display field for
   * @return string         Display field
   */
  
  public function generateDisplayField(\App\Model\Entity\ExternalIdentityRole $entity): string {
    // Try to find something renderable
    
    if(!empty($entity->title)) {
      return $entity->title;
    }
    
// XXX else affiliation type if set, else organization, else department
    
    return (string)$entity->id;
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
    if(!$entity->deleted) {
      $this->recordHistory($entity);

      if($entity->isDirty('status')) {
        $this->ExternalIdentities->recalculateStatus($entity->external_identity_id);
      }
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
    
    $this->registerPrimaryKeyValidation($validator, $this->getPrimaryLinks());
    
    $this->registerStringValidation($validator, $schema, 'role_key', true);

    $validator->add('affiliation_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('affiliation_type_id');
    
    $this->registerStringValidation($validator, $schema, 'title', false);
    
    $this->registerStringValidation($validator, $schema, 'organization', false);
    
    $this->registerStringValidation($validator, $schema, 'department', false);
    
    $this->registerStringValidation($validator, $schema, 'manager_identifier', false);
    
    $this->registerStringValidation($validator, $schema, 'sponsor_identifier', false);
    
    $validator->add('valid_from', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('valid_from');
    
    $validator->add('valid_through', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('valid_through');
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', ExternalIdentityStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    
    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');
    
    return $validator; 
  }
}