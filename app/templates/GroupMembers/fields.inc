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
    $hidden = [
      'person_id' => $vv_selected_person['id']
    ];
    
    // Present the person information in the view
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => '',
        'fieldLabel' => __d('field','name'),
        'groupmember' => $vv_selected_person
      ]
    ]);
  } else {
    // Produce the autocomplete people selector on 'add'
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'person_id',
        'fieldOptions' => [
          'class' => 'form-control people-autocomplete',
          'placeholder' => __d('operation','autocomplete.people.placeholder'),
          'id' => 'person_id',
        ],
        'autocomplete' => [
          'configuration' => [
            'for' => 'GroupMembers',
            'action' => $vv_action,
            'groupId' => $this->getRequest()?->getQuery('group_id')
          ]
        ]
      ]
    ]);
  }
} else {
  $hidden = [
    'person_id' => $vv_obj->person_id
  ];
}

print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'valid_from',
  ]
]);

print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'valid_through',
  ]
]);

// XXX RFE: Add links to EIS or Nesting info