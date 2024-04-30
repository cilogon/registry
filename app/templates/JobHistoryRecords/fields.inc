<?php
/**
 * COmanage Registry Job History Records Fields
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

// This view does not support add or edit
if($vv_action == 'view') {
  foreach(['record_key',
           'comment',
           'status'
          ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
      ]
    ]);
  }

// Rewrite these to always emit the column even if the field is blank
  if(!empty($vv_obj->person->primary_name)) {
    $viewLink = [
      'url' => [
        'controller' => 'people',
        'action'     => 'edit',
        $vv_obj->person->id
      ],
    ];

    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'person_id',
        'status' => $vv_obj->person->primary_name->full_name,
        'link' => $viewLink,
      ]
    ]);
  }
  
  if(!empty($vv_obj->external_identity->primary_name)) {
    $viewLink = [
      'url' => [
        'controller' => 'external_identities',
        'action'     => 'edit',
        $vv_obj->external_identity->id
      ],
    ];

    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'external_identity_id',
        'status' => $vv_obj->external_identity->primary_name->full_name,
        'link' => $viewLink,
      ]
    ]);
  }

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'created'
    ]
  ]);
}
