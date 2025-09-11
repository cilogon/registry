<?php
/**
 * COmanage Registry Group Members Controller
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

declare(strict_types = 1);

namespace App\Controller;

// XXX not doing anything with Log yet
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\Log\Log;

class GroupMembersController extends StandardController {
  protected array $paginate = [
    'order' => [
      'People.primary_name.name' => 'asc'
    ]
  ];

  /**
   * Callback run prior to the request render.
   *
   * @param   EventInterface  $event  Cake Event
   *
   * @return Response|void
   * @since  COmanage Registry v5.0.0
   */

  public function beforeRender(EventInterface $event) {
    // Pull the Group name for breadcrumb rendering
    
    $link = $this->getPrimaryLink(true);
    
    if(!empty($link->value)) {
      $this->set('vv_bc_parent_obj', $this->GroupMembers->Groups->get($link->value));
      $this->set('vv_bc_parent_displayfield', $this->GroupMembers->Groups->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->GroupMembers->Groups->getPrimaryKey());
    }
    
    return parent::beforeRender($event);
  }
  
  /**
   * Handle an add action for a Group Member.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function add() {
    // If we have a person_id in the request, the person has been pre-selected.
    if(!empty($this->request->getQuery('person_id'))) {
      $personId = $this->request->getQuery('person_id');
      $Names = $this->getTableLocator()->get('Names');
      $personName = $Names->primaryName((int)$personId)->full_name;
      $selectedPerson = [
        'id' => $personId,
        'name' => $personName
      ];
      $this->set('vv_selected_person', $selectedPerson);
    }
    
    return parent::add();
  }
  
  /**
   * Handle the deleted action for a Group Member.
   * This is used to set a flash message and override the target window.
   * 
   * @since  COmanage Registry v5.0.0
   */
  
  public function deleted() {
    // Add a flash message
    $this->Flash->information(__d('result','GroupMember.deleted'));
    // Set the target window
    $this->set('vv_target_window', 'top');
    
    return parent::deleted();
  }
}