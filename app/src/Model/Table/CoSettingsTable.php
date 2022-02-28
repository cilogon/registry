<?php
/**
 * COmanage Registry CO Settings Table
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

/**
 * To add a new CO setting, define the field in schema.json and then add it below
 * in addDefault(), validationDefault(), the CO Settings view, and wherever else
 * it'll be used.
 *
 * Where possible, field names should start with the relevant model (if there is
 * one) that the setting primarily applies to.
 *
 * We explicitly use a single, wide table for CO Settings. This is actually a
 * very efficient model for the database, vs joining across multiple settings
 * tables.
 */

declare(strict_types = 1);

namespace App\Model\Table;

use \Cake\ORM\Table;
use \Cake\Validation\Validator;
use \App\Lib\Enum\PermittedNameFieldsEnum;
use \App\Lib\Enum\PermittedTelephoneNumberFieldsEnum;
use \App\Lib\Enum\RequiredAddressFieldsEnum;
use \App\Lib\Enum\RequiredNameFieldsEnum;

class CoSettingsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Timestamp');
    
    // CO Settings are (a special type of) configuration
    $this->setIsConfigurationTable(true);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('AddressDefaultTypes')
         ->setClassName('Types')
         ->setForeignKey('address_default_type_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('address_default_type');
    $this->belongsTo('EmailAddressDefaultTypes')
         ->setClassName('Types')
         ->setForeignKey('email_address_default_type_id')
         ->setProperty('email_address_default_type');
    $this->belongsTo('IdentifierDefaultTypes')
         ->setClassName('Types')
         ->setForeignKey('identifier_default_type_id')
         ->setProperty('identifier_default_type');
    $this->belongsTo('NameDefaultTypes')
         ->setClassName('Types')
         ->setForeignKey('name_default_type_id')
         ->setProperty('name_default_type');
    $this->belongsTo('TelephoneNumberDefaultTypes')
         ->setClassName('Types')
         ->setForeignKey('telephone_number_default_type_id')
         ->setProperty('telephone_number_default_type');
    $this->belongsTo('UrlDefaultTypes')
         ->setClassName('Types')
         ->setForeignKey('url_default_type_id')
         ->setProperty('url_default_type');
    
    $this->setDisplayField('co_id');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setAllowUnkeyedPrimaryCO(['manage']);
    $this->setRedirectGoal('self');
    
    $this->setAutoViewVars([
      'addressDefaultTypes' => [
        'type' => 'type',
        'attribute' => 'Addresses.type'
      ],
      'addressRequiredFields' => [
        'type' => 'enum',
        'class' => 'RequiredAddressFieldsEnum'
      ],
      'emailAddressDefaultTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ],
      'identifierDefaultTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'nameDefaultTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type'
      ],
      'namePermittedFields' => [
        'type' => 'enum',
        'class' => 'PermittedNameFieldsEnum'
      ],
      'nameRequiredFields' => [
        'type' => 'enum',
        'class' => 'RequiredNameFieldsEnum'
      ],
      'telephoneNumberDefaultTypes' => [
        'type' => 'type',
        'attribute' => 'TelephoneNumbers.type'
      ],
      'telephoneNumberPermittedFields' => [
        'type' => 'enum',
        'class' => 'PermittedTelephoneNumberFieldsEnum'
      ],
      'urlDefaultTypes' => [
        'type' => 'type',
        'attribute' => 'Urls.type'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id). Since each CO's
      // CoSetting is created during CO Setup, admins can only edit.
      'entity' => [
        'delete' => false,
        'edit'   => ['platformAdmin', 'coAdmin'],
        'view'   => ['platformAdmin', 'coAdmin']    // Required for REST API
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add'    => false,
        'index'  => ['platformAdmin', 'coAdmin'],   // Required for REST API
        'manage' => ['platformAdmin', 'coAdmin']
      ]
    ]);
  }
  
  /**
   * Add default settings to a CO. Intended for use at CO Setup.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int $coId CO ID
   * @return int       CoSettings ID
   */
  
  public function addDefaults(int $coId): int {
    // Default values for each setting
    
    $defaultSettings = [
      'co_id'                             => $coId,
      'address_default_type_id'           => null,
      'address_required_fields'           => RequiredAddressFieldsEnum::Street,
      'email_address_default_type_id'     => null,
      'identifier_default_type_id'        => null,
      'name_default_type_id'              => null,
      'name_permitted_fields'             => PermittedNameFieldsEnum::HGMFS,
      'name_required_fields'              => RequiredNameFieldsEnum::Given,
      'telephone_number_default_type_id'  => null,
      'telephone_number_permitted_fields' => PermittedTelephoneNumberFieldsEnum::CANE,
      'url_default_type_id'               => null
// XXX to add new settings, set a default here, then add a validation rule below
//     also update data model documentation
      // 'disable_expiration'         => false,
      // 'disable_ois_sync'           => false,
      // 'enable_normalization'       => true,
      // 'enable_nsf_demo'            => false,
      // 'group_validity_sync_window' => DEF_GROUP_SYNC_WINDOW,
      // 'invitation_validity'        => DEF_INV_VALIDITY,
      // 'garbage_collection_interval'  => DEF_GARBAGE_COLLECT_INTERVAL,
      // 'permitted_fields_name'      => PermittedNameFieldsEnum::HGMFS,
      // 'required_fields_addr'       => RequiredAddressFieldsEnum::Street,
      // 'required_fields_name'       => RequiredNameFieldsEnum::Given,
      // 'sponsor_co_group_id'        => null,
      // 'sponsor_eligibility'        => SponsorEligibilityEnum::CoOrCouAdmin,
      // 't_and_c_login_mode'         => TAndCLoginModeEnum::NotEnforced,
      // 'enable_empty_cou'           => false,
      // 'theme_stacking'             => SuspendableStatusEnum::Suspended,
      // 'co_theme_id'                => null,
      // 'global_search_limit'        => DEF_GLOBAL_SEARCH_LIMIT
    ];
    
    $obj = $this->newEntity($defaultSettings);
    
    $this->save($obj);
    
    return $obj->id;
  }
  
  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  CoSetting $entity Entity to generate display field for
   * @return string         Display field
   */
  
  public function generateDisplayField(\App\Model\Entity\CoSetting $entity): string {
    return __d('controller', 'CoSettings', [99]);
  }
  
  /**
   * Determine if a requested Type is in use as a default via CoSettings.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int  $id Type ID
   * @return bool     true if the type is in use as a default, false otherwise
   */
  
  public function typeIsDefault(int $id): bool {
    // We actually don't need to care which type we're being asked about, since
    // $id can only resolve to a single type (as the primary key for the types
    // table). We simply see if $id is in any _default_type_id field.
    
    $orclause = [];
    
    foreach($this->getSchema()->columns() as $col) {
      if(preg_match('/_default_type_id$/', $col)) {
        $orclause[] = [$col => $id];
      }
    }
    
    $count = $this->find('all')->where(['OR' => $orclause])->count();
    
    return (bool)$count;
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $validator->add('address_default_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('address_default_type_id');
    
    $validator->add('address_required_fields', [
      'content' => ['rule' => ['inList', RequiredAddressFieldsEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('address_required_fields');
    
    $validator->add('email_address_default_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('email_address_default_type_id');
    
    $validator->add('identifier_default_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('identifier_default_type_id');
    
    $validator->add('name_default_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('name_default_type_id');
    
    $validator->add('name_permitted_fields', [
      'content' => ['rule' => ['inList', PermittedNameFieldsEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('name_permitted_fields');
    
    $validator->add('name_required_fields', [
      'content' => ['rule' => ['inList', RequiredNameFieldsEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('name_required_fields');
    
    $validator->add('telephone_number_default_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('telephone_number_default_type_id');
    
    $validator->add('telephone_number_permitted_fields', [
      'content' => ['rule' => ['inList', PermittedTelephoneNumberFieldsEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('telephone_number_permitted_fields');
    
    $validator->add('url_default_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('url_default_type_id');
    
    return $validator; 
  }
}