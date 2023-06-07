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

// XXX: if CFM-218 (Make fields.inc configuration only) is accepted, move the contents of this file into fields.inc
$topLinks = [
  [
    'icon' => 'sync',
    'order' => 'Default',
    'label' => __d('operation', 'reconcile'),
    'link' => [
      'action' => 'reconcile',
      $vv_obj->id
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
        'group_id' => $vv_obj->id
      ]
    ],
    'class' => ''
  ],
  [
    'icon' => 'history',
    'order' => 'Default',
    'label' => __d('controller', 'HistoryRecords', [99]),
    'link' => [
      'controller' => 'history_records',
      'action' => 'index',
      '?' => ['group_id' => $vv_obj->id]
    ],
    'class' => ''
  ]
];

$subnav = [
  'name' => 'group',
  'active' => 'properties'
];