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
  print $this->Field->control('required_fields_address', ['suppressBlank' => true]);
  
  print $this->Field->control('default_address_type_id');
  
  print $this->Field->control('default_email_address_type_id');
  
  print $this->Field->control('default_identifier_type_id');
  
  print $this->Field->control('default_name_type_id');

  print $this->Field->control('permitted_fields_name', ['suppressBlank' => true]);
  
  print $this->Field->control('required_fields_name', ['suppressBlank' => true]);

  print $this->Field->control('default_pronoun_type_id');

  print $this->Field->control('default_telephone_number_type_id');

  print $this->Field->control('permitted_fields_telephone_number', ['suppressBlank' => true]);
  
  print $this->Field->control('default_url_type_id');
  
  print $this->Field->control('search_global_limit');
  
  print $this->Field->control('search_global_limited_models');

  print $this->Field->groupedControls(
    [
      'person_picker_email_address_type_id' => [
        'options' => [
          'suppressBlank' => true,
          'label' => __d('field', 'mail'),
        ],
      ],
      'person_picker_identifier_type_id' => [
        'options' => [
          'suppressBlank' => true,
          'label' => __d('field', 'identifier'),
        ],
      ],
      'person_picker_display_types' => [
        'singleRowItem' => true
      ]
    ],
    'person_picker_display_fields'
  );

  print $this->Field->control('email_smtp_server_id');

  print $this->Field->control('email_delivery_address_type_id');
}
