<?php
/**
 * COmanage Registry inetOrgPerson Schemas Fields
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
  'given_name' => [
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'given_name_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'given_name' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.InetOrgPersonSchemas.given_name')]),
      ],
    ],
  ],
  'display_name_type_id' => [
    'empty' => __d('ldap_connector', 'disabled'),
  ],
  'o',
  'labeled_uri' => [
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'labeled_uri_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'labeled_uri' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.InetOrgPersonSchemas.labeled_uri')]),
      ],
    ],
  ],
  'mail' => [
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'mail_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'mail' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.InetOrgPersonSchemas.mail')]),
      ],
    ],
  ],
  'mobile' => [
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'mobile_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'mobile' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.InetOrgPersonSchemas.mobile')]),
      ],
    ],
  ],
  'employee_number_type_id' => [
    'empty' => __d('ldap_connector', 'disabled'),
  ],
  'employee_type',
  'address' => [
    'fieldLabel' => __d('field', 'Address'),
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'address_type_id' => [
        'label' => __d('ldap_connector', 'field.address_type_id'),
        'empty' => '(' . __d('operation', 'all') . ')',
        'singleRowItem' => true,
      ],
      'room_number' => [
        'type' => 'checkbox',
        'label' => __d('ldap_connector', 'field.room_number'),
      ],
    ],
  ],
  'uid' => [
    'labelIsTextOnly' => true,
    'groupedControls' => [
      'uid_type_id' => [
        'empty' => '(' . __d('operation', 'all') . ')',
      ],
      'uid' => [
        'type' => 'checkbox',
        'singleRowItem' => true,
        'label' => __d('ldap_connector', 'checkbox.disable', [__d('ldap_connector', 'field.InetOrgPersonSchemas.uid')]),
      ],
    ],
  ],
];

$subnav = [
  'tabs' => ['LdapConnector.LdapSchemas', 'LdapConnector.InetOrgPersonSchemas'],
  'action' => [
    'LdapConnector.LdapSchemas' => ['edit'],
    'LdapConnector.InetOrgPersonSchemas' => ['edit'],
  ],
];
