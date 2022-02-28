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

// This view does not currently support read-only
if($vv_action == 'add' || $vv_action == 'edit') {
  print $this->Field->control('cou_id');
  
  print $this->Field->control('affiliation_type_id', [], __d('field', 'affiliation'));
  
  print $this->Field->control('status', ['empty' => false]);
  
  print $this->Field->control('ordr');
  
  print $this->Field->control('title');
  
  print $this->Field->control('organization');
  
  print $this->Field->control('department');
  
  // For now, we render sponsor and manager as read only.
  // XXX Need People Picker (CFM-150)
  foreach(['sponsor', 'manager'] as $f) {
    $fp = $f."_person";
    
    $fname = "";
    $flink = [];
    
    if(!empty($vv_obj->$fp->names[0])) {
      $fname = $vv_obj->$fp->names[0]->full_name;
      $flink = ['url' => ['controller' => 'people', 'action' => 'canvas', $vv_obj->$fp->id]];
    }
    
    print $this->Field->statusControl($f.'_person_id', $fname, $flink, __d('field', $f));
  }
  
// XXX these need to render date pickers
// - we specifically have code in FieldHelper that checks for these two, but not date_of_birth
//   - can FieldHelper introspect the date type rather than require a hard coded list of fields?
//     though note valid_from/through uses special logic for 00:00:00 and 23:59:59
  print $this->Field->control('valid_from');
  
  print $this->Field->control('valid_through');
}

// XXX This is a placeholder for canvas... maybe it should become a separate page
//     rather than overload fields.inc?

print $this->Html->link(
  __d('controller', 'AdHocAttributes', [99]),
  [ 'controller' => 'ad_hoc_attributes',
    'action' => 'index', 
    '?' => [
      'person_role_id' => $vv_obj->id
    ]
  ],
  ['class' => 'linkbutton']
);

print $this->Html->link(
  __d('controller', 'Addresses', [99]),
  [ 'controller' => 'addresses',
    'action' => 'index', 
    '?' => [
      'person_role_id' => $vv_obj->id
    ]
  ],
  ['class' => 'linkbutton']
);

print $this->Html->link(
  __d('controller', 'TelephoneNumbers', [99]),
  [ 'controller' => 'telephone_numbers',
    'action' => 'index', 
    '?' => [
      'person_role_id' => $vv_obj->id
    ]
  ],
  ['class' => 'linkbutton']
);
