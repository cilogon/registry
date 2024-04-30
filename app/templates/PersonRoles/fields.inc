<?php
/**
 * COmanage Registry Person Roles Fields
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

if($vv_action == 'add' || $vv_action == 'edit' || $vv_action == 'view') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'cou_id',
    ]
  ]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' =>'affiliation_type_id',
      'fieldLabel' => __d('field', 'affiliation')
    ]
  ]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' =>'status'
    ]
  ]);

  foreach(['ordr',
           'title',
           'organization',
           'department'] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
      ]
    ]);
  }
  
  // For now, we render sponsor and manager as read only.
  // XXX Need People Picker (CFM-150)
  foreach(['sponsor', 'manager'] as $f) {
    $fp = $f."_person";
    
    $fname = "";
    $flink = [];
    
    if(!empty($vv_obj->$fp->names[0])) {
      $fname = $vv_obj->$fp->names[0]->full_name;
      $flink = ['url' => ['controller' => 'people', 'action' => 'edit', $vv_obj->$fp->id]];
    }

    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $f.'_person_id',
        'status' => $fname,
        'link' => $flink,
        'fieldLabel' => __d('field', $f)
      ]
    ]);
  }

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'valid_from', // timestamp
    ]
  ]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'valid_through', // timestamp
    ]
  ]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'frozen'
    ]]);
}

if($vv_action == 'edit' || $vv_action == 'view') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'source',
      'entity' => $vv_obj
    ]]);
}
