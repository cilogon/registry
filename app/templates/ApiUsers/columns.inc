<?php
/**
 * COmanage Registry API Users Index Columns
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

// Output advisory banner when co=1
if($vv_cur_co->isCOmanageCO()) {
  $alerts = [
    ['message' => __d('information','api.cmp')]
  ];
}

$indexColumns = [
  'username' => [
    'type' => 'link',
    'sortable' => true
  ],
  'status' => [
    'type' => 'enum',
    'class' => 'SuspendableStatusEnum',
    'sortable' => true
  ],
  'api_key' => [
    'type' => 'boolean',
    'class' => 'SetBooleanEnum'
  ]
];

if($vv_cur_co->id > 1) {
  // Also show privileged state
  $indexColumns['privileged'] = [
    'type' => 'boolean',
    'class' => 'YesBooleanEnum'
  ];
}

$indexColumns['valid_through'] = [
  'type' => 'datetime'
];

/*
// When the $bulkActions variable exists in a columns.inc config, the "Bulk edit" switch will appear in the index. 
$bulkActions = [
  // TODO: develop bulk actions. For now, use a placeholder.
  'delete' => true
];
*/