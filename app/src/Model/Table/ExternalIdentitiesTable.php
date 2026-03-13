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
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\ExternalIdentityStatusEnum;

class ExternalIdentitiesTable extends Table {
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
    $this->hasOne('ExtIdentitySourceRecords')
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
    $this->setAllowLookupPrimaryLink(['adopt', 'relink']);
    
    $this->setEditContains([
      'Addresses',
      'AdHocAttributes',
      'EmailAddresses',
      'ExtIdentitySourceRecords' => ['ExternalIdentitySources'],
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
        // Note the inverse operation for adoption, annulment, is handled by
        // External Identity Sources since there is no longer an External Identity
        'adopt' =>    ['platformAdmin', 'coAdmin'],
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'relink' =>   ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin', 'selfMember']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin', 'selfMember']
      ],
      // Related models whose permissions we'll need, typically for table views
      'related' => [
        'entity' => [
          'ExtIdentitySourceRecords',
        ],
        'table' => [
          'Names',
          'Addresses',
          'AdHocAttributes',
          'EmailAddresses',
          'ExternalIdentityRoles',
          'HistoryRecords',
          'Identifiers',
          'JobHistoryRecords',
          'Pronouns',
          'TelephoneNumbers',
          'Urls'
        ],
      ]
    ]);
  }

  /**
   * Adopt an External Identity.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $id   External Identity ID
   * @return int          Person ID
   */

  public function adopt(int $id): int {
    // Adoption is the process of converting a record that came from an External Identity
    // Source to a native Registry record. This process involves several steps. While we could
    // probably rely on Cake's transaction, we explicitly create one here to be clearer.

    $cxn = $this->getConnection();
    $cxn->begin();

    try {
      // Start by pulling the External Identity and related models

      $related = [
        // For the External Identity itself, we pull the directly related MVEAs
        // as an easy way to walk to the Pipelined attributes, which are what we
        // actually want to update
        'Addresses' => ['PipelinedAddresses'],
        'AdHocAttributes' => ['PipelinedAdHocAttributes'],
        'EmailAddresses' => ['PipelinedEmailAddresses'],
        'Identifiers' => ['PipelinedIdentifiers'],
        'Names' => ['PipelinedNames'],
        'Pronouns' => ['PipelinedPronouns'],
        'TelephoneNumbers' => ['PipelinedTelephoneNumbers'],
        'Urls' => ['PipelinedUrls'],
        // For the attached External Identity Roles, we want to pull the associated
        // Person Roles, but we still pull the EIR MVEAs and walk to their Pipelined
        // attributes (rather than query the Person Role attributes directly) because
        // an admin might have added additional MVEAs to the Person Role, and we need
        // to distinguish the ones that came from this External Identity.
        'ExternalIdentityRoles' => [
          'Addresses' => ['PipelinedAddresses'],
          'AdHocAttributes' => ['PipelinedAdHocAttributes'],
          'TelephoneNumbers' => ['PipelinedTelephoneNumbers'],
          'PersonRoles' // These are the Pipelined Roles
        ]
      ];

      $externalIdentity = $this->get($id, contain: $related);

      // For each of the top level related models, walk to the Pipelined record on the Person
      // and unset the source_ key.

      foreach(array_keys($related) as $modelName) {
        if($modelName == 'ExternalIdentityRoles') continue;  // We'll handle these separately

        // The name in related entity format, eg email_addresses
        $entities = Inflector::underscore($modelName);
        // The pipelined name in related entity format, eg pipelined_email_address
        $pentity = "pipelined_" . Inflector::singularize($entities);
        // The singular model name, eg EmailAddress
        $sModelName = Inflector::singularize($modelName);

        $soridTypeId = null;

        if($modelName == 'Identifiers') {
          // AR-ExternalIdentity-2 When an External Identity is adopted, the Source Key
          // Identifier is deleted from the adopting Person.

          // As a special case, we _delete_ rather than unlink the Source Key on the
          // Person Record, since it doesn't make sense to keep that anymore. To do
          // this, we need the type id.

          $soridTypeId = $this->Identifiers->Types->getTypeId(
            coId: $this->calculateCoForRecord($externalIdentity),
            attribute: 'Identifiers.type',
            value: 'sorid'
          );
        }

        if(!empty($externalIdentity->$entities)) {
          // $e is the MVEA entity attached to the External Identity
          foreach($externalIdentity->$entities as $e) {
            if(!empty($e->$pentity)) {
              // The name of the source field, eg source_email_address_id
              $sourceField = $e->sourceAttributeName();

              // $p is the Pipelined copy of $e
              foreach($e->$pentity as $p) {
                if($soridTypeId && $p->type_id == $soridTypeId) {
                  // Special case for Source Key / sorid

                  $this->llog('trace', "Deleting Source Key Identifier " . $p->id . " from Person " . $externalIdentity->person_id);

                  $this->Identifiers->delete($p);
                } elseif($e->id == $p->$sourceField) {
                  $this->llog('trace', "Unlinking Person " . $externalIdentity->person_id . " $sModelName " . $p->id . " from External Identity " . $externalIdentity->id . " $sModelName " . $e->id);

                  $p->$sourceField = null;
                  $this->$modelName->saveOrFail($p);
                }
              }
            }
          }
        }
      }

      // For each EIR, do the same, including for the Person Role itself.
      if(!empty($externalIdentity->external_identity_roles)) {
        foreach($externalIdentity->external_identity_roles as $eirole) {
          // The Person Role created from this EI Role
          $prole = $eirole->pipelined_person_role;

          foreach(array_keys($related['ExternalIdentityRoles']) as $modelName) {
            if(is_int($modelName)) continue;  // This is PersonRoles, which we'll handle these separately

            // The name in related entity format, eg email_addresses
            $entities = Inflector::underscore($modelName);
            // The pipelined name in related entity format, eg pipelined_email_address
            $pentity = "pipelined_" . Inflector::singularize($entities);
            // The singular model name, eg EmailAddress
            $sModelName = Inflector::singularize($modelName);

            if(!empty($eirole->$entities)) {
              // $e is the MVEA entity attached to the External Identity Role
              foreach($eirole->$entities as $e) {
                if(!empty($e->$pentity)) {
                  // The name of the source field, eg source_email_address_id
                  $sourceField = $e->sourceAttributeName();

                  // $p is the Pipelined copy of $e, and is attached to the Person Role ($prole),
                  // though we retrieved it via the EI Role
                  foreach($e->$pentity as $p) {
                    if($e->id == $p->$sourceField) {
                      $this->llog('trace', "Unlinking Person Role " . $prole->id . " $sModelName " . $p->id . " from External Identity Role " . $eirole->id . " $sModelName " . $e->id);

                      $p->$sourceField = null;
                      $this->$modelName->saveOrFail($p);
                    }
                  }
                }
              }
            }
          }

          $this->llog('trace', "Unlinking Person Role " . $prole->id . " from External Identity Role " . $eirole->id);

          $prole->source_external_identity_role_id = null;
          $this->ExternalIdentityRoles->PersonRoles->saveOrFail($prole);
        }
      }

      // Store the adopted Person ID in the External Identity Source Record so the record
      // can't be re-synced (AR-ExternalIdentity-1). We also need to unlink the External
      // Identity so we don't delete the EIS Record via cascade, below.

      $eisrecord = $this->ExtIdentitySourceRecords->find()
                                                  ->where(['external_identity_id' => $externalIdentity->id])
                                                  ->firstOrFail();

      // AR-GMR-3 prevents us from changing a primary link key, so we can't just do this:
      // $eisrecord->adopted_person_id = $externalIdentity->person_id;
      // $eisrecord->external_identity_id = null;
      // So instead we delete the current record and create a new one.
      // (The deletion is actually handled by the cascade from External Identity, below.)

      // Because we are creating a new record the "create" time will likely be _after_ the
      // "last update" time, which is a little unintuitive, however it is technically correct
      // so we don't try to override the create time of the new EIS Record. (The archived
      // values will still be available in the database for context.)

      $neweisdata = $this->filterMetadataForCopy(
        table: $this->ExtIdentitySourceRecords->getTarget(), 
        entity: $eisrecord
      );

      $neweisdata['external_identity_id'] = null;
      $neweisdata['adopted_person_id'] = $externalIdentity->person_id;
      // filterMetadataForCopy will pick the "wrong" primary key, so we need to repopulate it
      $neweisdata['external_identity_source_id'] = $eisrecord->external_identity_source_id;

      $neweisentity = $this->ExtIdentitySourceRecords->newEntity($neweisdata);

      $this->llog('trace', "Replacing ExtIdentitySourceRecord " . $eisrecord->id . " for source key " . $eisrecord->source_key . " to be adopted by Person " . $externalIdentity->person_id);
      $this->ExtIdentitySourceRecords->saveOrFail($neweisentity);

      // Create a History Record
      $this->recordHistory(
        entity: $externalIdentity,
        action: ActionEnum::ExternalIdentityAdopted,
        comment: __d('result', 'ExternalIdentities.adopted', [$externalIdentity->id])
      );

      // Finally, delete the External Identity and its related models
      $this->llog('trace', "Deleting External Identity " . $externalIdentity->id);
      $this->delete($externalIdentity);

      $cxn->commit();

      return $externalIdentity->person_id;
    }
    catch(\Exception $e) {
      $cxn->rollback();

      throw $e;
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

    $event->setResult(true);
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

    $externalIdentity = $this->get($id, contain: 'ExternalIdentityRoles');

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