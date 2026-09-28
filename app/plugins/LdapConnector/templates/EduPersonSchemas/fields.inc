<?php
/**
 * COmanage Registry eduPerson Schemas Fields
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
  'edu_person_affiliation',
  'edu_person_scoped_affiliation',
  'edu_person_entitlement',
  'edu_person_nickname' => [
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'edu_person_nickname_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'edu_person_nickname' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.EduPersonSchemas.edu_person_nickname')]),
      ],
    ],
  ],
  'edu_person_orcid',
  'edu_person_principal_name_type_id' => [
    'empty' => __d('ldap_connector', 'disabled'),
  ],
  'edu_person_principal_name_prior' => [
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'edu_person_principal_name_prior_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'edu_person_principal_name_prior' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.EduPersonSchemas.edu_person_principal_name_prior')]),
      ],
    ],
  ],
  'edu_person_unique_id' => [
    'fieldLabel' => __d('ldap_connector', 'field.EduPersonSchemas.edu_person_unique_id'),
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'edu_person_unique_id_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'edu_person_unique_id_export' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.EduPersonSchemas.edu_person_unique_id')]),
      ],
    ],
  ],
];

$subnav = [
  'tabs' => ['LdapConnector.LdapSchemas', 'LdapConnector.EduPersonSchemas'],
  'action' => [
    'LdapConnector.LdapSchemas' => ['edit'],
    'LdapConnector.EduPersonSchemas' => ['edit'],
  ],
];
