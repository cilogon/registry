<?php
/**
 * COmanage Registry API Users Fields
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

if($vv_cur_co->isCOmanageCO()) {
  $alerts = [
    ['message' => __d('information', 'api.cmp')]
  ];
}

// We link to the "Generate" button on edit only
$generateLink = [];

if(!empty($vv_obj->id)) {
  $generateLink = [
    'url' => [
      'controller' => 'api_users',
      'action'     => 'generate',
      $vv_obj->id
    ],
    'label' => __d('operation', 'api.key.generate'),
    'class' => 'provisionbutton nospin btn btn-primary btn-sm',
    'confirm' => __d('operation', 'api.key.generate.confirm')
  ];
}

$fields = [
  'username' => [
    // AR-ApiUser-3 For namespacing purposes, API Users are named with a prefix consisting of the string "co_#.".
    'fieldPrefix' => 'co_' . $vv_cur_co->id . '.'
  ],
  'api_key' => [
    'status' => !empty($vv_obj->api_key) ? __d('enumeration', 'SetBooleanEnum.1') : __d('enumeration', 'SetBooleanEnum.0'),
    'link' => $generateLink,
    'labelIsTextOnly' => true
  ],
  'status',
  'valid_from', // timestamp
  'valid_through', // timestamp
  'remote_ip', // string
  'privileged', // boolean
];

$fields = array_merge($fields, include(ROOT . DS . 'templates' . DS . 'Standard/metadata.inc'));