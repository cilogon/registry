<?php
/**
 * COmanage Registry voPerson Schemas Fields
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
 * @package       registry-plugins
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

$fields = [
  'vo_person_affiliation',
  'vo_person_application_password',
  'vo_person_application_uid' => [
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'vo_person_application_uid_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'vo_person_application_uid' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.VoPersonSchemas.vo_person_application_uid')]),
      ],
    ],
  ],
  'vo_person_author_name' => [
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'vo_person_author_name_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'vo_person_author_name' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.VoPersonSchemas.vo_person_author_name')]),
      ],
    ],
  ],
  'vo_person_certificate_dn',
  'vo_person_certificate_issuer_dn',
  'vo_person_external_id' => [
    'fieldLabel' => __d('ldap_connector', 'field.VoPersonSchemas.vo_person_external_id'),
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'vo_person_external_id_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'vo_person_external_id_export' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.VoPersonSchemas.vo_person_external_id')]),
      ],
    ],
  ],
  'vo_person_id' => [
    'fieldLabel' => __d('ldap_connector', 'field.VoPersonSchemas.vo_person_id'),
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'vo_person_id_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'vo_person_id_export' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.VoPersonSchemas.vo_person_id')]),
      ],
    ],
  ],
  'vo_person_policy_agreement',
  'vo_person_sorid' => [
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'vo_person_sorid_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'vo_person_sorid' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.VoPersonSchemas.vo_person_sorid')]),
      ],
    ],
  ],
  'vo_person_status',
  'vo_person_token',
];

$subnav = [
  'tabs' => ['LdapConnector.LdapSchemas', 'LdapConnector.VoPersonSchemas'],
  'action' => [
    'LdapConnector.LdapSchemas' => ['edit'],
    'LdapConnector.VoPersonSchemas' => ['edit'],
  ],
];
