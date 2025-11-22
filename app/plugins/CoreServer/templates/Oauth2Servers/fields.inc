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

// Render the Redirect URI
$fields = [
  'redirect_uri' => [
    'readonly' => true,
    'default' => $vv_redirect_uri
  ],
  'url',
  'access_grant_type' => [
    'options' => $types,
    'type' => 'select',
    'empty' => false
  ],
  'clientid',
  'client_secret',
  'scope' => [
    'placeholder' => '/authenticate',
  ]
];

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

$fields['access_token'] = [
  'status' => !empty($vv_obj->access_token) ? __d('enumeration', 'SetBooleanEnum.1') : __d('enumeration', 'SetBooleanEnum.0'),
  'link' => $generateLink,
  'labelIsTextOnly' => true
];