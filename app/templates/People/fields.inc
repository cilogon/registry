<?php
/**
 * COmanage Registry People Fields
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
if($vv_action == 'add') {
  // Adding a new CO Person is a bit non-standard vs most views. We need a
  // Primary Name (which we'll collect here) along with possibly collecting
  // other CO Person attributes.
  
  foreach(['honorific', 'given', 'middle', 'family', 'suffix'] as $f) {
    if(in_array($f, $vv_permitted_name_fields)) {
      print $this->Field->control('names.0.'.$f, ['required' => in_array($f, $vv_required_name_fields)]);
    }
  }
  
  print $this->Field->control('names.0.type_id', ['empty' => false, 'default' => $vv_default_name_type]);
  
  // AR-Name-1 Since this is the first name for this Person, it must be
  // designated primary
  $hidden = [
    // The initial name must be primary
    'names.0.primary_name' => true
  ];
} elseif($vv_action == 'edit') {
  $a = $vv_obj->extract(['person_role_id', 'person_id']);
}

if($vv_action == 'add' || $vv_action == 'edit') {
  print $this->Field->control('status', ['empty' => false]);
  
  print $this->Field->dateControl('date_of_birth', \App\Lib\Enum\DateTypeEnum::DateOnly);
}