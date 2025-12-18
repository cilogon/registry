<?php
/**
 * COmanage Registry People Fields
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

$fields = [];

if($vv_action == 'add') {
  // Adding a new CO Person is a bit non-standard vs most views. We need a
  // Primary Name (which we'll collect here) along with possibly collecting
  // other CO Person attributes.
  
  foreach(['honorific', 'given', 'middle', 'family', 'suffix'] as $f) {
    if(in_array($f, $vv_permitted_name_fields)) {
      $fields['names.0.'.$f] = [
        'required' => in_array($f, $vv_required_name_fields),
        'fieldType' => 'string'
      ];
    }
  }
  
  $fields['names.0.type_id'] = [
    'default' => $vv_default_name_type,
    'fieldLabel' => __d('field', 'People.name.type_id'),
    'required' => true,
    'fieldType' => 'string'
  ];
  
  // AR-Name-1 Since this is the first name for this Person, it must be
  // designated primary
  $hidden = [
    // The initial name must be primary
    'names.0.primary_name' => true
  ];
} elseif($vv_action == 'edit') {
  $a = $vv_obj->extract(['person_role_id', 'person_id']);
}

$fields = array_merge($fields, [
  'status',
  'date_of_birth'
]);

// List the MVEAs that may be shown on the mveaCanvas
// When this array exists, the mveaCanvas.php element will render  
$mveas = [
  'names',
  'email_addresses',
  'identifiers',
  'ad_hoc_attributes',
  'addresses',
  'telephone_numbers',
  'urls',
  'pronouns'
];

// Name the MVEAs Entity Type
$mveasEntityType = "person";

// Output for $topLinks from this specific configuration is presented in the mveaCanvas.php "Actions" menu.   
$topLinks = [
  [
    'icon' => 'history',
    'order' => 'Default',
    'label' => __d('controller', 'HistoryRecords', [99]),
    'link' => [
      'controller' => 'history_records',
      'action' => 'index',
      '?' => [
        'person_id' => $vv_obj->id
      ]
    ],
    'class' => ''
  ],
  [
    'icon' => 'cloud_sync',
    'order' => 'Default',
    'label' => __d('operation', 'provisioning.status'),
    'link' => [
      'controller' => 'provisioning_targets',
      'action' => 'status',
      '?' => [
        'person_id' => $vv_obj->id
      ]
    ],
    'class' => ''
  ],
  [
    'icon' => 'lock',
    'order' => 'Default',
    // We use "Authenticators" as the label because it's less confusiong
    'label' => __d('controller', 'Authenticators', [99]),
    'link' => [
      'controller' => 'authenticator_statuses',
      'action' => 'index',
      '?' => [
        'person_id' => $vv_obj->id
      ]
    ],
    'class' => ''
  ],
  [
    'icon' => 'notifications',
    'iconClass' => 'material-symbols-outlined',
    'order' => 'Default',
    'label' => __d('controller', 'Notifications', [99]),
    'link' => [
      'controller' => 'notifications',
      'action' => 'index',
      '?' => [
        'subject_person_id' => $vv_obj->id
        // XXX person_id is auto-inserted but isn't needed
      ]
    ],
    'class' => ''
  ],
  [
    'icon' => 'badge',
    'iconClass' => 'material-symbols-outlined',
    'order' => 'Default',
    'label' => __d('operation', 'identifiers.assign'),
    'link' => [
      'controller' => 'identifier_assignments',
      'action' => 'assign',
      '?' => [
        'person_id' => $vv_obj->id
      ]
    ],
    'confirm' => [
      'dg_body_txt' => __d('operation', 'identifiers.assign.confirm', [$vv_obj->id]),
      'dg_confirm_btn' => __d('operation', 'assign')
    ],
    'class' => ''
  ]
];

// $addMenuLinks is also given slightly different treatment from the typical $topLinks found in most views: 
// it is a page-global menu used for adding MVEAs and is given special treatment in element/mveaCanvas.php.
$addMenuLinks = [
  [
    'controller' => 'names',
    'action' => 'add',
    'icon' => 'account_box',
    'iconClass' => 'material-symbols-outlined'
  ],
  [
    'controller' => 'email_addresses',
    'action' => 'add',
    'icon' => 'email',
    'iconClass' => 'material-symbols-outlined'
  ],
  [
    'controller' => 'identifiers',
    'action' => 'add',
    'icon' => 'fingerprint'
  ],
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
    'controller' => 'pronouns',
    'action' => 'add',
    'icon' => '3p',
    'iconClass' => 'material-symbols-outlined'
  ],
  [
    'controller' => 'telephone_numbers',
    'action' => 'add',
    'icon' => 'phone'
  ],
  [
    'controller' => 'urls',
    'action' => 'add',
    'icon' => 'link'
  ]
];

$subnav = [
  'name' => 'person',
  'active' => 'person',
  'subActive' => 'properties'
];