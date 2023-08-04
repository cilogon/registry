<?php
/**
 * COmanage Registry Person Edit Navigation
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
    'icon' => 'badge',
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
    'iconClass' => 'material-icons-outlined'
  ],
  [
    'controller' => 'email_addresses',
    'action' => 'add',
    'icon' => 'email',
    'iconClass' => 'material-icons-outlined'
  ],
  [
    'controller' => 'identifiers',
    'action' => 'add',
    'icon' => 'fingerprint'
  ],
  [
    'controller' => 'ad_hoc_attributes',
    'action' => 'add',
    'icon' => 'check_box',
    'iconClass' => 'material-icons-outlined'
  ],
  [
    'controller' => 'addresses',
    'action' => 'add',
    'icon' => 'contact_mail',
    'iconClass' => 'material-icons-outlined'
  ],
  [
    'controller' => 'history_records',
    'action' => 'add',
    'icon' => 'history'
  ],
  [
    'controller' => 'pronouns',
    'action' => 'add',
    'icon' => 'transgender'
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