<?php
/**
 * COmanage Registry Organization Sources Table
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use App\Lib\Enum\OrgSyncModeEnum;
use App\Lib\Util\StringUtilities;

class OrganizationSourcesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\ClonableTrait;
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
   * @since  COmanage Registry v5.3.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    $this->addBehavior('Changelog');
    $this->addBehavior('Clonable');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Cos');
    
    $this->hasMany('OrganizationSourceRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->bindPluginRelations();
    
    $this->setDisplayField('description');
    
    $this->setPrimaryLink(['co_id']);
    $this->setRequiresCO(true);
    // We need to calculate the redirect URL for sync ourselves (in the controller)
    $this->setRedirectGoal('special', 'sync');
    $this->setAllowLookupPrimaryLink(['retrieve', 'search', 'sync']);

    $this->setAutoViewVars([
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'organization_source'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'OrgSyncModeEnum'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'configure' =>  ['platformAdmin', 'coAdmin'],
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'retrieve' =>   ['platformAdmin', 'coAdmin'],
        'search' =>     ['platformAdmin', 'coAdmin'],
        'sync' =>       ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
        'status' =>   ['platformAdmin', 'coAdmin']
      ],
      'related' => [
        'table' => [
          'OrganizationSourceRecords'
        ]
      ]
    ]);
  }

  /**
   * Map entity data returned from an Organization Source Backend to the CO.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  int    $coId     CO ID
   * @param  array  $attrs    Array of entity data
   * @param  array  $related  Related models to process
   * @return array            Entity data adjusted for the CO
   */

  protected function mapAttributesToCO(int $coId, ?array $attrs, array $related): array {
    // Check if we'er handling a deleted record
    if(empty($attrs)) {
      return [];
    }

    // Start by copying the single valued attributes

    $ret = [
      'co_id' => $coId,
      // Make sure source_key is a string
      'source_key' => (string)$attrs['source_key']
    ];

    foreach(['name', 'description', 'saml_scope', 'logo_url'] as $a) {
      // It might be better to pull these fields from the model in case they get updated...

      $ret[$a] = $attrs[$a];
    }

    // Map the Organization type. If we fail to map the entry we need to fail this record
    // (getTypeId will throw an exception).
    $ret['type_id'] = $this->Cos->Types->getTypeId($coId, 'Organizations.type', $attrs['type']);

    foreach($related as $m) {
      // By explicitly listing related models we'll drop any noise from the backend

      // $m = (eg) 'Identifiers', $um = (eg) 'identifiers'
      $um = Inflector::underscore($m);

      if(!empty($attrs[$um])) {
        foreach($attrs[$um] as $a) {
          $copy = $a;

          // Map the type string to a type ID. If we fail to map the string,
          // log an error but keep going.

          try {
            if($m != 'AdHocAttributes') {
              // AdHoc Attributes are not typed

              $copy['type_id'] = $this->Cos
                                      ->Types
                                      ->getTypeId($coId, $m.".type", $a['type']);
            }

            unset($copy['type']);
            $ret[$um][] = $copy;
          }
          catch(\Exception $e) {
            // If we can't find a type we can't insert this record
            $this->llog('error', "Failed to map attribute type \"" . $a['type'] . "\" to a valid Type ID for Organization Source record " . $attrs['source_key'] . ", skipping");
          }
        }
      }
    }

    return $ret;
  }

  /**
   * Retrieve a record from an Organization Source.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  int    $id         Organization Source ID
   * @param  string $sourceKey  OS Backend Source Key
   * @return array              Array of source_key, source_record, and entity_data
   */

  public function retrieve(int $id, string $sourceKey): array {
    $source = $this->getPluginConfiguration($id);

    $pModel = StringUtilities::pluginModel($source->plugin);

    $record = $this->$pModel->retrieve($source, $sourceKey);

    if($record['entity_data']) {
      // Inject the source key so every backend doesn't have to do this,
      // but only if the backend returned a record.
      $record['entity_data']['source_key'] = $sourceKey;

      $record['entity_data']['identifiers'][] = [
        'identifier' => $sourceKey,
        'type' => 'sorid'
      ];
    }
    
    return $record;
  }

  /**
   * Search the Organization Source.
   *
   * @since  COmanage Registry v5.3.0
   * @param  int    $id           Organization Source ID
   * @param  array  $searchAttrs  Array of search attributes and values, as configured by searchAttributes()
   * @return array                Array of matching records
   */

  public function search(int $id, array $attrs): array {
    $source = $this->getPluginConfiguration($id);

    $pModel = StringUtilities::pluginModel($source->plugin);

    return $this->$pModel->search($source, $attrs);
  }

  /**
   * Obtain the set of searchable attributes for this backend.
   *
   * @since  COmanage Registry v5.3.0
   * @return array    Array of searchable attributes and localized descriptions
   */

  public function searchableAttributes(int $id) {
    return $this->getPluginModel($id)->searchableAttributes();
  }

  /**
   * Sync an Organization from a Source to a CO.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  int      $id               Organization Source ID
   * @param  string   $sourceKey        OS Backend Source Key
   * @param  Boolean  $force            If true, force a sync even if the source record has not changed
   * @param  Boolean  $processDeletes   If true, delete Organizations no longer available in the backend
   * @return array                      'id': ID of Organization, 'status': Record status (synced, unchanged, removed, deleted)
   */
  
  public function sync(
    int $id, 
    string $sourceKey, 
    bool $force=false,
    bool $processDeletes=false
  ): array {
    // sync is where we really deviate from the External Identity Source model since we
    // don't use Pipelines. All the work is done here. (There's also no create option.)

    // The related models we support
    $relatedModels = [
      'Addresses',
      'AdHocAttributes',
      'Contacts',
      'EmailAddresses',
      'Identifiers',
      'TelephoneNumbers',
      'Urls'
    ];

    // The related models we save
    $saveModels = $relatedModels;
    $saveModels[] = 'OrganizationSourceRecords';

    // Get our configuration
    $source = $this->getPluginConfiguration($id);

    // $pModel = $this->getPluginModel($id);
    // $oscfg = $pModel->getConfig();

    // Pull the current record from the backend
    $backendRecord = null;

    try {
      $backendRecord = $this->retrieve($id, $sourceKey);
    }
    catch(\InvalidArgumentException $e) {
      // Record not found, fall through, we'll process this below
    }
    // Let other exceptions bubble up, including OverflowExceptions (> 1 record returned)

    // Next see if we have an OrganizationSourceRecord

    $osr = $this->OrganizationSourceRecords
                ->find()
                ->where([
                  'organization_source_id' => $id,
                  'source_key'             => $sourceKey
                ])
                ->contain(['Organizations' => $relatedModels])
                ->first();

    if(empty($backendRecord)) {
      if(!empty($osr->organization->id)) {
        if($processDeletes) {
          // If we are processing deletes, it means we are in Full mode, and so we should
          // actually (try to) delete the specified Organization. (If the admin wants to
          // keep these Organizations around, they can use Accrual or Update modes.)
          // There _should_ be an OSR since we shouldn't know about an Organization that
          // no longer has a backend record otherwise.

          $this->OrganizationSourceRecords->Organizations->delete($osr->organization->id);
        }

        // We'll note the backend record was removed, whether or not we delete it
        if($coJobId) {
/* XXX
          $this->Co->CoJob->CoJobHistoryRecord->record($coJobId,
                                                        $sourceKey,
                                                        $processDeletes ?  _txt('rs.os.src.deleted') : _txt('rs.os.src.removed'),
                                                        null,
                                                        null,
                                                        JobStatusEnum::Complete); */
        }

        return [
          'id' => $osr->organization->id,
          'status' => $processDeletes ? 'deleted' : 'removed'
        ];
      } else {
        throw new \RuntimeException("Unexpected empty Organization ID when retrieving $sourceKey");
      }
    }

    // Start a transaction
    $cxn = ConnectionManager::get('default');
    $cxn->begin();

    if(empty($osr)) {
      // This is a new record, start by creating an Organization Source Record
      // and a new Organization. The backend record is more or less in the format
      // we need, including for related models. However, we do need to map Type IDs.

      $org = $this->mapAttributesToCO($source->co_id, $backendRecord['entity_data'], $relatedModels);

      $org['organization_source_record'] = [
        'organization_source_id'  => $id,
        'source_key'              => $sourceKey,
        'source_record'           => $backendRecord['source_record'],
        'last_update'             => date('Y-m-d H:i:s'),
      ];

      $entity = $this->OrganizationSourceRecords
                     ->Organizations
                     ->newEntity($org, $saveModels);

      // When we save any related Email Addresses, EmailAddressesTable::afterMarshal
      // will unset the verified flag. For People, PipelinesTable will create a
      // Verification _after_ the sync is processed, but we don't really have a
      // concept of verifying Email Addresses for Organizations, so a sync will
      // always result in unverified Email Addresses, even if the source plugin
      // verifies them. This could be reconsidered in the future if we have a use
      // case for verified Organization Email Addresses. (in v4 we passed a
      // "trustVerified" flag.)

      try {
        $this->OrganizationSourceRecords
             ->Organizations
             ->saveOrFail($entity, $saveModels);
      }
      catch(\Exception $e) {
        $cxn->rollback();
        throw $e;
      }

      $cxn->commit();

      return [
        'id' => $entity->id,
        'status' => 'synced'
      ];
    } else {
      // We're updating an existing record

      if(!$force) {
        // Check to see if the record change

        if(!empty($osr->source_record)
           && !empty($backendRecord['source_record'])
           && ($osr->source_record == $backendRecord['source_record'])) {
          // Record is unchanged
/*
          if($coJobId) {
            $this->Co->CoJob->CoJobHistoryRecord->record($coJobId,
                                                          $sourceKey,
                                                          _txt('rs.os.src.unchanged'),
                                                          null,
                                                          null,
                                                          JobStatusEnum::Complete);
          }*/

          $cxn->commit();

          return array(
            'id' => $osr->organization_id,
            'status' => 'unchanged'
          );
        }
      }

      try {
        // We'll first update the OrganizationSourceRecord
        $osr->source_record = $backendRecord['source_record'];
        $osr->last_update = date('Y-m-d H:i:s');

        $this->OrganizationSourceRecords->saveOrFail($osr, ['associated' => false]);

        // Map the current backend record...
        $mapped = $this->mapAttributesToCO(
          $source->co_id, 
          $backendRecord['entity_data'], 
          $relatedModels
        );

        // Track any new entities so we don't immediately delete them
        $newEntities = [];

        // To avoid complications with patching, we work with individual records,
        // not associated models.
        $organizationEntity = $this->Cos->Organizations->get($osr->organization_id);

        // Now start the actual diff check with the Organization itself.
        $this->Cos->Organizations->patchEntity(
          $organizationEntity,
          // is_scalar will keep strings and ints but not arrays (or nulls)
          array_filter($mapped, 'is_scalar')
        );

        if($organizationEntity->isDirty()) {
// XXX either add more logging or remove this
          $this->llog('trace', "Organization " . $organizationEntity->id . " updated");
          $this->Cos->Organizations->saveOrFail($organizationEntity);
        }

        foreach($relatedModels as $m) {
          // Because there are no primary keys in the source data, we have to guess if the
          // current record is known or not. This effectively means we can only add or delete
          // (or leave existing records unchanged).

          // This was based on v4 OrgIdentitySource::syncOrgIdentity() and is conceptually
          // similar to v5 PipelinesTable.

          // $m = TelephoneNumbers
          // $property = telephone_numbers
          $property = Inflector::tableize($m);

          // Records obtained from the Organization Source
          $newRecords = isset($mapped[$property]) ? $mapped[$property] : [];

          foreach($osr->organization->$property as $curRecord) {
            $found = false;

            foreach($newRecords as $i => $newRecord) {
              if($curRecord->isProbablyThisArray($newRecord)) {
                // We appear to have found a match for an unchanged record, so
                // remove it from the list of new records to process and flag it
                // as found so we don't delete it.

                unset($newRecords[$i]);
                $found = true;
                break;
              }
            }

            if(!$found) {
              // Remove $curRecord since it doesn't appear to match anything,
              // unless it is frozen

              if(isset($curRecord->frozen) && $curRecord->frozen) {
                $this->llog('trace', "Refusing to delete frozen $m " . $curRecord->id . " on Organization " . $organizationEntity->id);
              } else {
                $this->llog('trace', "Deleting $m " . $curRecord->id . " from Organization " . $organizationEntity->id);
                $this->Cos->Organizations->$m->delete($curRecord);
              }
            }
          }

          // Now look for new records to add.

          foreach($newRecords as $newRecord) {
            // Since we've already found all records that are the same in both arrays,
            // we simply add each remaining new record, inserting the Organization ID
            // to link the record.

            $newRecord['organization_id'] = $osr->organization_id;

            $newEntity = $this->Cos->Organizations->$m->newEntity($newRecord);
            $this->Cos->Organizations->$m->saveOrFail($newEntity);
            
            $this->llog('trace', "Added $m " . $newEntity->id . " to Organization " . $organizationEntity->id);
          }
        }

        $cxn->commit();
      }
      catch(\Exception $e) {
        $cxn->rollback();
        throw $e;
      }

      return [
        'id' => $osr->organization_id,
        'status' => 'synced'
      ];
    }
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');
    
    $this->registerStringValidation($validator, $schema, 'description', true);
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', OrgSyncModeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'plugin', true);

    return $validator; 
  }
}