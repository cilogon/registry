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
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('DefaultAddressTypes')
         ->setClassName('Types')
         ->setForeignKey('default_address_type_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('default_address_type');
    $this->belongsTo('DefaultEmailAddressTypes')
         ->setClassName('Types')
         ->setForeignKey('default_email_address_type_id')
         ->setProperty('default_email_address_type');
    $this->belongsTo('PersonPickerEmailAddressType')
         ->setClassName('Types')
         ->setForeignKey('person_picker_email_address_type_id')
         ->setProperty('person_picker_email_address_type');
    $this->belongsTo('DefaultIdentifierTypes')
         ->setClassName('Types')
         ->setForeignKey('default_identifier_type_id')
         ->setProperty('default_identifier_type');
    $this->belongsTo('PersonPickerIdentifierTypes')
      ->setClassName('Types')
      ->setForeignKey('person_picker_identifier_type_id')
      ->setProperty('person_picker_identifier_type');
    $this->belongsTo('DefaultNameTypes')
         ->setClassName('Types')
         ->setForeignKey('default_name_type_id')
         ->setProperty('default_name_type');
    $this->belongsTo('DefaultPronounTypes')
         ->setClassName('Types')
         ->setForeignKey('default_pronoun_type_id')
         ->setProperty('default_pronoun_type');
    $this->belongsTo('DefaultTelephoneNumberTypes')
         ->setClassName('Types')
         ->setForeignKey('default_telephone_number_type_id')
         ->setProperty('default_telephone_number_type');
    $this->belongsTo('DefaultUrlTypes')
         ->setClassName('Types')
         ->setForeignKey('default_url_type_id')
         ->setProperty('default_url_type');

    $this->setDisplayField('co_id');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setAllowUnkeyedPrimaryCO(['manage']);
    $this->setRedirectGoal('self');
    
    $this->setAutoViewVars([
      'defaultAddressTypes' => [
        'type' => 'type',
        'attribute' => 'Addresses.type'
      ],
      'defaultEmailAddressTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ],
      'defaultIdentifierTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'personPickerEmailAddressTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ],
      'personPickerIdentifierTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'defaultNameTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type'
      ],
      'defaultPronounTypes' => [
        'type' => 'type',
        'attribute' => 'Pronouns.type'
      ],
      'defaultTelephoneNumberTypes' => [
        'type' => 'type',
        'attribute' => 'TelephoneNumbers.type'
      ],
      'defaultUrlTypes' => [
        'type' => 'type',
        'attribute' => 'Urls.type'
      ],
      'permittedFieldsNames' => [
        'type' => 'enum',
        'class' => 'PermittedNameFieldsEnum'
      ],
      'permittedFieldsTelephoneNumbers' => [
        'type' => 'enum',
        'class' => 'PermittedTelephoneNumberFieldsEnum'
      ],
      'requiredFieldsAddresses' => [
        'type' => 'enum',
        'class' => 'RequiredAddressFieldsEnum'
      ],
      'requiredFieldsNames' => [
        'type' => 'enum',
        'class' => 'RequiredNameFieldsEnum'
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
   * @throws ConflictException when default values exist
   */
  
  public function addDefaults(int $coId): int {
    // Default values for each setting
    
    $defaultSettings = [
      'co_id'                             => $coId,
      'default_address_type_id'           => null,
      'default_email_address_type_id'     => null,
      'default_identifier_type_id'        => null,
      'default_name_type_id'              => null,
      'default_pronoun_type_id'           => null,
      'default_telephone_number_type_id'  => null,
      'default_url_type_id'               => null,
      'permitted_fields_name'             => PermittedNameFieldsEnum::HGMFS,
      'permitted_fields_telephone_number' => PermittedTelephoneNumberFieldsEnum::CANE,
      'person_picker_email_type'          => null,
      'person_picker_identifier_type'     => null,
      'person_picker_display_types'       => true,
      'required_fields_address'           => RequiredAddressFieldsEnum::Street,
      'required_fields_name'              => RequiredNameFieldsEnum::Given,
      'search_global_limit'               => DEF_GLOBAL_SEARCH_LIMIT,
      'search_limited_models'             => false
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
    ];

    // Check if we already have Settings for this CO
    $settings = $this->find()->where([ 'co_id' => $defaultSettings['co_id'] ])->first();
    // If the record already exists throw an exception
    if(!empty($settings->{'id'})) {
      throw new \ConflictException(__d('error', 'default.conflict'));
    }

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
      if(preg_match('/^default_[a-z]+_type_id$/', $col)) {
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
    $validator->add('default_address_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('default_address_type_id');
    
    $validator->add('default_email_address_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('default_email_address_type_id');
    
    $validator->add('default_identifier_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('default_identifier_type_id');
    
    $validator->add('default_name_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('default_name_type_id');
    
    $validator->add('default_pronoun_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('default_pronoun_type_id');

    $validator->add('default_telephone_number_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('default_telephone_number_type_id');
    
    $validator->add('default_url_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('default_url_type_id');
    
    $validator->add('permitted_name_fields', [
      'content' => ['rule' => ['inList', PermittedNameFieldsEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('permitted_name_fields');
    
    $validator->add('permitted_telephone_number_fields', [
      'content' => ['rule' => ['inList', PermittedTelephoneNumberFieldsEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('permitted_fields_telephone_number');

    $validator->add('required_fields_address', [
      'content' => ['rule' => ['inList', RequiredAddressFieldsEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('required_fields_address');
    
    $validator->add('required_fields_name', [
      'content' => ['rule' => ['inList', RequiredNameFieldsEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('required_name_fields');
    
    $validator->add('search_global_limited_models', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('search_global_limited_models');
    
    $validator->add('search_global_limit', [
      'content' => ['rule' => ['comparison', '>', 0]]
    ]);
    $validator->notEmptyString('search_global_limit');

    $validator->add('person_picker_email_type', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('person_picker_email_type');

    $validator->add('person_picker_identifier_type', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('person_picker_identifier_type');


    $validator->add('person_picker_display_types', [
      'content' => ['rule' => 'boolean']
    ]);
    $validator->allowEmptyString('person_picker_display_types');

    return $validator;
  }
}