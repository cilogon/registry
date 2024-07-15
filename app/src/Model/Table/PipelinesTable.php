<?php
/**
 * COmanage Registry Pipelines Table
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
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use \App\Model\Entity\ExternalIdentity;
use \App\Model\Entity\ExternalIdentitySource;
use \App\Model\Entity\ExtIdentitySourceRecord;
use \App\Model\Entity\Person;
use \App\Model\Entity\Pipeline;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\DeletedRoleStatusEnum;
use \App\Lib\Enum\ExternalIdentityStatusEnum;
use \App\Lib\Enum\MatchStrategyEnum;
use \App\Lib\Enum\ProvisioningContextEnum;
use \App\Lib\Enum\StatusEnum;
use \App\Lib\Enum\SuspendableStatusEnum;
use \App\Lib\Util\StringUtilities;

class PipelinesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PluggableModelTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('MatchServers')
         ->setClassName('Servers')
         ->setForeignKey('match_server_id')
         ->setProperty('match_server');
    $this->belongsTo('MatchEmailAddressTypes')
         ->setClassName('Types')
         ->setForeignKey('match_email_address_type_id')
         ->setProperty('match_email_address_type');
    $this->belongsTo('MatchIdentifierTypes')
         ->setClassName('Types')
         ->setForeignKey('match_identifier_type_id')
         ->setProperty('match_identifier_type');
    $this->belongsTo('SyncAffiliationTypes')
         ->setClassName('Types')
         ->setForeignKey('sync_affiliation_type_id')
         ->setProperty('sync_affiliation_type');
    $this->belongsTo('SyncCous')
         ->setClassName('Cous')
         ->setForeignKey('sync_cou_id')
         ->setProperty('sync_cou');
    $this->belongsTo('SyncReplaceCous')
         ->setClassName('Cous')
         ->setForeignKey('sync_replace_cou_id')
         ->setProperty('sync_replace_cou');
    $this->belongsTo('SyncIdentifierTypes')
         ->setClassName('Types')
         ->setForeignKey('sync_identifier_type_id')
         ->setProperty('sync_identifier_type');
    
    $this->hasMany('ExternalIdentitySources');
    
    $this->setDisplayField('description');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'matchEmailAddressTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ],
      'matchIdentifierTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'matchStrategies' => [
        'type'  => 'enum',
        'class' => 'MatchStrategyEnum'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SuspendableStatusEnum'
      ],
      'syncAffiliationTypes' => [
        'type' => 'type',
        'attribute' => 'PersonRoles.affiliation_type'
      ],
      'syncCous' => [
        'type' => 'select',
        'model' => 'Cous'
      ],
      'syncIdentifierTypes' => [
        'type' => 'select',
        'model' => 'Types',
        'where' => ['attribute' => 'Identifiers.type']
      ],
      'syncReplaceCous' => [
        'type' => 'select',
        'model' => 'Cous'
      ],
      // Just go with Cake's default pluralization
      'syncStatusOnDeletes' => [
        'type'  => 'enum',
        'class' => 'DeletedRoleStatusEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }
  
  /**
   * Correlate an array of mapped backend record data (as returned by
   * mapAttributesToCO) to an existing External Identity (as returned by get)
   * by finding ID keys for related models.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentity $externalIdentity ExternalIdentity, including related models
   * @param  array            $mapped           Backend data, including related models
   * @return array                              Backend data, with record keys
   */

  protected function correlateRecordKeys(
    ExternalIdentity  $externalIdentity,
    array             $mapped
  ): array {
    $ret = $mapped;

    // Unlike when syncing an External Identity to a Person, syncing an EIS Record
    // to an External Identity doesn't have a key to map associated models in the
    // EIS Record to the External Identity. (The exception being the role_key on
    // the External Identity Role.) 

    // While we could enforce a key as part of the EIS API, that might be tricky
    // for some backends to implement (eg: an LdapSource record with two email
    // addresses can't guarantee it will always retrieve them in the same order),
    // and the only benefit of such a requirement would be that we could do an update
    // rather than a delete and add.

    // Instead we try to map records in the Backend data to records in the
    // External Identity data. We mostly iteratively loop over the various related
    // models. This isn't necessarily the most efficient approach, but in most cases
    // we're dealing with O(1) MVEA records. (eg: An EIS record will typically have
    // 0, 1, or maybe 2 EmailAddresses attached to it.)

    // Note that our goal here is to identity attributes that _haven't_ changed.
    // By finding a matching ID patchEntity() will know not to update the related
    // model. If an attribute changes in the Backend record, we won't match it
    // here and the old value will be deleted while the new value will be added.
    // (We do still need to handle some metadata for new records, though, in particular
    // foreign keys.)

    // Start with the ID of the External Identity itself.
    $ret['id'] = $externalIdentity->id;

    // Next look through the External Identity's related models.
    foreach([
      // related models need EntityMetaTrait
      'ad_hoc_attributes',
      'addresses', 
      'email_addresses',
      'identifiers',
      'names',
      'pronouns',
      'telephone_numbers',
      'urls'
    ] as $m) {
      if(!empty($externalIdentity->$m) && !empty($ret[$m])) {
        // There is at least one associated model of this type on the
        // External Identity, and in the mapped Backend data
        foreach($externalIdentity->$m as $rentity) {
          // Check all mapped records for the same model
          foreach($ret[$m] as $i => $mdata) {
            if(!isset($ret[$m][$i]['id']) // We saw this one already
               && $rentity->isProbablyThisArray($mdata)) {
              // Insert the record ID
              $ret[$m][$i]['id'] = $rentity->id;
              break; // We can exit the inner loop, but not the outer one
            }
          }
        }
      }

      if(!empty($ret[$m])) {
        // And make sure each mapped Backend record has a parent record ID.
        // We do this separately to catch any new records.
        foreach(array_keys($ret[$m]) as $i) {
          $ret[$m][$i]['external_identity_id'] = $externalIdentity->id;
        }
      }
    }

    // Now map any External Identity Roles. We can use the role_key to help here.
    
    if(!empty($externalIdentity->external_identity_roles)
       && !empty($ret['external_identity_roles'])) {
      foreach($externalIdentity->external_identity_roles as $roleentity) {
        foreach($ret['external_identity_roles'] as $i => $rdata) {
          if($roleentity->role_key == $rdata['role_key']) {
            // Insert the record ID for existing records (updates)
            $ret['external_identity_roles'][$i]['id'] = $roleentity->id;

            // While we're here, work with any related models
            foreach([
              // related models need EntityMetaTrait
              'ad_hoc_attributes',
              'addresses', 
              'telephone_numbers'
            ] as $m) {
              if(!empty($ret['external_identity_roles'][$i][$m])) {
                if(!empty($roleentity->$m)) {
                  // There is at least one associated model of this type on the
                  // External Identity Role, and in the mapped Backend data
                  foreach($roleentity->$m as $rentity) {
                    // Check all mapped records for the same model
                    foreach($ret['external_identity_roles'][$i][$m] as $j => $mdata) {
                      if(!isset($ret['external_identity_roles'][$i][$m][$j]['id']) // We saw this one already
                        && $rentity->isProbablyThisArray($mdata)) {
                        // Insert the record ID
                        $ret['external_identity_roles'][$i][$m][$j]['id'] = $rentity->id;
                        break; // We can exit the inner loop, but not the outer ones
                      }
                    }
                  }
                }

                // Insert the parent record ID, separately to catch any new records
                foreach(array_keys($ret['external_identity_roles'][$i][$m]) as $j) {
                  $ret['external_identity_roles'][$i][$m][$j]['external_identity_role_id'] = $roleentity->id;
                }
              }
            }

            break; // We can exit the inner loop, but not the outer one
          }
        }
      }

      // And finally any related models for the External Identity. We can skip this
      // for new Roles since the related models are also going to be new (and
      // therefore not have existing keys). For deleted Roles, when the Role itself
      // is deleted the associated models will also be deleted (as dependencies) so
      // we don't need to facilitate that here.
    }

    if(!empty($ret['external_identity_roles'])) {
      // Insert the parent record ID, again separately to catch any new records.
      foreach(array_keys($ret['external_identity_roles']) as $i) {
        $ret['external_identity_roles'][$i]['external_identity_id'] = $externalIdentity->id;
      }
    }

    return $ret;
  }

  /**
   * Create an initial Person record from an External Identity.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Pipeline                 $pipeline       Pipeline
   * @param  ExternalIdentitySource   $eis            External Identity Source
   * @param  ExtIdentitySourceRecord  $eisRecord      External Identity Source Record
   * @param  array                    $eisAttributes  Attributes provided by EIS Backend
   * @return Person                                   New Person entity
   */

  protected function createPersonFromEIS(
    Pipeline                $pipeline,
    ExternalIdentitySource  $eis,
    ExtIdentitySourceRecord $eisRecord,
    array                   $eisAttributes
  ): Person {
    // This is a skeletal Person record so that we can hang other entities
    // from it (eg: External Identity). As such, we're just prepopulating
    // some defaults but these values are NOT linked to the original source
    // record and can be changed via other steps in the Pipeline, an
    // administrator, or self service tooling. We also do not create roles
    // or run identifier assignments, etc, that stuff happens later in the
    // Pipeline.

    $mappedAttributes = $this->mapAttributesToCO($pipeline, $eisAttributes);

    $newPerson = [
      'co_id'   => $pipeline->co_id,
      'status'  => StatusEnum::Pending
    ];

    if(!empty($mappedAttributes['date_of_birth'])) {
      $newPerson['date_of_birth'] = $mappedAttributes['date_of_birth'];
    }

    if(empty($mappedAttributes['names'][0])) {
      throw new \InvalidArgumentException('At least one name is required for createPersonFromEIS');
    }

    // AR-Pipeline-1 If a Pipeline creates a new Person, the first Name
    // returned by the External Identity Source backend will be used as
    // the initial Primary Name for the new Person.
    $newPerson['names'][] = $mappedAttributes['names'][0];
    // Force this to be the primary name just in case it wasn't set
    $newPerson['names'][0]['primary_name'] = true;

    $entity = $this->Cos->People->newEntity($newPerson, ['associated' => 'Names']);

    $this->Cos->People->saveOrFail($entity, ['associated' => 'Names']);

    $this->Cos->People->recordHistory(
      entity: $entity,
      action: ActionEnum::PersonAddedPipeline,
      comment: __d('result', 
                   'People.added.pipeline',
                   [$pipeline->description, 
                    $pipeline->id,
                    $eis->description,
                    $eis->id,
                    $eisRecord->source_key])
    );

    return $entity;
  }

  /**
   * Copy the data from an entity and filter metadata, returning an array
   * suitable for creating a new entity. Related models are also removed.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity       Entity to copy
   * @return array                Array of filtered entity data
   */

  protected function duplicateFilterEntityData($entity): array {
    // There's some overlap with TableMetaTrait::filterMetadataFields...

    $newdata = $entity->toArray();

    // This list is a combination of eliminating fields that create
    // noise in change detection for History creation, as well as
    // functional attributes that cause problems if set (eg: frozen).
    unset(
      $newdata['id'],
      $newdata['external_identity_id'],
      $newdata['external_identity_role_id'],
      $newdata['actor_identifier'],
      $newdata['created'],
      $newdata['deleted'],
      $newdata['frozen'],
      $newdata['full_name'],
      // XXX we temporarily filter manager and sponsor identifiers because
      // we haven't yet implemented support for mapping them
      $newdata['manager_identifier'],
      $newdata['sponsor_identifier'],
      $newdata['modified'],
      $newdata['primary_name'],
      $newdata['revision'],
      $newdata['role_key'],
      // We don't want status for the External Identity, and we handle it
      // specially for External Identity Roles
      $newdata['status']
    );

    // Timestamps are FrozenTime objects in the entity data, and is_scalar
    // will filter them out, so convert them to strings
    foreach(['valid_from', 'valid_through'] as $attr) {
      if(!empty($entity->$attr)) {
        $newdata[$attr] = $entity->$attr->i18nFormat('yyyy-MM-dd HH:mm:ss');
      } else {
        // Populate a blank value so removal works correctly
        $newdata[$attr] = "";
      }
    }

    // This will remove anything that isn't stringy
    return array_filter($newdata, 'is_scalar');
  }

  /**
   * Execute the specified Pipeline on the provided EIS data.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id               Pipeline ID
   * @param  int    $eisId            Exxternal Identity Source ID
   * @param  array  $eisBackendRecord Record returned by EIS Backend
   * @param  bool   $force            Force the Pipeline to run all steps, even if no changes were detected
   * @return string                   Record status (new, unchanged, unknown, updated)
   */

  public function execute(
    int   $id,
    int   $eisId, 
    array $eisBackendRecord,
    bool  $force=false
  ): string {
    // Start with our configuration(s)
    $pipeline = $this->get($id);
    $eis = $this->ExternalIdentitySources->get($eisId);

    // Start a Transaction
    $cxn = $this->getConnection();
    $cxn->begin();

    try {
      $this->llog('trace', "Executing Pipeline $id for EIS $eisId source key " . $eisBackendRecord['source_key']);

      // (1) Create or update the ExtIdentitySourceRecord based on the
      //     data provided by the backend
      $eisRecord = $this->manageEISRecord(
        $pipeline, 
        $eis, 
        $eisBackendRecord['source_key'],
        $eisBackendRecord['source_record']
      );

      if(!$force && $eisRecord['status'] == 'unchanged') {
        $this->llog('trace', "Record for EIS $eisId source key " . $eisBackendRecord['source_key'] . " is unchanged, stopping Pipeline");

        $cxn->commit();
        return $eisRecord['status'];
      }

      // (2) Match against an existing Person or create a new Person, in
      //     accordance with the Pipeline's Match Strategy
      $personInfo = $this->obtainPerson(
        $pipeline,
        $eis,
        $eisRecord['record'],
        $eisBackendRecord['entity_data']
      );

      $person = $personInfo['person'];

      // We can't record the start history until we have a Person entity
      $this->Cos->People->ExternalIdentities->recordHistory(
        entity: $person,
        action: ActionEnum::PersonPipelineStarted,
        comment: __d('result', 
                    'Pipelines.started',
                    [$pipeline->description, $id, $eis->description, $eisId, $eisBackendRecord['source_key']])
      );

      // (3) Create or update an External Identity based on the sync strategy
      //     and the backend attributes
      $externalIdentity = $this->syncExternalIdentity(
        $pipeline,
        $person,
        $eis,
        $eisRecord['record'],
        $eisBackendRecord['entity_data']
      );

      // If the Person record was matched (meaning it isn't new) create a
      // History Record here, now that we have an External Identity

      if($personInfo['status'] == 'matched') {
        $this->Cos->People->ExternalIdentities->recordHistory(
          entity: $person,
          action: ActionEnum::PersonMatchedPipeline,
          comment: __d('result', 
                       'Pipelines.matched',
                       [$pipeline->description, $id, $eis->description, $eisId, $eisBackendRecord['source_key'], $personInfo['strategy']])
        );
      }

      // (4) Sync the External Identity attributes with the Person record
      $person = $this->syncPerson(
        $pipeline,
        $externalIdentity->id,
        $person
      );

      // (5) Assign Identifiers

      // We can basically ignore the results from assign() since we don't
      // directly report them anywhere.

      $this->Cos->IdentifierAssignments->assign(
        entityType:     'People',
        entityId:       $person->id,
        provision:      false,
// XXX should we pass this in when we have it? CFM-343
        actorPersonId:  null
      );

      // (6) Update Person Status
      // - We no longer need to do anything here since status recalculation
      //   happens automatically
/*
      $person = $this->updatePersonStatus(
        $pipeline,
        $externalIdentity,
        $person
      );*/

      // (7) Provision

      $this->Cos->People->requestProvisioning(
        id:       $person->id,
        context:  ProvisioningContextEnum::Automatic
      );

      $this->Cos->People->ExternalIdentities->recordHistory(
        entity: $person,
        action: ActionEnum::PersonPipelineComplete,
        comment: __d('result', 
                    'Pipelines.complete',
                    [$id, $eisId, $eisBackendRecord['source_key']])
      );

      $this->llog('trace', "Pipeline $id complete for EIS $eisId source key " . $eisBackendRecord['source_key']);

      $cxn->commit();

      return $eisRecord['status'];
    }
    catch(\Exception $e) {
      $cxn->rollback();

      $this->llog('error', "Pipeline $id for EIS $eisId source key " . $eisBackendRecord['source_key'] . " failed: " . $e->getMessage());

      throw new \RuntimeException($e->getMessage());
    }
  }

  /**
   * Pipeline step to create or update the External Identity Source Record.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Pipeline               $pipeline         Pipeline
   * @param  ExternalIdentitySource $eis              External Identity Source
   * @param  string                 $sourceKey        Source Key
   * @param  string                 $sourceRecord     Source Record
   * @param  array                  $eisBackendRecord Record returned by EIS Backend
   * @return array                                    ExtIdentitySourceRecord and change status
   */

  protected function manageEISRecord(
    Pipeline                $pipeline, 
    ExternalIdentitySource  $eis,
    string                  $sourceKey,
    string                  $sourceRecord,
  ): array {
    $status = 'unknown';

    // Are we supposed to use record hashes instead?
    $useHash = isset($eis->hash_source_record) && $eis->hash_source_record;

    // Do we already have an EISRecord for this source_key?
    $eisRecord = $this->ExternalIdentitySources->ExtIdentitySourceRecords
                      ->find()
                      ->where([
                        'ExtIdentitySourceRecords.external_identity_source_id' => $eis->id,
                        'ExtIdentitySourceRecords.source_key'                  => $sourceKey
                      ])
                      ->contain(['ExternalIdentities' => 'People'])
                      ->first();

    if($eisRecord) {
      // Update the record as needed, but only if the source record changed.
      // We consider any aspect of the source record changing to mark the
      // EIS record as changed, even if it's not material to the attributes
      // that construct the External Identity.

      if((empty($eisRecord->source_record) && !empty($sourceRecord))    // New record
         || (!empty($eisRecord->source_record) && empty($sourceRecord)) // Deleted record
         || (!empty($eisRecord->source_record) && !empty($sourceRecord) // Updated record?
             // Note when $useHash we don't md5 the $eisRecord because it was
             // stored as an md5 hash. (This does mean the first time we sync
             // a record after hash_source_record is enabled we'll reprocess it
             // even if nothing changed.)
             && (($useHash && ($eisRecord->source_record != md5($sourceRecord)))
                 || (!$useHash && ($eisRecord->source_record != $sourceRecord))))) {
        // We have an update of some form or another, including, possibly, a delete

        $this->llog('trace', "Updating Record for EIS " . $eis->description . " (" . $eis->id . ") source key $sourceKey");

        $eisRecord->source_record = $useHash ? md5($sourceRecord) : $sourceRecord;
        $eisRecord->last_update = date('Y-m-d H:i:s', time());

        $status = 'updated';
      } else {
        $this->llog('trace', "Record for EIS " . $eis->description . " (" . $eis->id . ") source key $sourceKey unchanged");

        $status = 'unchanged';
      }
    } else {
      // Insert a new record
      $this->llog('trace', "Creating a new Record for EIS " . $eis->description . " (" . $eis->id . ") source key $sourceKey");

      $eisRecord = $this->ExternalIdentitySources->ExtIdentitySourceRecords
                        ->newEntity([
                          'external_identity_source_id' => $eis->id,
                          'source_key'                  => $sourceKey,
                          'source_record'               => $useHash ? md5($sourceRecord) : $sourceRecord,
                          'last_update'                 => date('Y-m-d H:i:s', time())
                        ]);
      
      $status = 'new';
    }

    $this->ExternalIdentitySources->ExtIdentitySourceRecords->saveOrFail($eisRecord);

    // Because --force is implemented in the Pipeline, we need to return the
    // $eisRecord regardless of whether or not it changed, and so we also need
    // a status flag to indicate whether or not it was.
    return [
      'record'  => $eisRecord,
      'status'  => $status
    ];
  }

  /**
   * Map entity data returned from an EIS Backend to the CO.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Pipeline  $pipeline        Pipeline
   * @param  array     $eisAttributes   Attributes provided by EIS Backend
   * @return array                      Attributes adjusted for the CO
   */

  protected function mapAttributesToCO(
    Pipeline          $pipeline,
    array             $eisAttributes
  ): array {
    // We explicitly list the valid models, which will effectively filter
    // out any unsupported noise from the backend. (Unsupported attributes
    // will be ignored on save or throw errors.)

    $ret = [
      // Make sure source_key is a string
      'source_key' => (string)$eisAttributes['source_key']
    ];
    
    if(!empty($eisAttributes['date_of_birth'])) {
      // While we're here, make sure it's in YYYY-MM-DD format. This should fail
      // if the inbound attribute is invalid.
      $dob = \DateTimeImmutable::createFromFormat('Y-m-d', $eisAttributes['date_of_birth']);

      if($dob) {
        $ret['date_of_birth'] = $dob->format('Y-m-d');
      }
    }

    foreach([
      'addresses', 
      'email_addresses',
      'identifiers',
      'names',
      'pronouns',
      'telephone_numbers',
      'urls'
    ] as $m) {
      if(!empty($eisAttributes[$m])) {
        foreach($eisAttributes[$m] as $attr) {
          $copy = $attr;

          // Map the type string to a type ID. If we fail to map the string,
          // log an error but keep going.

          try {
            $copy['type_id'] = $this->Cos->Types
                                    ->getTypeId(
                                      $pipeline->co_id,
                                      Inflector::camelize($m).".type",
                                      $attr['type']
                                    );
            unset($copy['type']);
            $ret[$m][] = $copy;
          }
          catch(\Exception $e) {
            // If we can't find a type we can't insert this record
            $this->llog('error', "Failed to map $attr type \"" . $attr['type'] . "\" to a valid Type ID for EIS record " . $eisAttributes['source_key'] . ", skipping");
          }
        }
      }
    }

    // ad_hoc_attributes require no special handling
    if(!empty($eisAttributes['ad_hoc_attributes'])) {
      $ret['ad_hoc_attributes'] = $eisAttributes['ad_hoc_attributes'];
    }

    if(!empty($eisAttributes['external_identity_roles'])) {
      foreach($eisAttributes['external_identity_roles'] as $role) {
        $rolecopy = [];

        // Start with the single value attributes
        foreach($role as $attr => $val) {
          if(is_array($val)) {
            // This is a related model, skip it for now
            continue;
          }

          if($attr == 'role_key') {
            // Make sure the role key is a string
            $rolecopy['role_key'] = (string)$val;
          } elseif($attr == 'affiliation') {
            // Affiliation needs to be mapped

            $rolecopy['affiliation_type_id'] = $this->Cos->Types
                                                         ->getTypeId(
                                                           $pipeline->co_id,
                                                           'PersonRoles.affiliation_type',
                                                           $val
                                                         );
          } elseif($attr == 'status') {
            // Generally we'll let validation and recalcuation handle status,
            // but if for some reason the backend asserts Deleted (which is used
            // internally as a sync status, and so is not permitted to be asserted
            // by the backend) we'll just convert it to Archived rather than futz
            // around with context specific validation rules.

            // Strictly speaking this is not an Application Rule since backends
            // shouldn't assert Deleted status so we don't need to document a
            // behavior for what happens when they do.

            $rolecopy['status'] = 
              $val == ExternalIdentityStatusEnum::Deleted
              ? ExternalIdentityStatusEnum::Archived
              : $val;
          } else {
// XXX need to add sponsor/manager mapping CFM-33; remove from duplicateFilterEntityData
            // Just copy the attribute
            $rolecopy[$attr] = $val;
          }
        }

        // If no affiliation type was provided by the backend,
        // use the Pipeline's configuration
        if(empty($rolecopy['affiliation_type_id'])) {
          $rolecopy['affiliation_type_id'] = $pipeline->sync_affiliation_type_id;
        }
        
        // Now handle related models
        foreach([
          'addresses', 
          'telephone_numbers'
        ] as $m) {
          if(!empty($role[$m])) {
            foreach($role[$m] as $attr) {
              $copy = $attr;

              // Map the type string to a type ID. If we fail to map the string,
              // log an error but keep going.

              try {
                $copy['type_id'] = $this->Cos->Types
                                        ->getTypeId(
                                          $pipeline->co_id,
                                          Inflector::camelize($m).".type",
                                          $attr['type']
                                        );
                unset($copy['type']);
                $rolecopy[$m][] = $copy;
              }
              catch(\Exception $e) {
                $this->llog('error', "Failed to map $attr type \"" . $attr['type'] . "\" to a valid Type ID for EIS role record " . $role['role_key'] . ", skipping");
              }
            }
          }
        }

        // And just copy ad hoc attributes
        if(!empty($role['ad_hoc_attributes'])) {
          $rolecopy['ad_hoc_attributes'] = $role['ad_hoc_attributes'];
        }

        $ret['external_identity_roles'][] = $rolecopy;
      }
    }

    return $ret;
  }

  /**
   * Map an Identifier of the configured type to a Person ID.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $typeId     Identifier Type ID
   * @param  string $identifier Identifier
   * @return int                Person ID
   */

  protected function mapIdentifier(int $typeId, string $identifier): ?int {
    try {
      $Identifiers = TableRegistry::getTableLocator()->get('Identifiers');

      return $Identifiers->lookupPerson($typeId, $identifier);
    }
    catch(\Exception $e) {
      return null;
    }
  }

  /**
   * Pipeline step to obtain a Person associated with the $eisRecord, possibly
   * by executing the Match Strategy.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Pipeline                 $pipeline       Pipeline
   * @param  ExternalIdentitySource   $eis            External Identity Source
   * @param  ExtIdentitySourceRecord  $eisRecord      External Identity Source Record
   * @param  array                    $eisAttributes  Attributes provided by EIS Backend
   * @return array                                    'person': Person object
   *                                                  'status': 'linked', 'created', 'matched'
   *                                                  'strategy': If status = 'matched', the MatchStrategy
   */

  protected function obtainPerson(
    Pipeline                $pipeline,
    ExternalIdentitySource  $eis,
    ExtIdentitySourceRecord $eisRecord,
    array                   $eisAttributes
  ): array {
    // Shorthand...
    $sourceKey = $eisRecord->source_key;

    // If the $eisRecord has an External Identity attached to it, there must
    // also be a Person, and we can just return that.

    if(!empty($eisRecord->external_identity_id)) {
      $this->llog('trace', "Using previously linked Person " . $eisRecord->external_identity->person->id . " for EIS " . $eis->description . " (" . $eis->id . ") source key $sourceKey");
      return [
        'person' => $eisRecord->external_identity->person,
        'status' => 'linked'
      ];
    }

    // There isn't a Person associated with the request, run the configured
    // Match Strategy to see if one exists

    $person = null;
    $referenceId = null;

    $this->llog('trace', "Using Match Strategy " . $pipeline->match_strategy . " for EIS " . $eis->description . " (" . $eis->id . ") source key $sourceKey");

    switch($pipeline->match_strategy) {
      case MatchStrategyEnum::EmailAddress:
      case MatchStrategyEnum::Identifier:
        $person = $this->searchByAttribute(
          $eis,
          $eisRecord,
          $pipeline->match_strategy,
          ($pipeline->match_strategy == MatchStrategyEnum::EmailAddress
           ? $pipeline->match_email_address_type_id
           : $pipeline->match_identifier_type_id),
          $eisAttributes
        );
        break;
      case MatchStrategyEnum::External:
// XXX If we get a reference ID, attach it to the $eisRecord here CFM-33
        throw new \RuntimeException('NOT IMPLEMENTED');
        break;
      case MatchStrategyEnum::NoMatching:
        // No matching configured, so just fall through and create a new Person
        break;
    }

    if(!$person) {
      // We didn't find an existing Person, so create a new one
      $this->llog('trace', "No existing Person found, creating new Person record for EIS " . $eis->description . " (" . $eis->id . ") source key $sourceKey");

      return [
        'person' => $this->createPersonFromEIS($pipeline, $eis, $eisRecord, $eisAttributes),
        'status' => 'created'
      ];
    }

    return [
      'person'    => $person,
      'status'    => 'matched',
      'strategy'  => $pipeline->match_strategy
    ];
  }

  /**
   * Search for an existing Person using an attribute provided in the EIS Record.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentitySource   $eis            External Identity Source
   * XXX params/return
   * @return Person                   Person if found, null otherwise
   * @throws InvalidArgumentException
   */

  protected function searchByAttribute(
    ExternalIdentitySource  $eis,
    ExtIdentitySourceRecord $eisRecord,
    string                  $matchStrategy,
    int                     $attributeTypeId,
    array                   $attributes
  ): ?Person {
    // By the time the Pipeline is called, $attributes (while an array) should be
    // normalized to the Registry data model (though we haven't yet called
    // mapAttributesToCO).

    // First map the search type ID from the configuration to the expected API string

    $Types = TableRegistry::getTableLocator()->get('Types');

    $typeLabel = $Types->getTypeLabel($attributeTypeId);

    // Make sure we have a valid search item

    $searchValue = null;
    $searchString = null;
    $SearchTable = null;

    if($matchStrategy == MatchStrategyEnum::EmailAddress) {
      $SearchTable = TableRegistry::getTableLocator()->get('EmailAddresses');
      $searchValue = Hash::extract($attributes, "email_addresses.{n}[type=$typeLabel]");

      if(!empty($searchValue)) {
        $searchString = $searchValue[0]['mail'];
      }
    } elseif($matchStrategy == MatchStrategyEnum::Identifier) {
      $SearchTable = TableRegistry::getTableLocator()->get('Identifiers');
      $searchValue = Hash::extract($attributes, "identifiers.{n}[type=$typeLabel]");

      if(!empty($searchValue)) {
        $searchString = $searchValue[0]['identifier'];
      }
    } else {
      throw new \InvalidArgumentException("Unknown Match Strategy '" . $matchStrategy . "' in PipelinesTable::searchByAttribute()");
    }

    if(empty($searchString)) {
      $this->llog('trace', "No attribute found of type $typeLabel for Match Strategy, creating new Person record for EIS " . $eis->description . " (" . $eis->id . ") source key " . $eisRecord->source_key);
      return null;
    }

    // Perform the search

    $personId = null;

    try {
      $personId = $SearchTable->lookupPerson($attributeTypeId, $searchString);
    }
    catch(\Cake\Datasource\Exception\RecordNotFoundException $e) {
      // No match
    }

    if(!empty($personId)) {
      // For consistency with createPersonFromEIS, we retrieve the Person and Names.
      // syncExternalIdentity will pull whatever Person attributes it actually needs.

      // AR-Pipeline-2 Pipeline Person Matching ignores the existing Person status.
      $person = $SearchTable->People->get($personId, ['contain' => ['Names']]);

      // We can't record history yet since we don't have an External Identity
      // (we'll do that in execute()), but we can at least log

      $this->llog('trace', "Matched to existing Person ID $personId using Match Strategy $matchStrategy and search string '$searchString' for EIS " . $eis->description . " (" . $eis->id . ") source key " . $eisRecord->source_key);

      return $person;
    }

    return null;
  }

  /**
   * Sync an External Identity Source Record to an External Identity.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Pipeline                 $pipeline       Pipeline
   * @param  Person                   $person         Person
   * @param  ExternalIdentitySource   $eis            External Identity Source
   * @param  ExtIdentitySourceRecord  $eisRecord      External Identity Source Record
   * @param  array                    $eisAttributes  Attributes provided by EIS Backend
   * @return External Identity                        External Identity, possibly newly created
   */

  protected function syncExternalIdentity(
    Pipeline                $pipeline,
    Person                  $person,
    ExternalIdentitySource  $eis,
    ExtIdentitySourceRecord $eisRecord,
    array                   $eisAttributes
  ): ExternalIdentity {
    if(empty($eisRecord->external_identity_id)) {
      $this->llog('trace', "Creating new External Identity for Person " . $person->id . " from EIS " . $eis->description . " (" . $eis->id . ")");

      // We can substantially just save the backend attributes, with a little
      // bit of preprocessing

      $mapped = $this->mapAttributesToCO($pipeline, $eisAttributes);

      // We also need to add the Person ID
      $mapped['person_id'] = $person->id;

      $entity = $this->Cos->People->ExternalIdentities->newEntity(
        $mapped,
        ['associated' => [
          'Addresses',
          'AdHocAttributes',
          'EmailAddresses',
          'Identifiers',
          'Names',
          'Pronouns',
          'TelephoneNumbers',
          'Urls',
          'ExternalIdentityRoles',
          'ExternalIdentityRoles.AdHocAttributes',
          'ExternalIdentityRoles.Addresses',
          'ExternalIdentityRoles.TelephoneNumbers'
        ]]
      );

      $this->Cos->People->ExternalIdentities->saveOrFail(
        $entity,
        ['associated' => [
          'Addresses',
          'AdHocAttributes',
          'EmailAddresses',
          'Identifiers',
          'Names',
          'Pronouns',
          'TelephoneNumbers',
          'Urls',
          'ExternalIdentityRoles',
          'ExternalIdentityRoles.AdHocAttributes',
          'ExternalIdentityRoles.Addresses',
          'ExternalIdentityRoles.TelephoneNumbers'
        ]]
      );

      // Update $eisRecord with the new external_entity_id
      $eisRecord->external_identity_id = $entity->id;
      $this->ExternalIdentitySources->ExtIdentitySourceRecords->saveOrFail($eisRecord);

      $this->Cos->People->ExternalIdentities->recordHistory(
        entity: $entity,
        action: ActionEnum::PersonAddedPipeline,
        comment: __d('result', 
                    'Pipelines.ei.added',
                    [$pipeline->description, 
                     $pipeline->id,
                     $eis->description,
                     $eis->id,
                     $eisRecord->source_key])
      );

      return $entity;
    } else {
      $this->llog('trace', "Updating existing External Identity " . $eisRecord->external_identity_id . " for Person " . $person->id . " from EIS " . $eis->description . " (" . $eis->id . ")");

      // Start by pulling the current ExternalIdentity with its associated models.

      $externalIdentity = $this->Cos->People->ExternalIdentities->get(
        $eisRecord->external_identity_id,
        ['contain' => [
          'Addresses',
          'AdHocAttributes',
          'EmailAddresses',
          'Identifiers',
          'Names',
          'Pronouns',
          'TelephoneNumbers',
          'Urls',
          'ExternalIdentityRoles' => [
            'AdHocAttributes',
            'Addresses', 
            'TelephoneNumbers'
          ]
        ]]
      );

      // Map the current backend record...
      $mapped = $this->mapAttributesToCO($pipeline, $eisAttributes, $externalIdentity);
      // and try to correlate its record keys.
      $mapped = $this->correlateRecordKeys($externalIdentity, $mapped);

      // Track any new entities so we don't immediately delete them
      $newEntities = [];

      // To avoid complications with patching, we work with individual records,
      // not associated models.
      $externalIdentityEntity = $this->Cos->People->ExternalIdentities->get(
        $eisRecord->external_identity_id
      );

      // Now start the actual diff check with the External Identity itself.
      $this->Cos->People->ExternalIdentities->patchEntity(
        $externalIdentityEntity,
        // is_scalar will keep strings and ints but not arrays (or nulls)
        array_filter($mapped, 'is_scalar')
      );

      if($externalIdentityEntity->isDirty()) {
        $this->llog('trace', "External Identity " . $externalIdentityEntity->id . " updated");
        $this->Cos->People->ExternalIdentities->saveOrFail($externalIdentityEntity);
      }

      // Walk through the top level associated models.
      foreach([
        'Addresses',
        'AdHocAttributes',
        'EmailAddresses',
        'Identifiers',
        'Names',
        'Pronouns',
        'TelephoneNumbers',
        'Urls',
        'ExternalIdentityRoles'
      ] as $model) {
        $amodel = Inflector::underscore($model);

        if(!empty($mapped[$amodel])) {
          // We have one or more of this model in the mapped backend data,
          // check for update vs insert. Delete is handled below.

          foreach($mapped[$amodel] as $arecord) {
            if(!empty($arecord['id'])) {
              // We successfully mapped the backend record to an entity,
              // so this is an update. Find the entity retrieved above
              // with the matching record ID.

              // Note that generally we _won't_ actually perform an update
              // because if a backend record changes it won't successfully map
              // to a record ID in correlateRecordKeys. (While this code will
              // run, isDirty() will generally return false.) Instead, we'll
              // add the "new" record (meaning the changed record) and
              // delete the "old" record (meaning the database copy),
              // resulting in a new ID being assigned. However, if we're
              // ever able to add persistant record keys to the Backend
              // interface this code should "just work".

              // We do rely on this block to process EIR related models,
              // which have roleIdentifiers that allow us to match records.

              foreach($externalIdentity->$amodel as $aentity) {
                if($aentity->id == $arecord['id']) {
                  // This is the record we mapped in the backend data
                  $this->Cos->People->ExternalIdentities->$model->patchEntity(
                    $aentity,
                    // We only need to filter out related models for
                    // ExternalIdentityRoles since the others don't have them
                    array_filter($arecord, 'is_scalar'),
                    ['associated' => []]
                  );

                  if($aentity->isDirty()) {
                    $this->Cos->People->ExternalIdentities->$model->saveOrFail($aentity, ['associated' => false]);
                    $this->llog('trace', "Updated $model " . $aentity->id . " for External Identity " . $externalIdentityEntity->id);
                  }

                  // ----- Process this model's related models ----- //
                  if($model == 'ExternalIdentityRoles') {
                    // We also need to sync the related models. This is just
                    // different enough that it's not worth trying to abstract
                    // this code as a separate function.

                    foreach([
                      'Addresses',
                      'AdHocAttributes',
                      'TelephoneNumbers'
                    ] as $eirmodel) {
                      $aeirmodel = Inflector::underscore($eirmodel);

                      if(!empty($arecord[$aeirmodel])) {
                        // We have one or more Role related model is the mapped
                        // backend data, check for update vs insert.

                        foreach($arecord[$aeirmodel] as $aeirrecord) {
                          if(!empty($aeirrecord['id'])) {
                            // This is an update since we successfully mapped
                            // the backend entityrecord, but see note above
                            // about updates not really happening.

                            foreach($aentity->$aeirmodel as $aeirentity) {
                              if($aeirentity->id == $aeirrecord['id']) {
                                // This is the record we want to work with
                                $this->Cos->People->ExternalIdentities->$model->$eirmodel->patchEntity(
                                  $aeirentity,
                                  $aeirrecord
                                );

                                if($aeirentity->isDirty()) {
                                  $this->Cos->People->ExternalIdentities->$model->$eirmodel->saveOrFail($aeirentity);
                                  $this->llog('trace', "Updated $eirmodel " . $aeirentity->id . " for $model " . $aentity->id);
                                }

                                break; // $aeirentity
                              }
                            }
                          } else {
                            // This is the insertion of a new record to an
                            // _existing_ External Identity Role.

                            $newentity = $this->Cos->People->ExternalIdentities->$model->$eirmodel->newEntity($aeirrecord);

                            $this->Cos->People->ExternalIdentities->$model->$eirmodel->saveOrFail($newentity);
                            $this->llog('trace', "Added $eirmodel " . $newentity->id . " for $model " . $aentity->id);

                            // Inject the new entity so syncPerson sees it
                            $externalIdentity->$model->$eirmodel[] = $newentity;
                            $newEntities[$amodel][] = $newentity->id;
                          }
                        }
                      }

                      // Handle deleted records attached to a _still existing_
                      // External Identity Role.

                      if(!empty($aentity->$aeirmodel)) {
                        foreach($aentity->$aeirmodel as $aeirentity) {
                          $found = false;
                          
                          if(!empty($arecord[$aeirmodel])) {
                            $found = Hash::extract($arecord[$aeirmodel], '{n}[id='.$aeirentity->id.']');
                          }

                          if(!$found) {
                            $this->llog('trace', "Deleted $eirmodel " . $aeirentity->id . " for $model " . $aentity->id);
                            $this->Cos->People->ExternalIdentities->$model->$eirmodel->deleteOrFail($aeirentity);
                            // Note deleted related models remain on the ExternalIdentity in case they
                            // are needed later in the Pipeline.
                          }
                        }
                      }
                    }
                  }
                  // ----- End of related model processing ----- //

                  // Done processing existing parent record, break $aentity loop
                  break;
                }
              }
            } else {
              // This is the insertion of a new record. For ExternalIdentityRoles
              // $arecord should include the associated models, so we don't need
              // to do any special handling for them.

              $associated = [];

              if($model == 'ExternalIdentityRoles') {
                $associated = ['Addresses', 'AdHocAttributes', 'TelephoneNumbers'];
              }

              $newentity = $this->Cos->People->ExternalIdentities->$model->newEntity(
                $arecord,
                ['associated' => $associated]
              );

              $this->Cos->People->ExternalIdentities->$model->saveOrFail(
                $newentity,
                ['associated' => $associated]
              );

              $this->llog('trace', "Added $model " . $newentity->id . " for External Identity " . $externalIdentityEntity->id);

              // Inject the new entity so syncPerson sees it
              $externalIdentity->$amodel[] = $newentity;
              $newEntities[$amodel][] = $newentity->id;
            }
          }
        }

        // Now handled deleted records, for which we only need to check
        // $externalIdentity not being empty. If $mapped is empty we'll
        // simply remove all the related model entities from the
        // $externalIdentity.

        // Deleting an ExternalIdentityRole will delete its associated
        // model entities.

        if(!empty($externalIdentity->$amodel)) {
          foreach($externalIdentity->$amodel as $aentity) {
            $found = false;

            if(!empty($mapped[$amodel])) {
              // Is this an existing entity in the mapped data?
              $found = (bool)Hash::extract($mapped[$amodel], '{n}[id='.$aentity->id.']');

              if(!$found
                 && !empty($newEntities[$amodel])
                 && in_array($aentity->id, $newEntities[$amodel])) {
                // This is a new entity we just added
                $found = true;
              }
            }

            if(!$found) {
              if($model == 'ExternalIdentityRoles') {
                // We have to handle the link to PersonRoles a bit carefully.
                // First, we'll set the status of the Role in accordance with the
                // Pipeline configuration. We do this here because 
                // ExternalIdentityRolesTable::beforeDelete() will set the Person
                // Role foreign key to null to avoid problems with cascading deletes,
                // but then when we sync the Person record later we won't see this
                // PersonRole since the foreign key was nulled out.

                // We don't set the foreign key to null here because we want it
                // to be cleared regardless of how the ExternalIdentity was deleted.
                // eg: If an admin deletes it, the delete should complete but there
                // is no Pipeline context so the PersonRole status won't be updated.

                $prole = $this->Cos->People->PersonRoles->find()
                              ->where(['PersonRoles.source_external_identity_role_id' => $aentity->id])
                              ->contain(['AdHocAttributes', 'Addresses', 'TelephoneNumbers'])
                              ->first();
      
                if(!empty($prole)) {
                  if(isset($prole->frozen) && $prole->frozen) {
                    $this->llog('trace', "Refusing to update frozen Person Role " . $prole->id . " from deleted External Identity Role " . $aentity->id);
                  } else {
                    // Update the status in accordance with the Pipeline configuration
                    $this->llog('trace', "Updating status on PersonRole " . $prole->id . " to " . $pipeline->sync_status_on_delete . " following deletion of source ExternalIdentityRole " . $aentity->id);

                    $prole->status = $pipeline->sync_status_on_delete;
                    $this->Cos->People->PersonRoles->saveOrFail($prole);

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
                          $this->llog('trace', "Deleted $aeirmodel " . $aeirentity->id . " for Person Role " . $prole->id);
                          $this->Cos->People->PersonRoles->$eirmodel->deleteOrFail($aeirentity);
                        }
                      }
                    }
                  }
                }
              }

              $this->llog('trace', "Deleted $model " . $aentity->id . " for External Identity " . $externalIdentityEntity->id);
              $this->Cos->People->ExternalIdentities->$model->deleteOrFail($aentity);
              // Note deleted related models remain on the ExternalIdentity in case they
              // are needed later in the Pipeline.
            }
          }
        }
      }

      // Note $externalIdentity may include deleted related models.

      return $externalIdentity;
    }
  }

  /**
   * Sync an External Identity to a Person.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Pipeline    $pipeline            Pipeline
   * @param  int         $externalIdentityId  External Identity ID
   * @param  Person      $person              Person
   * @return Person                           Person
   */

  protected function syncPerson(
    Pipeline          $pipeline,
    int               $externalIdentityId,
    Person            $person
  ): Person {
    // We re-pull the External Identity to account for any changes that might have
    // been processed by syncExternalIdentity.
    $externalIdentity = $this->Cos->People->ExternalIdentities->get(
      $externalIdentityId,
      ['contain' => [
        'Addresses',
        'AdHocAttributes',
        'EmailAddresses',
        'Identifiers',
        'Names',
        'Pronouns',
        'TelephoneNumbers',
        'Urls',
        'ExternalIdentityRoles' => [
          'AdHocAttributes',
          'Addresses', 
          'TelephoneNumbers'
        ]
      ]]
    );

    // Because ExternalIdentities belongTo People, we can assume we have at least
    // a Person object here (it would have been created by obtainPerson if there
    // wasn't one at the start of the process).

    // Start with the directly related models

    foreach([
      'Addresses',
      'AdHocAttributes',
      'EmailAddresses',
      'Identifiers',
      'Names',
      'Pronouns',
      'TelephoneNumbers',
      'Urls'
    ] as $model) {
      $amodel = Inflector::underscore($model);
      // sourcefk = eg source_name_id
      $sourcefk = $this->Cos->People->$model->sourceForeignKey();
      // sourceModel = eg SourceName
      $sourceModel = StringUtilities::foreignKeyToClassName($sourcefk);
      // sourceEntity = eg source_name
      $sourceEntity = "source_" . $amodel;

      // Pull the current set of associated records for this model.
      // We can filter down to those that came from _any_ source (ie
      // the source attribute is not null), but filtering only those
      // from _this_ source requires a JOIN that isn't really worth the
      // effort.

      $curentities = $this->Cos->People->$model
                          ->find()
                          ->where([
                            $model.'.person_id'             => $person->id,
                            $model.'.'.$sourcefk." IS NOT"  => null
                          ])
                          ->contain([$sourceModel])
                          ->all();
      
      // Track which IDs we've seen to facilitate deletes.
      $seenIds = [];

      if(!empty($externalIdentity->$amodel)) {
        // Walk through the ExternalIdentity's entities, adding or updating as
        // appropriate.

        foreach($externalIdentity->$amodel as $eientity) {
          if($eientity->deleted) {
            // We ignore entities flagged as deleted, we'll calculate deletions
            // separately in case we need to fix manually mucked up data.
            continue;
          }

          // Convert the ExternalIdentity attribute to an array and filter it
          $newdata = $this->duplicateFilterEntityData($eientity);
          
          // Add the foreign keys
          $newdata[$sourcefk] = $eientity->id;
          $newdata['person_id'] = $person->id;

          // Do we have a corresponding record on the Person?
          $found = $curentities->firstMatch([$sourcefk => $eientity->id]);

          if($found) {
            // There is an existing record, update it (if it changed) _unless_
            // the attribute record is frozen.

            if($model == 'EmailAddresses' && $found->verified) {
              // If the Person Email Address is verified and the EI Email Address
              // is _not_, we preserve the verification flag _unless_ the mail address
              // has changed.
              
              // This is effectively a combination of AR-EmailAddress-2 Editing an
              // Email Address (but not its Type) associated with a Person will revert
              // it to unverified and AR-EmailAddress-4 A frozen Email Address may be
              // verified if it is otherwise eligible for verification.
              if($newdata['mail'] == $found->mail) {
                $newdata['verified'] = $found->verified;
              }
            }

            if($model == 'Names' && $found->primary_name) {
              // Preserve the primary name flag, if set
              $newdata['primary_name'] = true;
            }

            $this->Cos->People->$model->patchEntity($found, $newdata);

            if($found->isDirty()) {
              if(isset($found->frozen) && $found->frozen) {
                $this->llog('trace', "Refusing to update frozen $model " . $found->id . " to Person from External Identity " . $externalIdentity->id);
              } else {
                $this->Cos->People->$model->saveOrFail($found);
                $this->llog('trace', "Updated $model " . $found->id . " to Person from External Identity " . $externalIdentity->id);
              }
            }
          } else {
            // This is a new record. We have to convert the External Identity to
            // an array to create the new Person entity anyway, so we use that
            // as an opportunity to set the foreign key.

            // Note that certain application logic (eg: no primary names for
            // EIS data copied to People) is implemented in beforeMarshal on
            // the appropriate Table.

            // Default the new attribute to not frozen
            $newdata['frozen'] = false;

            $newentity = $this->Cos->People->$model->newEntity($newdata);
            $this->Cos->People->$model->saveOrFail($newentity);

            $this->llog('trace', "Added $model " . $newentity->id . " to Person from External Identity " . $externalIdentity->id);
          }
        }

        $seenIds[] = $eientity->id;
      }

      // Now walk through the Person entities, and delete any that we didn't see.
      // In theory we could make Cake do this automatically via a HasOne
      // relation, but it's a bit tricky to make Cake handle relations within
      // the same object correctly to cascade the delete. Also, we need to
      // filter entities that aren't associated with this EIS.

      if(!empty($curentities)) {
        foreach($curentities as $aentity) {
          if(!empty($aentity->$sourceEntity->external_identity_id)
             && $aentity->$sourceEntity->external_identity_id == $externalIdentityId) {
            // $aentity is an entity attached to the Person and was sourced from
            // an attribute associated with the current External Identity (as opposed
            // to another EI associated with the Person); we search through the
            // source attributes for one with a corresponding source key ID
            $found = Hash::extract($externalIdentity[$amodel], '{n}[id='.$aentity->$sourcefk.']');

            if(!$found) {
              if(isset($aentity->frozen) && $aentity->frozen) {
                $this->llog('trace', "Refusing to delete frozen $model " . $aentity->id . " on Person from External Identity " . $externalIdentity->id);
              } else {
                $this->llog('trace', "Deleted $model " . $aentity->id . " for Person " . $person->id);
                $this->Cos->People->$model->deleteOrFail($aentity);
              }
            }
          }
        }
      }
    }

    // Next sync External Identity Roles to Person Roles.
    // **Be careful to note the different terminology for EIR vs PR**
    // Track which person roles we've seen to remove any deleted ones.
    $seenRoleIds = [];

    if(!empty($externalIdentity->external_identity_roles)) {
      // $sourcefk = 'source_external_identity_role_id'
      $sourcefk = $this->Cos->People->PersonRoles->sourceForeignKey();

      // Pull the current Person Roles
      $curentities = $this->Cos->People->PersonRoles
                          ->find()
                          ->where([
                            'PersonRoles.person_id'        => $person->id,
                            "PersonRoles.$sourcefk IS NOT" => null
                          ])
                          ->contain(['AdHocAttributes', 'Addresses', 'TelephoneNumbers'])
                          ->all();

      foreach($externalIdentity->external_identity_roles as $eirentity) {
        if($eirentity->deleted) {
          // We ignore entities flagged as deleted, we'll calculate deletions
          // separately in case we need to fix manually mucked up data.
          continue;
        }

        // Convert the ExternalIdentityRole to an array and filter it
        $newdata = $this->duplicateFilterEntityData($eirentity);
        
        // Insert foreign keys
        $newdata[$sourcefk] = $eirentity->id;
        $newdata['person_id'] = $person->id;

        // And set the COU, if configured. Currently all Roles from a given
        // External Identity sync to the same COU.
        if(!empty($pipeline->sync_cou_id)) {
          $newdata['cou_id'] = $pipeline->sync_cou_id;
        }

        // Map Manager and Sponsor identifiers, if set, to corresponding People.
        // If not found, we'll log a warning but otherwise proceed.
        // Also, we need a configured Identifier type.
        
        foreach(['manager', 'sponsor'] as $f) {
          $eirField = $f . "_identifier";
          $prField = $f . "_person_id";

          // Populate a null value by default, in case an existing foreign key
          // is removed
          $newdata[$prField] = null;

          if(!empty($eirentity->$eirField)) {
            if(!empty($pipeline->sync_identifier_type_id)) {
              $newdata[$prField] = $this->mapIdentifier(
                                    $pipeline->sync_identifier_type_id,
                                    $eirentity->$eirField
                                   );
              
              if(empty($newdata[$prField])) {
                $this->llog('trace', "Unable to map $eirField for External Identity Role " . $eirentity->id . " because no Person with the specified identifier was found");
              }
            } else {
              $this->llog('trace', "Unable to map $eirField for External Identity Role " . $eirentity->id . " because there is no Sync Identifier Type configured for Pipeline " . $pipeline->id);
            }
          }
        }

        // duplicateFilterEntityData() will remove status, but we need to
        // set it back (if asserted) or set a default (if not).
        if(!empty($eirentity->status)) {
          if($eirentity->status == ExternalIdentityStatusEnum::Archived) {
            // The EI Role was flagged as Archived, update the Person Role to
            // the status configured in the Pipeline. In this scenario, we don't
            // otherwise remove associated MVEAs.
            $newdata['status'] = $pipeline->sync_status_on_delete;
          } else {
            $newdata['status'] = $eirentity->status;
          }
        } else {
          // Default to Active status for this Role (subject to validity date recalculation)
          $newdata['status'] = StatusEnum::Active;
        }

        // Do we have a corresponding record on the Person?
        $found = $curentities->firstMatch([$sourcefk => $eirentity->id]);

        if($found) {
          // There is an existing record, update it (if it changed) _unless_
          // the role record is frozen.

          $this->Cos->People->PersonRoles->patchEntity($found, $newdata, ['associated' => []]);

          if($found->isDirty()) {
            if(isset($found->frozen) && $found->frozen) {
              $this->llog('trace', "Refusing to update frozen Person Role " . $found->id . " to Person from External Identity " . $externalIdentity->id);
            } else {
              $this->Cos->People->PersonRoles->saveOrFail($found, ['associated' => false]);
              $this->llog('trace', "Updated PersonRole " . $found->id . " to Person from External Identity " . $externalIdentity->id);
            }
          }
        } else {
          // Default the new attribute to not frozen
          $newdata['frozen'] = false;

          $newentity = $this->Cos->People->PersonRoles->newEntity($newdata, ['associated' => []]);
          $this->Cos->People->PersonRoles->saveOrFail($newentity, ['associated' => false]);

          $this->llog('trace', "Added PersonRole " . $newentity->id . " to Person from External Identity " . $externalIdentity->id);
        }

        // Now handle related models

        foreach([
          'ad_hoc_attributes' => 'AdHocAttributes',
          'addresses' => 'Addresses',
          'telephone_numbers' => 'TelephoneNumbers'
        ] as $m => $t) {
          $seenRelatedModelIds = [];

          if(!empty($eirentity->$m)) {
            foreach($eirentity->$m as $relatedEntity) {
              // Convert the related entity to an array and filter it
              $newdata = $this->duplicateFilterEntityData($relatedEntity);
              
              // Insert foreign keys
              $rsourcefk = $this->Cos->People->PersonRoles->$t->sourceForeignKey();
              $newdata[$rsourcefk] = $relatedEntity->id;
              $newdata['person_role_id'] = $found->id ?? $newentity->id;

              // See if we have a correponding Person Role entity, but only if
              // we're working with an existing Person Role

              $relatedFound = null;

              if(!empty($found->$m)) {
                $relatedFound = Hash::extract($found->$m, '{n}['.$rsourcefk.'='.$relatedEntity->id.']');

                if($relatedFound) {
                  // Hash returns an array, but we want the first object in it

                  $relatedFound = $relatedFound[0];

                  // There is an existing record, update it (if it changed) _unless_
                  // the record is frozen

                  $this->Cos->People->PersonRoles->$t->patchEntity($relatedFound, $newdata, ['associated' => []]);

                  if($relatedFound->isDirty()) {
                    if(isset($relatedFound->frozen) && $relatedFound->frozen) {
                      $this->llog('trace', "Refusing to update frozen $t " . $relatedFound->id . " to Person Role from External Identity Role $t " . $relatedEntity->id);
                    } else {
                      $this->Cos->People->PersonRoles->$t->saveOrFail($relatedFound, ['associated' => false]);
                      $this->llog('trace', "Updated $t " . $relatedFound->id . " to Person Role from External Identity Role $t " . $relatedEntity->id);
                    }
                  }

                  $seenRelatedModelIds[] = $relatedFound->id;
                }
              }

              // We need to use empty() because Hash might return an empty array
              if(empty($relatedFound)) {
                // We have a new related entity on an existing Person Role, or a new
                // Person Role (and therefore all related entities are new)

                // Default the new attribute to not frozen
                $newdata['frozen'] = false;

                $newrentity = $this->Cos->People->PersonRoles->$t->newEntity($newdata, ['associated' => []]);
                $this->Cos->People->PersonRoles->$t->saveOrFail($newrentity, ['associated' => false]);

                $this->llog('trace', "Added PersonRole $t " . $newrentity->id . " to Person Role from External Identity Role $t " . $relatedEntity->id);

                $seenRelatedModelIds[] = $newrentity->id;
              }
            }
          }

          // Delete any related models we didn't see in the source EI Role
          if(!empty($found->$m)) {
            foreach($found->$m as $curRelatedEntity) {
              if(!in_array($curRelatedEntity->id, $seenRelatedModelIds)) {
                if(isset($curRelatedEntity->frozen) && $curRelatedEntity->frozen) {
                  $this->llog('trace', "Refusing to delete frozen $t " . $curRelatedEntity->id . " from Person Role $t " . $relatedEntity->id);
                } else {
                  $this->llog('trace', "Deleted $t " . $curRelatedEntity->id . " for Person Role " . $found->id);
                  $this->Cos->People->PersonRoles->$t->deleteOrFail($curRelatedEntity);
                }
              }
            }
          }
        }

        $seenRoleIds[] = $eirentity->id;
      }

      // For any roles we didn't see, we don't actually delete them, instead
      // we set them to the configured status. This allows Expiration Policies
      // to be applied, and also allows us to reactive a role if it comes back
      // with the same Role Key.

      // Under what circumstances would we have a Person Role with a foreign key
      // to an EI Role, but we didn't see that EI Role when walking the loop,
      // above?
      // - If the backend changed the status to Suspended or Archived, the EIR
      //   would still be valid, and we would see it above.
      // - If the backend deleted the role entirely, syncExternalIdentity would
      //   notice, and explicitly change the PersonRole status to $delete_status
      //   while ExternalIdentityRoles::beforeDelete would update the PR foreign
      //   key to no longer point to the source EIR, so we wouldn't see the PR
      //   at all.
      // - A manually deleted EIR would behave similarly.
/*
      if(!empty($curentities->person_roles)) {
        foreach($curentities->person_roles as $currole) {
          if(!in_array($currole->id, $seenRoleIds)) {
          }
        }
      }*/
    }

    return $person;
  }

  /**
   * Update Person status upon completion of Pipeline syncing.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Pipeline                 $pipeline         Pipeline
   * @param  ExternalIdentity         $externalIdentity External Identity
   * @param  Person                   $person           Person
   * @return Person                                     Person
   *

  protected function updatePersonStatus(
    Pipeline          $pipeline,
    ExternalIdentity  $externalIdentity,
    Person            $person
  ): Person {
    // Role status is set during syncPerson, so all we need to do is update
    // the Person status, and then only if the current status is Pending.

    if($person->status == StatusEnum::Pending) {
      $person->status = StatusEnum::Active;

      $this->Cos->People->saveOrFail($person, ['associated' => false]);
      $this->llog('trace', "Pipeline " . $pipeline->id . " updating Person " . $person->id . " status to Active");
    }

    return $person;
  }*/

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
    
    $this->registerStringValidation($validator, $schema, 'description', false);

    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $validator->add('match_strategy', [
      'content' => ['rule' => ['inList', MatchStrategyEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('match_strategy');

    $validator->add('match_email_address_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString(
      field: 'match_email_address_type_id',
      when: function ($context) { 
        return (!empty($context['data']['match_strategy'])
                && ($context['data']['match_strategy'] == MatchStrategyEnum::EmailAddress));
      }
    );

    $validator->add('match_identifier_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString(
      field: 'match_identifier_type_id',
      when: function ($context) { 
        return (!empty($context['data']['match_strategy'])
                && ($context['data']['match_strategy'] == MatchStrategyEnum::Identifier));
      }
    );

    $validator->add('match_server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('match_server_id');

    $validator->add('sync_affiliation_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('sync_affiliation_type_id');

    $validator->add('sync_cou_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('sync_cou_id');

    $validator->add('sync_replace_cou_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('sync_replace_cou_id');

    $validator->add('sync_status_on_delete', [
      'content' => ['rule' => ['inList', DeletedRoleStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('sync_status_on_delete');

    $validator->add('sync_identifier_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('sync_identifier_type_id');
    
    return $validator; 
  }
}