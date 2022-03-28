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
  print $this->Field->control('comment');
  
  if($vv_action == 'add') {
    // On manual add insert action
// XXX and actor person id
    
    $hidden = [
      'action' => \App\Lib\Enum\ActionEnum::CommentAdded
    ];
  }
  
  if($vv_action == 'view') {
    print $this->Field->control('action');
    
    if(!empty($vv_obj->person->primary_name)) {
      $viewLink = [
        'url' => [
          'controller' => 'people',
          'action'     => 'edit',
          $vv_obj->person->id
        ],
      ];
      
      print $this->Field->statusControl(
        'person_id',
        $vv_obj->person->primary_name->full_name,
        $viewLink
      );
    }
    
    if(!empty($vv_obj->person_role_id)) {
      $viewLink = [
        'url' => [
          'controller' => 'person_roles',
          'action'     => 'edit',
          $vv_obj->person_role_id
        ],
      ];
      
      print $this->Field->statusControl(
        'person_role_id',
        $vv_obj->person_role_id,
        $viewLink
      );
    }
    
    if(!empty($vv_obj->external_identity->primary_name)) {
      $viewLink = [
        'url' => [
          'controller' => 'external_identities',
          'action'     => 'edit',
          $vv_obj->external_identity->id
        ],
      ];
      
      print $this->Field->statusControl(
        'external_identity_id',
        $vv_obj->external_identity->primary_name->full_name,
        $viewLink
      );
    }
    
    if(!empty($vv_obj->external_identity_role_id)) {
      $viewLink = [
        'url' => [
          'controller' => 'external_identity_roles',
          'action'     => 'edit',
          $vv_obj->external_identity_role_id
        ],
      ];
      
      print $this->Field->statusControl(
        'external_identity_role_id',
        $vv_obj->external_identity_role_id,
        $viewLink
      );
    }
    
    if(!empty($vv_obj->actor_person->names)) {
      $viewLink = [
        'url' => [
          'controller' => 'people',
          'action'     => 'edit',
          $vv_obj->actor_person->id
        ],
      ];
      
      print $this->Field->statusControl(
        'actor_person_id',
        $vv_obj->actor_person->names[0]->full_name,
        $viewLink,
        __d('field', 'actor')
      );
    }
    
    print $this->Field->control('created');
  }
}
