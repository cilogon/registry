<?php
/**
 * COmanage Registry CO Settings Fields
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

$fields = [
  'required_fields_address' => [
    'empty' => false
  ],
  'default_address_type_id',
  'default_email_address_type_id',
  'default_identifier_type_id',
  'default_name_type_id',
  'permitted_fields_name' => [
    'empty' => false
  ],
  'required_fields_name' => [
    'empty' => false
  ],
  'default_pronoun_type_id',
  'default_telephone_number_type_id',
  'permitted_fields_telephone_number' => [
    'empty' => false
  ],
  'default_url_type_id',
  'search_global_limit',
  'search_global_limited_models',
  'person_picker_display_fields' => [
    'labelIsTextOnly' => true,
    'groupedControls' =>  [
      'person_picker_email_address_type_id' => [
        'label' => __d('field', 'mail'),
        'empty' => '(' . __d('operation', 'all') . ')',
//      'all' => '(' . __d('operation', 'all') . ')'
      ],
      'person_picker_identifier_type_id' => [
        'label' => __d('field', 'identifier'),
        'empty' => '(' . __d('operation', 'all') . ')',
//      'all' => '(' . __d('operation', 'all') . ')',
      ],
      'person_picker_display_types' => [
        'singleRowItem' => true,
        'label' => __d('field', 'CoSettings.person_picker_display_types')
      ]
    ],
  ],
  'email_smtp_server_id',
  'email_delivery_address_type_id',
  'authn_events_api_disable'
];

if($vv_obj->co->isCOmanageCO()) {
  // Fields prefixed platform_ are only available within the COmanage CO
  $fields = array_merge($fields, [
    'platform_env_mfa',
    'platform_env_mfa_value',
    'platform_env_mfa_enable_eg'
  ]);
}
