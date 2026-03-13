<?php
/**
 * COmanage Registry Env Sources Table
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace EnvSource\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use \EnvSource\Lib\Enum\EnvSourceSpModeEnum;

class EnvSourcesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  // Cache of Table Models
  protected $tableCache = [];
  
  // Cache of the type map, for flat mode
  protected $typeCache = [];

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.1.0
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
    $this->belongsTo('AddressTypes')
         ->setClassName('Types')
         ->setForeignKey('address_type_id')
         ->setProperty('address_type');
    $this->belongsTo('DefaultAffiliationTypes')
         ->setClassName('Types')
         ->setForeignKey('default_affiliation_type_id')
         ->setProperty('default_affiliation_type');
    $this->belongsTo('EmailAddressTypes')
         ->setClassName('Types')
         ->setForeignKey('email_address_type_id')
         ->setProperty('email_address_type');
    $this->belongsTo('NameTypes')
         ->setClassName('Types')
         ->setForeignKey('name_type_id')
         ->setProperty('name_type');
    $this->belongsTo('TelephoneNumberTypes')
         ->setClassName('Types')
         ->setForeignKey('telephone_number_type_id')
         ->setProperty('telephone_number_type');
    
    $this->hasMany('EnvSource.EnvSourceIdentities')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->setDisplayField('id');
    
    $this->setPrimaryLink(['external_identity_source_id']);
    $this->setRequiresCO(true);

    $this->setEditContains([
      'ExternalIdentitySources',
    ]);

    $this->setViewContains([
      'ExternalIdentitySources',
    ]);

    $this->setAutoViewVars([
      'addressTypes' => [
        'type' => 'type',
        'attribute' => 'Addresses.type'
      ],
      'defaultAffiliationTypes' => [
        'type'      => 'type',
        'attribute' => 'PersonRoles.affiliation_type'
      ],
      'emailAddressTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ],
      'nameTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type'
      ],
      'spModes' => [
        'type' => 'enum',
        'class' => 'EnvSource.EnvSourceSpModeEnum'
      ],
      'telephoneNumberTypes' => [
        'type' => 'type',
        'attribute' => 'TelephoneNumbers.type'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false, //['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.2.0
   * @param \EnvSource\Model\Entity\EnvSource $entity Entity to generate display field for
   * @return string         Display field
   */
  public function generateDisplayField(\EnvSource\Model\Entity\EnvSource $entity): string {
    return __d('env_source', 'display.EnvSource', [$entity->external_identity_source->description]);
  }

  /**
   * Obtain the set of changed records from the source database.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  ExternalIdentitySource $source     External Identity Source
   * @param  int                    $lastStart  Timestamp of last run
   * @param  int                    $curStart   Timestamp of current run
   * @return array|bool                         An array of changed source keys, or false
   */

  public function getChangeList(
    \App\Model\Entity\ExternalIdentitySource $source,
    int $lastStart, // timestamp of last run
    int $curStart   // timestamp of current run
  ): array|bool {
    return false;
  }

  /**
   * Obtain the full set of records from the source database.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  ExternalIdentitySource $source     External Identity Source
   * @return array                              An array of source keys
   */

  public function inventory(
    \App\Model\Entity\ExternalIdentitySource $source
  ): array {
// XXX do we want to implement inventory of cached records?

    return false;
  }

  /**
   * Convert a record from the EnvSource data to a record suitable for
   * construction of an Entity. This call is for use with Relational Mode.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  EnvSource  $EnvSource  EnvSource configuration entity
   * @param  array      $result     Array of Env attributes
   * @return array                  Entity record (in array format)
   */

  protected function resultToEntityData(
    \EnvSource\Model\Entity\EnvSource $EnvSource,
    array $result
  ): array {
    // We don't need most of the $EnvSource configuration since EnvSourceCollector::parse
    // already mapped the variable names for us. We do need to know the sp_mode for parsing
    // multiple values, and also we need the types.

    // Build the External Identity as an array
    $eidata = [];

    // We don't currently have a field to record DoB, so we need to null it
    $eidata['date_of_birth'] = null;

    // Single value fields that map to the External Identity Role
    $role = [
      // We only support one role per record
      'role_key' => '1',
      'affiliation' => $this->DefaultAffiliationTypes->getTypeLabel($EnvSource->default_affiliation_type_id)
    ];

    foreach([
      'env_affiliation' => 'affiliation',
      'env_department' => 'department',
      'env_organization' => 'organization',
      'env_title' => 'title'
    ] as $v => $f) {
      if(!empty($result[$v])) {
        $role[$f] = $result[$v];
      }
    }

    $eidata['external_identity_roles'][] = $role;

    // XXX Name should probably honor CO Settings?
    $name = [
      'type' => $this->NameTypes->getTypeLabel($EnvSource->name_type_id)
    ];

    foreach([
      'env_name_honorific' => 'honorific',
      'env_name_given' => 'given',
      'env_name_middle' => 'middle',
      'env_name_family' => 'family',
      'env_name_suffix' => 'suffix'
    ] as $v => $f) {
      if(!empty($result[$v])) {
        $name[$f] = $result[$v];
      }
    }

    $eidata['names'][] = $name;

    // XXX Address should probably honor CO Settings?
    $address = [
      'type' => $this->AddressTypes->getTypeLabel($EnvSource->address_type_id)
    ];

    foreach([
      'env_address_street' => 'street',
      'env_address_locality' => 'locality',
      'env_address_state' => 'state',
      'env_address_postal_code' => 'postalcode',
      'env_address_country' => 'country'
    ] as $v => $f) {
      if(!empty($result[$v])) {
        $address[$f] = $result[$v];
      }
    }

    if(count(array_keys($address)) > 1) {
      // We have a field other than type, so add it to the result

      $eidata['addresses'][] = $address;
    }

    // Email Address
    if(!empty($result['env_mail'])) {
      $mails = [];

      // We accept multiple values if supported by the configured SP software.

      switch($EnvSource->sp_mode) {
        case EnvSourceSpModeEnum::Shibboleth:
          $mails = explode(";", $result['env_mail']);
          break;
        case EnvSourceSpModeEnum::SimpleSamlPhp:
          $mails = explode(",", $result['env_mail']);
          break;
        default:
          // We dont' try to tokenize the string
          $mails = [ $result['env_mail' ]];
          break;
      }

      foreach($mails as $m) {
        $eidata['email_addresses'][] = [
          'mail' => $m,
          'type' => $this->EmailAddressTypes->getTypeLabel($EnvSource->email_address_type_id),
          // We treat externally asserted email addresses as not verified,
          // but this can be overridden in the Pipeline configuration.
          // Note voPersonVerifiedEmail is capable of transmitted verified status,
          // so in theory we could define a configuration to check for that.
          'verified' => false
        ];
      }
    }

    // Walk through all defined Identifiers
    foreach([
      'env_identifier_eppn' => 'eppn',
      'env_identifier_eptid' => 'eptid',
      'env_identifier_epuid' => 'epuid',
      'env_identifier_network' => 'network',
      'env_identifier_oidcsub' => 'oidcsub',
      'env_identifier_samlpairwiseid' => 'pairwiseid',
      'env_identifier_samlsubjectid' => 'subjectid'
      // We don't include source_key (sorid) because the Pipeline will automatically insert it
    ] as $v => $t) {
      // Because we're in an External Identity context, we don't need to map the
      // type strings to IDs (that happens in the Pipeline)

      if(!empty($result[$v])) {
        $eidata['identifiers'][] = [
          'identifier' => $result[$v],
          'type' => $t
        ];
      }
    }

    // Telephone Number
    if(!empty($result['env_telephone_number'])) {
      $eidata['telephone_numbers'][] = [
        'number' => $result['env_telephone_number'],
        'type' => $this->TelephoneNumberTypes->getTypeLabel($EnvSource->telephone_number_type_id)
      ];
    }

    return $eidata;
  }

  /**
   * Retrieve a record from the External Identity Source.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  ExternalIdentitySource $source     EIS Entity with instantiated plugin configuration
   * @param  string                 $source_key Backend source key for requested record
   * @return array                              Array of source_key, source_record, and entity_data
   * @throws InvalidArgumentException
   */

  public function retrieve(
    \App\Model\Entity\ExternalIdentitySource $source,
    string $source_key
  ): array {
    $entity = $this->EnvSourceIdentities->find()
                                        ->where([
                                          'env_source_id' => $source->env_source->id,
                                          'source_key'    => $source_key
                                        ])
                                        ->first();

    if($entity) {
      return [
        'source_key'    => $entity->source_key,
        'source_record' => $entity->env_attributes,
        'entity_data'   => $this->resultToEntityData($source->env_source, json_decode($entity->env_attributes, true))
      ];
    } else {
      throw new \InvalidArgumentException(__d('error', 'notfound', [$source_key]));
    }

    return [];
  }

  /**
   * Search the External Identity Source.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  ExternalIdentitySource $source       EIS Entity with instantiated plugin configuration
   * @param  array                  $searchAttrs  Array of search attributes and values, as configured by searchAttributes()
   * @return array                                Array of matching records
   * @throws InvalidArgumentException
   */

  public function search(
    \App\Model\Entity\ExternalIdentitySource $source,
    array $searchAttrs
  ): array {
    // We currently only support retrieving based on Source Key
    $ret = [];
    
    try {
      $record = $this->retrieve($source, $searchAttrs['source_key']);

      $ret[ $record['source_key'] ] = $record['entity_data'];

      return $ret;
    }
    catch(\InvalidArgumentException $e) {
      // Source Key not found in table
      return $ret;
    }
  }
  
  /**
   * Obtain the set of searchable attributes for this backend.
   * 
   * @since  COmanage Registry v5.1.0
   * @return array    Array of searchable attributes and localized descriptions
   */

  public function searchableAttributes(): array {
    return [
      'source_key' => __d('field', 'source_key')
    ];
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.1.0
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

    $validator->add('redirect_on_duplicate', [
      'content' => ['rule' => 'url']
    ]);
    $validator->allowEmptyString('redirect_on_duplicate');

    $validator->add('sp_mode', [
      'content' => ['rule' => ['inList', EnvSourceSpModeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('sp_mode');

    $validator->add('sync_on_login', [
      'content' => ['rule' => 'boolean']
    ]);
    $validator->allowEmptyString('sync_on_login');

    // These are all required even if the deployer doesn't intend to populate the
    // corresponding Env fields

    foreach([
      'default_affiliation_type_id',
      'address_type_id',
      'email_address_type_id',
      'name_type_id',
      'telephone_number_type_id'
    ] as $field) {
      $validator->add($field, [
        'content' => ['rule' => 'isInteger']
      ]);
      $validator->notEmptyString($field);
    }

    // For simplicity, we don't require any specific fields to be populated except SORID,
    // though depending on the CO configuration some fields may effectively be required.

    $this->registerStringValidation($validator, $schema, 'env_identifier_sourcekey', true);

    foreach([
      'env_address_street',
      'env_address_locality',
      'env_address_state',
      'env_address_postalcode',
      'env_address_country',
      'env_affiliation',
      'env_department',
      'env_identifier_eppn',
      'env_identifier_eptid',
      'env_identifier_epuid',
      'env_identifier_network',
      'env_identifier_oidcsub',
      'env_identifier_samlpairwiseid',
      'env_identifier_samlsubjectid',
      'env_mail',
      'env_name_honorific',
      'env_name_given',
      'env_name_middle',
      'env_name_family',
      'env_name_suffix',
      'env_organization',
      'env_telephone_number',
      'env_title'
    ] as $field) {
      $this->registerStringValidation($validator, $schema, $field, false);
    }

    $this->registerStringValidation($validator, $schema, 'lookaside_file', false);

    return $validator; 
  }
}