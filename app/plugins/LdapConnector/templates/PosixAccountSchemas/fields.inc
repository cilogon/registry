<?php
/**
 * COmanage Registry POSIX Account Schemas Fields
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

$fields = [
  'cn' => [
    'checked' => true,
    'readonly' => true,
  ],
  'uid' => [
    'checked' => true,
    'readonly' => true,
  ],
  'uid_number' => [
    'checked' => true,
    'readonly' => true,
  ],
  'gid_number' => [
    'checked' => true,
    'readonly' => true,
  ],
  'home_directory' => [
    'checked' => true,
    'readonly' => true,
  ],
  'login_shell',
  'gecos',
];

$hidden = [
  'cn' => 1,
  'uid' => 1,
  'uid_number' => 1,
  'gid_number' => 1,
  'home_directory' => 1,
];

$subnav = [
  'tabs' => ['LdapConnector.LdapSchemas', 'LdapConnector.PosixAccountSchemas'],
  'action' => [
    'LdapConnector.LdapSchemas' => ['edit'],
    'LdapConnector.PosixAccountSchemas' => ['edit'],
  ],
];
