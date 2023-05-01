<?php
/**
 * COmanage Registry People Index Columns
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

$indexColumns = [
  'name' => [
    'type' => 'link',
    'model' => 'primary_name',
    'field' => 'full_name',
    'action' => 'edit',
// XXX see comments in the controller about sorting on given vs family
    'sortable' => 'PrimaryName.family'
  ],
  'status' => [
    'type' => 'enum',
    'class' => 'StatusEnum',
    'sortable' => true
  ]
];

// $topLinks appear as an upper right menu
$topLinks = [];

// $rowActions appear as row-level menu items in the index view gear icon
$rowActions = [
  [
    'controller' => 'person_roles',
    'action' => 'index',
    'icon' => 'emoji_people'
  ],
  [
    'controller' => 'external_identities',
    'action' => 'index',
    'icon' => 'system_update_alt',
    'class' => 'bottom-border'
  ],
  [
    'controller' => 'history_records',
    'action' => 'index',
    'icon' => 'history'
  ]
];

// TODO: develop bulk actions. For now, use a placeholder. 
$bulkActions = [
  'expunge' => true
];