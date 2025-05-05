<?php
/**
 * COmanage Registry Env Sources Fields
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
?>

<?php
// This view does not currently support read-only
if($vv_action == 'edit') {
  foreach([
    // 'duplicate_mode',
    'sp_mode',
    'sync_on_login',
    'address_type_id',
    'default_affiliation_type_id',
    'email_address_type_id',
    'name_type_id',
    'telephone_number_type_id',
    'redirect_on_duplicate',
    'lookaside_file'
  ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field
      ]
    ]);
  }

  // Print a title
  $title = __d('env_source', 'information.header.map');
  print $this->element('form/h3Inject', compact('title'));

  $defaultNames = [
    'env_identifier_sourcekey' => 'ENV_SOURCE_KEY',
    'env_address_street' => 'ENV_STREET',
    'env_address_locality' => 'ENV_LOCALITY',
    'env_address_state' => 'ENV_STATE',
    'env_address_postalcode' => 'ENV_POSTALCODE',
    'env_address_country' => 'ENV_COUNTRY',
    'env_affiliation' => 'ENV_AFFILIATION',
    'env_department' => 'ENV_DEPARTMENT',
    'env_identifier_eppn' => 'ENV_EPPN',
    'env_identifier_eptid' => 'ENV_EPTID',
    'env_identifier_epuid' => 'ENV_EPUID',
    'env_identifier_network' => 'ENV_NETWORK',
    'env_identifier_oidcsub' => 'ENV_OIDCSUB',
    'env_identifier_samlpairwiseid' => 'ENV_SAMLPAIRWISE',
    'env_identifier_samlsubjectid' => 'ENV_SAMLSUBJECT',
    'env_mail' => 'ENV_MAIL',
    'env_name_honorific' => 'ENV_HONORIFIC',
    'env_name_given' => 'ENV_GIVEN',
    'env_name_middle' => 'ENV_MIDDLE',
    'env_name_family' => 'ENV_FAMILY',
    'env_name_suffix' => 'ENV_SUFFIX',
    'env_organization' => 'ENV_ORGANIZATION',
    'env_telephone_number' => 'ENV_TELEPHONE',
    'env_title' => 'ENV_TITLE'
  ];

  foreach([
    'env_identifier_sourcekey',
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
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
        'fieldOptions' => [
          'default' => $defaultNames[$field]
        ]
      ]
    ]);
  }
}
