<?php
/**
 * COmanage Registry Ldap Schema Index Columns
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

$indexColumns = [
  'description' => [
    'type' => 'link',
    'action' => 'configure',
    'sortable' => true,
    'class' => 'cm-modal-link nospin',
    'dataAttrs' => [
      ['data-cm-modal-title', __d('ldap_connector', 'controller.LdapSchemas', [99])]
    ]
  ],
  'plugin' => [
    'type' => 'echo',
    'sortable' => true
  ],
  'status' => [
    'type' => 'enum',
    'class' => 'SuspendableStatusEnum',
    'sortable' => true
  ]
];

$subnav = [
  'tabs' => ['ProvisioningTargets', 'LdapConnector.LdapProvisioners', 'LdapConnector.LdapSchemas'],
  'action' => [
    'ProvisioningTargets' => ['edit'],
    'LdapConnector.LdapProvisioners' => ['edit'],
    'LdapConnector.LdapSchemas' => ['index'],
  ],
  'counter' => ['LdapConnector.LdapSchemas'],
];

// $rowActions appear as row-level menu items in the index view gear icon
$rowActions = [
  [
    'controller' => 'ldap_schemas',
    'plugin'     => 'LdapConnector',
    'action'  => 'configure',
    'label'   => __d('operation', 'configure.plugin'),
    'icon'    => 'electrical_services',
    'class' => 'cm-modal-link nospin',
    'dataAttrs' => [
      ['data-cm-modal-title', __d('ldap_connector', 'controller.LdapSchemas', [99])]
    ]
  ],
];
