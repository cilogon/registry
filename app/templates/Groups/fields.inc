<?php
/**
 * COmanage Registry Groups Fields
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

// Most fields can't be edited for Owners Groups
$options = [];

if($vv_obj->isOwners()) {
  $options['readonly'] = true;
}

foreach(['name',
          'description',
          'status',
          'open',
          'nesting_mode_all'] as $field) {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => $field,
      'fieldOptions' => $options
    ]
  ]);
}

if(!empty($parents)) {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'parent_id',
      'fieldLabel' => __d('field', 'parent_id'),
    ]
  ]);
}

if($vv_action != 'add') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'group_type',
      'fieldOptions' => [
        'readonly' => true
      ]
    ]
  ]);

  if($vv_obj->isOwners()) {
    // Link to the main group
    if(!empty($vv_obj->owners_for_group)) {
      print $this->element('form/listItem', [
        'arguments' => [
          'fieldName' => 'owners_group_id',
          'fieldLabel' => __d('field', 'Groups.owners.for'),
          'status' => $vv_obj->owners_for_group->name,
          'link' => ['url' => ['controller' => 'groups', 'action' => 'edit', $vv_obj->owners_for_group->id]],
          'labelIsTextOnly' => true
        ]
      ]);
    }
  } else {
    // Link to the owners group
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'owners_group_id',
        'status' => $vv_obj->owners_group->name ?? '',
        'link' => ['url' => ['controller' => 'groups', 'action' => 'edit', $vv_obj->owners_group_id]],
        'labelIsTextOnly' => true
      ]
    ]);
  }
}

print $this->element('clonable');