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

$fields = [];

if($vv_action == 'add') {
  if(!empty($vv_selected_person)) {
    // The person has been preselected. Created a hidden field, and present information about the person.
    $hidden = [
      'person_id' => $vv_selected_person['id']
    ];
    // Present the person name in the view. Note that 'person_name' is not a real field. 
    // Because 'groupmember' is set in this field definition, this field information will be
    // passed to the element/form/infoDiv/groupMember to display the Person name rather than
    // generate a field. 'person_name' will be used only to set the class of the container <li> element.
    $fields['person_name'] = [
      'fieldLabel' => __d('field','name'),
      'groupmember' => $vv_selected_person
    ];
  } else {
    // Produce the autocomplete people selector on 'add'
    $fields['person_id'] = [
      'class' => 'form-control people-autocomplete',
      'placeholder' => __d('operation','autocomplete.people.placeholder'),
      'id' => 'person_id',
      'autocomplete' => [
        'configuration' => [
          'for' => 'GroupMembers',
          'action' => $vv_action,
          'groupId' => $this->getRequest()?->getQuery('group_id')
        ]
      ]
    ];
  }
} else {
  $hidden = [
    'person_id' => $vv_obj->person_id
  ];
  
  // Set the selected person for edit and view also
  // so we can display the name and link to the person canvas.
  $selectedPerson = [
    'id' => $vv_obj->person_id,
    'name' => $vv_obj->person->primary_name->full_name
  ];
  $this->set('vv_selected_person', $selectedPerson);
  // See note in the 'add' section above regarding how 'person_name' is used.
  // It is not a real field name.
  $fields['person_name'] = [
    'fieldLabel' => __d('field','name'),
    'groupmember' =>  $selectedPerson
  ];
}

$fields = array_merge($fields, [
  'valid_from',
  'valid_through'
]);

// XXX RFE: Add links to EIS or Nesting info