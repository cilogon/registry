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
?>
<?php
// This view does not support add or read-only
if($vv_action == 'edit') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'required_fields_address',
      'fieldOptions' => ['empty' => false]
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'default_address_type_id'
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'default_email_address_type_id'
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'default_identifier_type_id'
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'default_name_type_id'
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'permitted_fields_name',
      'fieldOptions' => ['empty' => false]
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'required_fields_name',
      'fieldOptions' => ['empty' => false]
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'default_pronoun_type_id'
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'default_telephone_number_type_id'
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'permitted_fields_telephone_number',
      'fieldOptions' => ['empty' => false]
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'default_url_type_id'
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'search_global_limit'
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'search_global_limited_models'
    ]]);

    print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'person_picker_display_fields',
      'labelIsTextOnly' => true,
      'groupedControls' =>  [
        // each key is the fieldName of the control we are going to create
        'person_picker_email_address_type_id' => [
          'fieldOptions' => [
            'label' => __d('field', 'mail'),
            'empty' => '(' . __d('operation', 'all') . ')',
//            'all' => '(' . __d('operation', 'all') . ')'
          ],
        ],
        'person_picker_identifier_type_id' => [
          'fieldOptions' => [
            'label' => __d('field', 'identifier'),
            'empty' => '(' . __d('operation', 'all') . ')',
//            'all' => '(' . __d('operation', 'all') . ')',
          ],
        ],
        'person_picker_display_types' => [
          'singleRowItem' => true,
          'fieldLabel' => __d('field', 'CoSettings.person_picker_display_types')
        ]
      ],
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'email_smtp_server_id'
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'email_delivery_address_type_id'
    ]]);
}
