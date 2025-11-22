<?php
/**
 * COmanage Registry Person Roles Fields
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
  'cou_id',
  'affiliation_type_id' => [
    'fieldLabel' => __d('field', 'affiliation')
  ],
  'status',
  'ordr',
  'title',
  'organization',
  'department'
];

// Provide people-picker for sponsor and manager
foreach(['sponsor', 'manager'] as $f) {
  $fp = $f . '_person';

  $vv_autocomplete_arguments = [
    'fieldLabel' => __d('field', $f),
    'autocomplete' => [
      'configuration' => [
        'action' => 'GET',
        'for' => 'co'
      ]
    ]
  ];
  
  if(!empty($vv_obj->$fp->names[0])) {
    $vv_autocomplete_arguments['default'] =  $vv_obj->$fp->id;
  }

  $fields[$f.'_person_id'] = $vv_autocomplete_arguments;
}

$fields = array_merge($fields, [
  'valid_from',
  'valid_through',
  'frozen'
]);

if($vv_action == 'edit' || $vv_action == 'view') {
  $fields['source'] = [
    'entity' => $vv_obj
  ];
}

// List the MVEAs that may be shown on the mveaCanvas
// When this array exists, the mveaCanvas.php element will render  
$mveas = [
  'ad_hoc_attributes',
  'addresses',
  'telephone_numbers'
];

// Name the MVEAs Entity Type
$mveasEntityType = "person_role";

// $addMenuLinks is also given slightly different treatment from the typical $topLinks found in most views: 
// it is a page-global menu used for adding MVEAs and is given special treatment in element/mveaCanvas.php.
$addMenuLinks = [
  [
    'controller' => 'ad_hoc_attributes',
    'action' => 'add',
    'icon' => 'user_attributes',
    'iconClass' => 'material-symbols-outlined'
  ],
  [
    'controller' => 'addresses',
    'action' => 'add',
    'icon' => 'contact_mail',
    'iconClass' => 'material-symbols-outlined'
  ],
  [
    'controller' => 'telephone_numbers',
    'action' => 'add',
    'icon' => 'phone'
  ]
];

$subnav = [
  'name' => 'person',
  'active' => 'person_roles',
  'subActive' => 'properties'
];