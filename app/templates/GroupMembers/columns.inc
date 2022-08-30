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

$indexColumns = [
 'name' => [
    'type' => 'relatedLink',
    'model' => 'person',
    'submodel' => 'primary_name',
    'field' => 'full_name',
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

$bulkActions = [
  // TODO: develop bulk actions. For now, use a placeholder. 
  'delete' => true
];