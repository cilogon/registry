<?php
/**
 * COmanage Registry External Identity Roles Fields
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
  print $this->Field->control('affiliation_type_id', labelText: __d('field', 'affiliation'));
  
  print $this->Field->control('status', ['empty' => false]);
  
  print $this->Field->control('ordr');
  
  print $this->Field->control('title');
  
  print $this->Field->control('organization');
  
  print $this->Field->control('department');
  
// XXX need to clarify this is an _identifier_ not an actual Person FK
  print $this->Field->control('sponsor_identifier', labelText: __d('field', 'sponsor'));
  
  print $this->Field->control('manager_identifier', labelText: __d('field', 'manager'));
  
  print $this->Field->dateControl('valid_from');
  
  print $this->Field->dateControl('valid_through');
}
