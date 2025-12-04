<?php
/**
 * COmanage Registry Identifiers Fields
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

$fields = [
  'identifier',
  'type_id' => [
    'default' => $vv_default_type
  ]
];


if($vv_primary_link_attr == 'person_id') {
  // AR-Identifier-1 Only Persons can have a login flag
  $fields[] = 'login';
} else {
  // Will be used by add-edit-view.php to append the hidden fields in the form
  $hidden['login'] = false;
}

$fields = array_merge($fields, [
  'status',
  'frozen'
]);

if($vv_action == 'edit' || $vv_action == 'view') {
  $fields['source'] = [
    'entity' => $vv_obj
  ];

  $fields[] = 'provisioning_target_id';
}

// Top Links
if(!empty($vv_obj->identifier) && $vv_obj->isLogin()) {
  $topLinks = [
    [
      'icon'      => 'lock',
      'order'     => 'Default',
      'label'     => __d('controller', 'AuthenticationEvents', [99]),
      'link' => [
        'controller' => 'authentication_events',
        'action'     => 'index',
        '?' => [
          'authenticated_identifier' =>
            \App\Lib\Util\StringUtilities::urlbase64encode($vv_obj->identifier)
        ]
      ],
      'class' => ''
    ]
  ];
}