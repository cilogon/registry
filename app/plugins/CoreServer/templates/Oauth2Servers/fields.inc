<?php
/**
 * COmanage Registry Oauth2 Servers Fields
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

// This view does currently not support read-only, and add is not used
if($vv_action !== 'edit') {
  return;
}

// Render the Redirect URI
print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'redirect_uri',
    'fieldOptions' => [
      'readOnly' => true,
      'default' => $vv_redirect_uri
    ]
  ]]);

print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'url'
  ]]);

print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'access_grant_type',
    'fieldSelectOptions' => $types,
    'fieldType' => 'select',
    'fieldOptions' => [
      'empty' => false,
    ],
  ]]);

foreach ([
           "clientid",
           "client_secret",
         ] as $field) {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => $field
    ]]);
}

print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'scope',
    'fieldOptions' => [
      'placeholder' => '/authenticate',
    ],
  ]]);

$generateLink = [];
if(!empty($vv_obj->id)) {
  $generateLink = [
    'url' => [
      'plugin'     => 'CoreServer',
      'controller' => 'Oauth2Servers',
      'action'     => 'token',
      $vv_obj->id
    ],
    'label' => __d('core_server', 'info.Oauth2Servers.token.obtain'),
    'class' => 'provisionbutton nospin btn btn-primary btn-sm',
  ];
}

print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'access_token',
    'status' => !empty($vv_obj->access_token) ? __d('enumeration', 'SetBooleanEnum.1') : __d('enumeration', 'SetBooleanEnum.0'),
    'link' => $generateLink,
    'labelIsTextOnly' => true
  ]
]);