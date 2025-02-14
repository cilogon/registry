<?php
/**
 * COmanage Registry History Records Fields
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

// This view does not support edit
if($vv_action == 'add' || $vv_action == 'view') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'comment',
    ]]);
  
  if($vv_action == 'add') {
    // On manual add insert action
// XXX and actor person id
    
    $hidden = [
      'action' => \App\Lib\Enum\ActionEnum::CommentAdded
    ];
  }
  
  if($vv_action == 'view') {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'action',
      ]]);
    
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
          'link' => $viewLink
        ]
      ]);
    }
    
    if(!empty($vv_obj->person_role_id)) {
      $viewLink = [
        'url' => [
          'controller' => 'person_roles',
          'action'     => 'edit',
          $vv_obj->person_role_id
        ],
      ];

      print $this->element('form/listItem', [
        'arguments' => [
          'fieldName' => 'person_role_id',
          'status' => $vv_obj->person_role_id,
          'link' => $viewLink
        ]
      ]);
    }
    
    if(!empty($vv_obj->external_identity)) {
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
          'status' => !empty($vv_obj->external_identity->names[0]) ? $vv_obj->external_identity->names[0]->full_name : $vv_obj->external_identity->id,
          'link' => $viewLink
        ]
      ]);
    }
    
    if(!empty($vv_obj->external_identity_role_id)) {
      $viewLink = [
        'url' => [
          'controller' => 'external_identity_roles',
          'action'     => 'edit',
          $vv_obj->external_identity_role_id
        ],
      ];

      print $this->element('form/listItem', [
        'arguments' => [
          'fieldName' => 'external_identity_role_id',
          'status' => $vv_obj->external_identity_role_id,
          'link' => $viewLink
        ]
      ]);
    }
    
    if(!empty($vv_obj->group_id)) {
      $viewLink = [
        'url' => [
          'controller' => 'groups',
          'action'     => 'edit',
          $vv_obj->group_id
        ],
      ];

      print $this->element('form/listItem', [
        'arguments' => [
          'fieldName' => 'group_id',
          'status' => $vv_obj->group->name,
          'link' => $viewLink
        ]
      ]);
    }
    
    if(!empty($vv_obj->actor_person->names)) {
      $viewLink = [
        'url' => [
          'controller' => 'people',
          'action'     => 'edit',
          $vv_obj->actor_person->id
        ],
      ];

      print $this->element('form/listItem', [
        'arguments' => [
          'fieldName' => 'actor_person_id',
          'status' => $vv_obj->actor_person->names[0]->full_name,
          'link' => $viewLink,
          'fieldLabel' => __d('field', 'actor')
        ]
      ]);
    }

    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'created',
      ]]);
  }
}
