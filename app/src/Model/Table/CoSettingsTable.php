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
use \Cake\ORM\TableRegistry;
use \Cake\Validation\Validator;
use \App\Lib\Enum\TAndCLoginModeEnum;
use \App\Lib\Enum\PermittedNameFieldsEnum;
use \App\Lib\Enum\PermittedTelephoneNumberFieldsEnum;
use \App\Lib\Enum\RequiredAddressFieldsEnum;
use \App\Lib\Enum\RequiredNameFieldsEnum;

class CoSettingsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
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
    $this->belongsTo('DefaultIdentifierTypes')
         ->setClassName('Types')
         ->setForeignKey('default_identifier_type_id')
         ->setProperty('default_identifier_type');
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
    $this->belongsTo('EmailDeliveryAddressTypes')
         ->setClassName('Types')
         ->setForeignKey('email_delivery_address_type_id')
         ->setProperty('email_delivery_address_type');
    $this->belongsTo('EmailSmtpServers')
         ->setClassName('Servers')
         ->setForeignKey('email_smtp_server_id')
         ->setProperty('email_smtp_server');
    $this->belongsTo('PersonPickerEmailAddressType')
         ->setClassName('Types')
         ->setForeignKey('person_picker_email_address_type_id')
         ->setProperty('person_picker_email_address_type');
    $this->belongsTo('PersonPickerIdentifierTypes')
      ->setClassName('Types')
      ->setForeignKey('person_picker_identifier_type_id')
      ->setProperty('person_picker_identifier_type');
    $this->belongsTo('Themes');

    $this->setDisplayField('co_id');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    $this->setAllowUnkeyedPrimaryCO(['manage']);
    $this->setRedirectGoal('self');

    $this->setEditContains(['Cos']);
    
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
      'emailDeliveryAddressTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ],
      'emailSmtpServers' => [
        'type' => 'select',
        'model' => 'Servers',
        'where' => ['plugin' => 'CoreServer.SmtpServers']
      ],
      'permittedFieldsNames' => [
        'type' => 'enum',
        'class' => 'PermittedNameFieldsEnum'
      ],
      'permittedFieldsTelephoneNumbers' => [
        'type' => 'enum',
        'class' => 'PermittedTelephoneNumberFieldsEnum'
      ],
      'personPickerEmailAddressTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ],
      'personPickerIdentifierTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'requiredFieldsAddresses' => [
        'type' => 'enum',
        'class' => 'RequiredAddressFieldsEnum'
      ],
      'requiredFieldsNames' => [
        'type' => 'enum',
        'class' => 'RequiredNameFieldsEnum'
      ],
      'tcLoginModes' => [
        'type'  => 'enum',
        'class' => 'TAndCLoginModeEnum'
      ],
      'themes' => [
        'type' => 'select',
        'model' => 'Themes'
      ]
    ]);

    // Enable the Model Specific REST API for this Table
    $this->enableMsrApi();
    
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
      'co_id'                                => $coId,
      'default_address_type_id'              => null,
      'default_email_address_type_id'        => null,
      'default_identifier_type_id'           => null,
      'default_name_type_id'                 => null,
      'default_pronoun_type_id'              => null,
      'default_telephone_number_type_id'     => null,
      'default_url_type_id'                  => null,
      'authn_events_api_disable'             => false,
      'email_smtp_server_id'                 => null,
      'email_delivery_address_type_id'       => null,
      'permitted_fields_name'                => PermittedNameFieldsEnum::HGMFS,
      'permitted_fields_telephone_number'    => PermittedTelephoneNumberFieldsEnum::CANE,
      'person_picker_email_address_type_id'  => null,
      'person_picker_identifier_type_id'     => null,
      'person_picker_display_types'          => true,
      'required_fields_address'              => RequiredAddressFieldsEnum::Street,
      'required_fields_name'                 => RequiredNameFieldsEnum::Given,
      'search_global_limit'                  => DEF_GLOBAL_SEARCH_LIMIT,
      'search_limited_models'                => false,
      'tc_login_mode'                        => TAndCLoginModeEnum::NotEnforced,
      'tc_return_url_allow_list'             => null,
      'theme_id'                             => null,
      // Platform configuration defaults
      'platform_env_mfa'                     => null,
      'platform_env_mfa_value'               => null,
      'platform_env_mfa_enable_eg'           => false,
      'platform_upload_enable'               => false,
      // In general storing large files in the database is not performant, this number
      // probably shouldn't be increased.
      'platform_upload_max_size'             => 10000000
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
      // 'enable_empty_cou'           => false,
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
   * Get the MFA Indicator configuration.
   * 
   * @since  COmanage Registry v5.2.0
   * @return array|false  Arrof of configuration data if MFA is required, false otherwise
   */

  public function getMfaIndicator(): array|false {
    // The MFA Indicator only applies to the COmanage CO.

    $COmanageCO = $this->Cos->find('COmanageCO')->firstOrFail();

    $settings = $this->find()->where(['co_id' => $COmanageCO->id])->firstOrFail();

    if(!empty($settings->platform_env_mfa) && !ctype_space($settings->platform_env_mfa)) {
      return [
        'indicator'       => $settings->platform_env_mfa,
        'value'           => $settings->platform_env_mfa_value,
        'exempt_groups'   => $settings->platform_env_mfa_enable_eg ?? false,
        // We return the COmanage CO ID so AppController doesn't have to look it up again
        'comanage_co_id'  => $COmanageCO->id
      ];
    }

    return false;
  }

  /**
   * Get the outgoing SMTP Server for the specified CO.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int          $coId CO ID
   * @return SmtpServer         SmtpServer, or null if none configured
   */

  public function getSmtpServer(int $coId): ?\CoreServer\Model\Entity\SmtpServer {
    // Note CoreServer should always be enabled

    // The initial implementation has a per-CO SmtpServer setting, but at some point
    // we might allow the COmanage CO's SmtpServer to either override any CO Setting
    // or provide a default if there is no CO Setting.

    $settings = $this->find()
                     ->where(['CoSettings.co_id' => $coId])
                     ->contain(['EmailSmtpServers'])
                     ->firstOrFail();
    
    if(!empty($settings->email_smtp_server)) {
      // Because dynamic plugin relations are tricky to query via contain, we just
      // make a second query.

      $SmtpServers = TableRegistry::getTableLocator()->get('CoreServer.SmtpServers');

      return $SmtpServers->find()
                         ->where(['server_id' => $settings->email_smtp_server->id])
                         ->firstOrFail();
    }
    
    // If this isn't the COmanageCO, then use that configuration, if there is one
    $COmanageCO = $this->Cos->find('COmanageCO')->firstOrFail();

    if($COmanageCO->id != $coId) {
      // Note we're returning an object outside the calling CO's space, which isn't
      // expected this shouldn't be a problem ordinarily (core code should know what
      // it's doing, and plugins have full access to the database anyway), but there
      // could be unidentified edge cases where this could cause problems.

      return $this->getSmtpServer($COmanageCO->id);
    }
    
    return null;
  }
  
  /**
   * Get the Themes in use for the requested CO. This will return an array of up to two
   * Themes, one for the requested CO and one for the COmanage CO (if $coId is not the
   * COmanage CO).
   * 
   * @since  COmanage Registry v5.3.0
   * @param  int $coId    CO ID, or if null the Platform Theme will be requested
   * @return array        Array of Themes, with keys "platform" (if $coId is not the COmanage CO) and "co"
   */

  public function getThemes(?int $coId): array {
    $ret = [
      'platform'  => null,
      'co'        => null
    ];

    if($coId) {
      $cocfg = $this->find()->where(['CoSettings.co_id' => $coId])->contain(['Themes'])->firstOrFail();

      if(!empty($cocfg->theme)) {
        $ret['co'] = $cocfg->theme;
      }
    }

    $COmanageCO = $this->Cos->find('COmanageCO')->firstOrFail();

    if(!$coId || $COmanageCO->id != $coId) {
      // Retrieve the Platform Theme separately

      $cmpcfg = $this->find()->where(['CoSettings.co_id' => $COmanageCO->id])->contain(['Themes'])->firstOrFail();

      if(!empty($cmpcfg->theme)) {
        $ret['platform'] = $cmpcfg->theme;
      }
    }

    return $ret;
  }

  /**
   * Get the maximum permitted size for an uploaded file, or false if uploads
   * are disabled.
   * 
   * @since  COmanage Registry v5.3.0
   * @return int|bool     Maximum permitted size (in bytes) or false if uploads are disabled
   */

  public function getUploadMaxSize(): int|bool {
    // The Mostly Static Resource configurations are applied at the COmanage CO.

    $COmanageCO = $this->Cos->find('COmanageCO')->firstOrFail();

    $settings = $this->find()->where(['co_id' => $COmanageCO->id])->firstOrFail();

    if($settings->platform_upload_enable && $settings->platform_upload_max_size > 0) {
      return $settings->platform_upload_max_size;
    }

    return false;
  }
  
  /**
   * Reset (disable) the MFA requirement.
   * 
   * @since  COmanage Registry v5.2.0
   * @return bool     true on success
   */

  public function resetMfaIndicator(): bool {
    // The MFA Indicator only applies to the COmanage CO.

    $COmanageCO = $this->Cos->find('COmanageCO')->firstOrFail();

    $settings = $this->find()->where(['co_id' => $COmanageCO->id])->firstOrFail();

    $settings->platform_env_mfa = null;
    $settings->platform_env_mfa_value = null;
    $settings->platform_env_mfa_enable_eg = false;

    $this->saveOrFail($settings);

    return true;
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
   * Determine if File Uploads are enabled.
   * 
   * @since  COmanage Registry v5.3.0
   * @return bool     true if enabled, false otherwise
   */

  public function uploadsEnabled(): bool {
    // The Mostly Static Resource configurations are applied at the COmanage CO.

    $COmanageCO = $this->Cos->find('COmanageCO')->firstOrFail();

    $settings = $this->find()->where(['co_id' => $COmanageCO->id])->firstOrFail();
    
    return (bool)$settings->platform_upload_enable;
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
    
    $validator->add('authn_events_api_disable', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('authn_events_api_disable');
    
    $validator->add('email_delivery_address_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('email_delivery_address_type_id');

    $validator->add('email_smtp_server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('email_smtp_server_id');

    $validator->add('permitted_name_fields', [
      'content' => ['rule' => ['inList', PermittedNameFieldsEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('permitted_name_fields');
    
    $validator->add('permitted_telephone_number_fields', [
      'content' => ['rule' => ['inList', PermittedTelephoneNumberFieldsEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('permitted_fields_telephone_number');

    $validator->add('person_picker_display_types', [
      'content' => ['rule' => 'boolean']
    ]);
    $validator->allowEmptyString('person_picker_display_types');

    $validator->add('person_picker_email_address_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('person_picker_email_address_type_id');

    $validator->add('person_picker_identifier_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('person_picker_identifier_type_id');

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
    
    $validator->add('tc_login_mode', [
      'content' => ['rule' => ['inList', TAndCLoginModeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('tc_login_mode');

    $validator->add('tc_return_url_allowlist', [
      'filter'  => ['rule'     => ['validateInput'],
                    'provider' => 'table']
    ]);
    $validator->allowEmptyString('tc_return_url_allowlist');

    // "platform_" prefixed fields are intended to be available in the COmanage CO only.
    // We do this rather than create a separate table (like "meta") to leverage the existing
    // infrastructure and not have to fight Cake to maintain a table with a single row.
    
    $validator->add('theme_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('theme_id');

    $this->registerStringValidation($validator, $schema, 'platform_env_mfa', false);

    $this->registerStringValidation($validator, $schema, 'platform_env_mfa_value', false);

    $validator->add('platform_env_mfa_enable_eg', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('platform_env_mfa_enable_eg');

    $validator->add('platform_upload_enable', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('platform_upload_enable');

    $validator->add('platform_upload_max_size', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->add('platform_upload_max_size', [
      'value' => ['rule' => ['comparison', '>=', 0]]
    ]);
    $validator->allowEmptyString('platform_upload_max_size');

    return $validator;
  }
}