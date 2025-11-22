<?php
/**
 * COmanage Registry Api Sources Table
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

namespace ApiConnector\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

class ApiSourcesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\TabTrait;

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
    $this->belongsTo('ExternalIdentitySources');
    $this->belongsTo('ApiUsers');

    $this->hasMany('ApiConnector.ApiSourceEndpoints')
      ->setDependent(true)
      ->setCascadeCallbacks(true);
    $this->hasMany('ApiConnector.ApiSourceRecords')
      ->setDependent(true)
      ->setCascadeCallbacks(true);
    
    $this->setDisplayField('id');
    
    $this->setPrimaryLink(['external_identity_source_id']);
    $this->setRequiresCO(true);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
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
   * Map API field names to Registry data model names.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $model      Model name (in API format)
   * @param  array  $attributes Model attributes
   * @return array              Mapped model attributes
   */

  protected function mapApiToRegistry(string $model, array $attributes): array {
    // All API attributes must be defined in the fieldMap, even if both names are the same
    $fieldMap = [
      // sorAttributes = top level External Identity
      'sorAttributes' => [
        // Dates should correctly marshal to a DateTime object without us doing anything
        'dateOfBirth' => 'date_of_birth'
      ],
      // roles = External Identity Role
      'roles' => [
        'roleIdentifier' => 'role_key',
        'affiliation' => 'affiliation',
        'department' => 'department',
        'managerIdentifier' => 'manager_identifier',
        'rank' => 'ordr',
        'organization' => 'organization',
        'sponsorIdentifier' => 'sponsor_identifier',
        'status' => 'status',
        'title' => 'title',
        'validFrom' => 'valid_from',
        'validThrough' => 'valid_through'
      ],
      // MVEAs
      'addresses' => [
        'country' => 'country',
        'language' => 'language',
        'locality' => 'locality',
        'postalCode' => 'postal_code',
        'region' => 'state',
        'room' => 'room',
        'streetAddress' => 'street',
        'type' => 'type'
      ],
      'adhoc' => [
        'tag' => 'tag',
        'value' => 'value'
      ],
      'emailAddresses' => [
        'address' => 'mail',
        'type' => 'type',
        'verified' => 'verified'
      ],
      'identifiers' => [
        'identifier' => 'identifier',
        'type' => 'type'
      ],
      'names' => [
        'family' => 'family',
        'given' => 'given',
        'language' => 'language',
        'middle' => 'middle',
        'prefix' => 'honorific',
        'suffix' => 'suffix',
        'type' => 'type'
      ],
      'telephoneNumbers' => [
        'number' => 'number',
        'type' => 'type'
      ],
      'urls' => [
        'type' => 'type',
        'url' => 'url'
      ]
    ];

    $ret = [];

    foreach($attributes as $attr => $value) {
      if(isset($fieldMap[$model][$attr])) {
        $ret[ $fieldMap[$model][$attr] ] = $value;
      }
    }

    return $ret;
  }

    /**
   * Remove a record from the External Identity Source.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int        $id         Api Source ID
   * @param  string     $sorLabel   System of Record Label
   * @param  string     $sorId      API System of Record ID
   * @return bool                   True on success
   * @throws RecordNotFoundException
   */

  public function remove(int $id, string $sorLabel, string $sorId): bool {
    // Pull our configuration
    $apiSource = $this->get($id, contain: ['ExternalIdentitySources']);

    // Like upsert(), we don't really need $sorLabel, but we check it for
    // consistency with upsert() (which also doesn't really need it).

    if(empty($apiSource->external_identity_source->sor_label)
       || $apiSource->external_identity_source->sor_label != $sorLabel) {
      throw new \InvalidArgumentException("Requested SOR Label $sorLabel does not match configuration");
    }

    // Remove the ApiSourceRecord for this $source_key from the cache

    try {
      // Start a Transaction
      $cxn = $this->getConnection();
      $cxn->begin();

      $apiSourceRecord = $this->ApiSourceRecords->find()
                                                ->where([
                                                  'api_source_id' => $id,
                                                  'source_key'    => $sorId
                                                ])
                                                ->firstOrFail();
    
      $this->ApiSourceRecords->delete($apiSourceRecord);

      // Run sync
      $this->ExternalIdentitySources->sync($apiSource->external_identity_source_id, $sorId);

      $cxn->commit();

      return true;
    }
    catch(\Exception $e) {
      $cxn->rollback();

      throw $e;
    }
  }


  /**
   * Convert a record from the ApiSource message to a record suitable for
   * construction of an Entity.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array  $result   ApiSource message
   * @return array            Entity record (in array format)
   */

  protected function resultToEntityData(array $result): array {
    // Convert the inbound message format to the Entity record array format.
    // They're actually very similar, but we need to do some work, in particular
    // around mapping field names.

    $eidata = [];

    // Start with single-value EI attributes

    $eidata = $this->mapApiToRegistry('sorAttributes', $result['sorAttributes']);

    // EI MVEAs, which can generally just be copied in place

    foreach([
      'addresses' => 'addresses',
      'adhoc' => 'ad_hoc_attributes',
      'emailAddresses' => 'email_addresses',
      'identifiers' => 'identifiers',
      'names' => 'names',
      'telephoneNumbers' => 'telephone_numbers',
      'urls' => 'urls'
    ] as $apiModel => $registryModel) {
      if(!empty($result['sorAttributes'][$apiModel])) {
        foreach($result['sorAttributes'][$apiModel] as $m) {
          $eidata[$registryModel][] = $this->mapApiToRegistry($apiModel, $m);
        }
      }
    }

    // EI Roles

    if(!empty($result['sorAttributes']['roles'])) {
      foreach($result['sorAttributes']['roles'] as $roleData) {
        if(!empty($roleData['roleIdentifier'])) {
          // The top level role data
          $eirdata = $this->mapApiToRegistry('roles', $roleData);

          // EIR MVEAs

          foreach([
            'addresses' => 'addresses',
            'adhoc' => 'ad_hoc_attributes',
            'telephoneNumbers' => 'telephone_numbers',
            'urls' => 'urls'
          ] as $apiModel => $registryModel) {
            if(!empty($roleData[$apiModel])) {
              foreach($roleData[$apiModel] as $m) {
                $eirdata[$registryModel][] = $this->mapApiToRegistry($apiModel, $m);
              }
            }
          }

          $eidata['external_identity_roles'][] = $eirdata;
        }
      }
    }

    return $eidata;
  }

  /**
   * Retrieve a record from the External Identity Source.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentitySource $source     EIS Entity with instantiated plugin configuration
   * @param  string                 $source_key Backend source key for requested record
   * @return array                              Array of source_key, source_record, and entity_data
   * @throws RecordNotFoundException
   */

  public function retrieve(
    \App\Model\Entity\ExternalIdentitySource $source,
    string $source_key
  ): array {
    $ret = [
      'source_key' => $source_key
    ];

    // Pull the ApiSourceRecord for this $source_key from the cache

    $apiSourceRecord = $this->ApiSourceRecords->find()
                                              ->where([
                                                'api_source_id' => $source->api_source->id,
                                                'source_key'    => $source_key
                                              ])
                                              ->first();

    if(!$apiSourceRecord) {
      // Record was deleted
      $ret['source_record'] = null;
      $ret['entity_data'] = null;
    } else {
      $ret['source_record'] = $apiSourceRecord->source_record;
      $ret['entity_data'] = $this->resultToEntityData(
        json_decode(json: $apiSourceRecord->source_record, associative: true)
      );
    }

    return $ret;
  }

  /**
   * Search the External Identity Source.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  ExternalIdentitySource $source       EIS Entity with instantiated plugin configuration
   * @param  array                  $searchAttrs  Array of search attributes and values, as configured by searchAttributes()
   * @return array                                Array of matching records
   * @throws InvalidArgumentException
   */

  public function search(
    \App\Model\Entity\ExternalIdentitySource $source,
    array $searchAttrs
  ): array {
    $ret = [];

    // We search the cache of existing records (push), but not (currently) a
    // remote URL (pull). For now we only search on SORID, which matches v4 behavior.

    $records = $this->ApiSourceRecords->find()
                                      ->where([
                                        'api_source_id' => $source->api_source->id,
                                        'source_key'    => $searchAttrs['q']
                                      ])
                                      ->all();
    
    if(!empty($records)) {
      foreach($records as $rec) {
        $ret[ $rec->source_key ] = $this->resultToEntityData(json_decode($rec->source_record, true));
      }
    }

    return $ret;
  }

  /**
   * Obtain the set of searchable attributes for this backend.
   * 
   * @since  COmanage Registry v5.0.0
   * @return array    Array of searchable attributes and localized descriptions
   */

  public function searchableAttributes(): array {
    // In v4 we aonly accepted SORID. For now, that's all we implement in search(),
    // but we could enhance this to search the text of source_record as well.

    return [
      'q' => __d('field', 'search.placeholder')
    ];
  }

  /**
   * Insert or update an ApiSource record and associated External Identity.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id         ApiSource ID
   * @param  string $sorLabel   System of Record Label
   * @param  string $sorId      System of Record ID
   * @param  array  $attributes Attributes from message body
   * @return array              bool 'new': true if a new External Identity was created
   * @throws InvalidArgumentException
   */

  public function upsert(
    int     $id,
    string  $sorLabel,
    string  $sorId,
    array   $attributes
  ): array {
    $ret = [];

    // Pull our configuration
    $apiSource = $this->get($id, contain: ['ExternalIdentitySources']);

    // Strictly speaking we don't need $sorLabel since we know which configuration
    // to use from the ApiSource ID, and $sorLabel might not be unique across COs
    // in a multi-tenant environment. Eventually we could support multiple
    // Systems of Record within the same ApiSource configuration, but for now
    // we just make sure $sorLabel matches the configuration and throw an error
    // if it doesn't.

    if(empty($apiSource->external_identity_source->sor_label)
       || $apiSource->external_identity_source->sor_label != $sorLabel) {
      throw new \InvalidArgumentException("Requested SOR Label $sorLabel does not match configuration");
    }

    // Create or Update the API Source Record

    // For consistency, we'll always make the source_record pretty (which
    // should also make it slightly easier for an admin to look at it.
    $sourceRecord = json_encode($attributes, JSON_PRETTY_PRINT);

    // Note we transition from "SOR ID" (TAP API terminology) to "Source Key"
    // (Registry terminology) here
    $apiSourceRecord = $this->ApiSourceRecords->find()
                                              ->where([
                                                'api_source_id' => $id,
                                                'source_key'    => $sorId
                                              ])
                                              ->first();
    
    if(!empty($apiSourceRecord)) {
      // Update

      $apiSourceRecord->source_record = $sourceRecord;
    } else {
      // Insert

      $apiSourceRecord = $this->ApiSourceRecords->newEntity([
        'api_source_id' => $id,
        'source_key'    => $sorId,
        'source_record' => $sourceRecord
      ]);

      $ret['new'] = true;
    }

    $this->ApiSourceRecords->saveOrFail($apiSourceRecord);

    // Note update of ApiSourceRecord doesn't necessarily imply update of
    // an associated External Identity - it could be an insert. Regardless,
    // ExternalIdentitySources::sync (really Pipelines::execute) will deal with it.
    
    $this->ExternalIdentitySources->sync($apiSource->external_identity_source_id, $sorId);

    return $ret;
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('external_source_identity_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('external_source_identity_id');

    return $validator; 
  }
}