<?php
/**
 * COmanage Registry Groups Edit Navigation
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
  'identifiers'
];

// Name the MVEAs Entity Type
$mveasEntityType = "group";

// XXX: if CFM-218 (Make fields.inc configuration only) is accepted, move the contents of this file into fields.inc
$topLinks = [
  [
    'icon' => 'history',
    'order' => 'Default',
    'label' => __d('controller', 'HistoryRecords', [99]),
    'link' => [
      'controller' => 'history_records',
      'action' => 'index',
      '?' => [
        'group_id' => $vv_obj->id
      ]
    ],
    'class' => ''
  ]
];

if(!$vv_obj->isOwners()) {
  // Most actions aren't available for Owners Groups
  $topLinks += [
    [
      'icon' => 'cloud_sync',
      'order' => 'Default',
      'label' => __d('operation', 'provisioning.status'),
      'link' => [
        'controller' => 'provisioning_targets',
        'action' => 'status',
        '?' => [
          'group_id' => $vv_obj->id
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
          'group_id' => $vv_obj->id
        ]
      ],
      'class' => ''
    ],
    [
      'icon' => 'sync',
      'order' => 'Default',
      'label' => __d('operation', 'reconcile'),
      'link' => [
        'action' => 'reconcile',
        $vv_obj->id
      ],
      'class' => ''
    ]
  ];
}

// $addMenuLinks is also given slightly different treatment from the typical $topLinks found in most views: 
// it is a page-global menu used for adding MVEAs and is given special treatment in element/mveaCanvas.php.

if(!$vv_obj->isOwners()) {
  $addMenuLinks = [
    [
      'controller' => 'identifiers',
      'action' => 'add',
      'icon' => 'fingerprint'
    ]
  ];
}

$subnav = [
  'name' => 'group',
  'active' => 'properties'
];