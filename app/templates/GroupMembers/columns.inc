<?php
/**
 * COmanage Registry Group Members Index Columns
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

use App\Lib\Enum\BulkActionEnum;

$indexColumns = [
 'name' => [
    'type' => 'link',
    'model' => 'person',
    'submodel' => 'primary_name',
    'field' => 'full_name',
    'class' => 'cm-modal-link nospin', // launch this in a modal 
    'dataAttrs' => [
      ['data-cm-modal-title', __d('controller', 'GroupMembers', 1)]
    ]
// XXX not clear how to sort on submodel, but maybe just leave it for UX revisions?
//    'sortable' => 'PrimaryName.family'
  ],
  'source' => [
    'type' => 'closure',
    'function' => function ($entity) {
      if(!empty($entity->group_nesting_id)) {
        if(!empty($entity->group_nesting->group->name)) {
          return $this->Html->link($entity->group_nesting->group->name,
                                   ['controller' => 'groups', 'action' => 'edit', $entity->group_nesting->group->id]);
        } else {
          return __d('controller', 'GroupNestings', [1]);
        }
      } else {
        return __d('field', 'GroupMembers.source.direct');
      }
    }
  ],
  'valid_from' => [
    'type' => 'datetime'
  ],
  'valid_through' => [
    'type' => 'datetime'
  ]
];

// Suppress the default add link
$suppressAddLink = true;
$topLinks = [
/* 
  // This "Add" link launches the add form in a modal with a autocomplete people picker as a field. 
  // Leave it here as a reference until we have a similar field such as "sponsor" created. 
  [
    'icon' => 'Add',
    'order' => 'Default',
    'label' => __d('operation', 'add.a', [__d('controller', 'GroupMembers', 1)]),
    'link' => [
      'controller' => 'GroupMembers',
      'action' => 'add'
    ],
    'class' => 'cm-modal-link nospin', 
    'dataAttrs' => [
      ['data-cm-modal-title', __d('controller', 'GroupMembers', 1)]
    ]
  ]
  */
];


// When the $bulkActions variable exists in a columns.inc config, the "Bulk edit" switch will appear in the index.
// The array should contain a list of actions to be made available.
$bulkActions = [BulkActionEnum::Delete];


$subnav = [
  'name' => 'group',
  'active' => 'members'
];

// Page options
$peoplePicker = [
  'label' => __d('operation','add.member'),
  'viewConfigParameters' => [
    'for' => 'GroupMembers',
    'action' => $vv_action,
    'groupId' => $this->getRequest()?->getQuery('group_id')
  ],
  'actionUrl' => [
    'plugin'     => null,
    'controller' => 'GroupMembers',
    'action' => 'add',
    '?' => [
      'group_id' => $this->getRequest()?->getQuery('group_id')
    ]
  ]
];
