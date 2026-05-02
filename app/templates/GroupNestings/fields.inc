<?php
/**
 * COmanage Registry Group Nestings Fields
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
  $fields = [
    'target_group_id'
  ];
} elseif($vv_action == 'edit') {
  $link = [
    'url' => [
      'controller' => 'groups',
      'action'     => 'edit',
      $vv_obj->target_group_id
    ],
    'target' => '_top'
  ];
  // The target group can't be changed after adding
  $fields = [
    'target_group_id' => [
      'status' => $vv_obj->target_group->name,
      'link' => $link
    ]
  ];
}

$link = [
  'url' => [
    'controller' => 'groups',
    'action'     => 'edit',
    $vv_bc_parent_obj->id,
  ],
  'target' => '_top'
];
// The target group can't be changed after adding
$fields['group_id'] = [
  'status' => $vv_bc_parent_obj->name,
  'link' => $link
];
$fields[] = 'negate';

if($vv_action == 'add') {
  // Allow the admin to request background creation of the Nesting
  $fields['queue'] = [
    'fieldLabel' => __d('operation', 'add.queue', [__d('controller', 'GroupNestings', [1])]),
    'type' => 'checkbox',
    'checked' => ''
  ];
}

if($vv_action == 'edit') {
  $topLinks = [
    [
      'icon' => 'add_to_queue',
      'order' => 'Default',
      'label' => __d('operation', 'delete.queue'),
      'link' => [
        // 'controller' => 'group_nestings',
        'action' => 'queue',
        $vv_obj->id
        // '?' => [
        //   'id' => $vv_obj->id
        // ]
      ],
      'confirm' => [
        'dg_body_txt' => __d('operation', 'delete.queue.confirm', [$vv_obj->id]),
        'dg_confirm_btn' => __d('operation', 'queue')
      ],
      'class' => ''
    ]
  ];
}