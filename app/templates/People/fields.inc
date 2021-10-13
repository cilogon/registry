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
  
// XXX this should be subject to the CO Setting for name fields
// XXX how do we flag the name fields as required? need to update auto-introspection
//     in whatever calls startControlSet()
  print $this->Field->control('names.0.given');
  
  print $this->Field->control('names.0.family');
  
// XXX should cluster name fields together so it's obvious type relates to name
// XXX how do we set a default value here? ['default' => X], but what is X? it's
//     an integer that varies according to the CO, we'd need a find where co=x and attribute=Name.type and name=official
  print $this->Field->control('names.0.type_id', ['empty' => false]);
  
  print $this->Field->control('status', ['empty' => false]);
  
  print $this->Field->control('date_of_birth');
  
  $hidden = [
    // The initial name must be primary
    'names.0.primary_name' => true
  ];
} elseif($vv_action == 'canvas') {
  // XXX This is a placeholder for canvas... maybe it should become a separate page
  //     rather than overload fields.inc?
  
  print $vv_obj->primary_name->common_name;
}