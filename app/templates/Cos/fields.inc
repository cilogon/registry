<?php
/**
 * COmanage Registry Cos Fields
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
  'name',
  'description',
  'status'
];

// Top Links
if($vv_action == 'edit') {
  $topLinks[] = [
    'icon'  => 'add_to_queue',
    'order' => 'Default',
    'label' => __d('operation', 'delete.queue'),
    // 'url'  => $vv_base_url . $vv_obj->name,
    'link'  => [
      'action'  => 'delete',
      $vv_obj->id,
      '?' => [
        'queue' => 'yes'
      ]
    ],
    'confirm' => [
      'dg_body_txt' => __d('operation', 'delete.queue.confirm', [$vv_obj->id]),
      'dg_confirm_btn' => __d('operation', 'confirm.yes'),
      'dg_cancel_btn' => __d('operation', 'confirm.no')
    ],
    'class' => ''
  ];
}