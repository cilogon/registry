<?php
/**
 * COmanage Registry Group Members Fields
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

if($vv_action == 'add') {
  if(!empty($vv_selected_person)) {
    // The person has been preselected. Created a hidden field, and present information about the person.
    print $this->Form->hidden('person_id', ['value' => $vv_selected_person['id']]);
    
    // Present the person information in the view:
?>    
    <li>
      <div class="field">
        <div class="field-name">
          <div class="field-title">
            <?= __d('field','name') ?>
          </div>
        </div>
        <div class="field-info">
          <span class="group-member-to-add-name">
            <?= $vv_selected_person['name'] ?>
          </span> 
          <span class="group-member-to-add-id">
            (<?= __d('information', 'entity.id', [$vv_selected_person['id']]) ?>)
          </span>
        </div>
      </div>
    </li>
<?php 
  } else {
    // Produce the autocomplete people selector on 'add'
    print $this->Field->peopleAutocompleteControl('person_id', [
      'for' => 'GroupMembers',
      'action' => $vv_action,
      'groupId' => $this->getRequest()?->getQuery('group_id')
    ]); 
  }
  
  // XXX As a temporary hack for development, we accept a manually entered Person ID
  // XXX leave here temporarily.
  //print $this->Field->control('person_id', ['type' => 'text']);
} else {
  print $this->Form->hidden('person_id');
}

print $this->Field->dateControl('valid_from');

print $this->Field->dateControl('valid_through');

// XXX RFE: Add links to EIS or Nesting info