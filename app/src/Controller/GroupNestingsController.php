<?php
/**
 * COmanage Registry Group Nestings Controller
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
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

class GroupNestingsController extends StandardController {
  protected array $paginate = [
    'order' => [
      'Group.name' => 'asc'
    ]
  ];
  
  /**
   * Handle an add action for a Standard object.
   *
   * @since  COmanage Registry v5.2.0
   */
  
  public function add() {
    if($this->request->is('post')) {
      // if queue is true, extract the parameters and queue a job,
      // otherwise pass through to parent::add()

      $data = $this->request->getData();

      if(!empty($data['queue'] && $data['queue'])) {
        // This is substantially similar to queue(), below

        $JobTable = TableRegistry::getTableLocator()->get("Jobs");

        try {
          $job = $JobTable->register(
            coId:             $this->getCOID(),
            plugin:           'CoreJob.NesterJob',
            parameters:       ['group_id' => $data['group_id'], 
                               'target_group_id' => $data['target_group_id'],
                               'negate' => (bool)$data['negate']],
            registerSummary:  __d('result', 'GroupNesting.add.queued.ok', [$data['group_id'], $data['target_group_id']])
          );

          $this->Flash->success(__d('result', 'GroupNesting.add.queued.ok', [$data['group_id'], $data['target_group_id']]));

          return $this->redirect([
            'controller' => 'jobs',
            'action' => 'view',
            $job->id
          ]);
        }
        catch(\Exception $e) {
          $this->Flash->error($e->getMessage());
          // We need to be careful not to call parent on failure or we'll end up
          // immediately processing the request
        }
      } else {
        return parent::add();
      }
    } else {
      return parent::add();
    }
  }

  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */

  public function beforeRender(\Cake\Event\EventInterface $event) {
    if($this->request->getParam('action') != 'deleted') {
      // Pull the Group name for breadcrumb rendering
      $link = $this->getPrimaryLink(true);
      
      if(!empty($link->value)) {
        $this->set('vv_bc_parent_obj', $this->GroupNestings->Groups->get($link->value));
        $this->set('vv_bc_parent_displayfield', $this->GroupNestings->Groups->getDisplayField());
        $this->set('vv_bc_parent_primarykey', $this->GroupNestings->Groups->getPrimaryKey());
      }
      
      // We need to calculate the available set of groups for nesting. We do this
      // here rather than via autoViewVars because we need to know the current
      // group (to exclude it).
      
      $this->set('targetGroups', $this->GroupNestings->availableGroups((int)$link->value));
      
      return parent::beforeRender($event);
    }
  }
  
  /**
   * Handle the deleted action for a Group Nesting.
   * This is used to set a flash message and override the target window.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function deleted() {
    // Add a flash message
    $this->Flash->information(__d('result','GroupNesting.deleted'));
    // Set the target window
    $this->set('vv_target_window', 'top');
    
    return parent::deleted();
  }

  /**
   * Queue a job to process a Group Nesting removal.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string $id  Record ID, for removal only
   */

  public function queue(string $id) {
    $JobTable = TableRegistry::getTableLocator()->get("Jobs");

    try {
      $job = null;

      // Requesting removal of an existing Group Nesting

      $job = $JobTable->register(
        coId:             $this->getCOID(),
        plugin:           'CoreJob.DeletionJob',
        parameters:       ['target_model' => 'GroupNestings', 'target_id' => $id],
        registerSummary:  __d('result', 'GroupNesting.delete.queued.ok', [$id])
      );

      $this->Flash->success(__d('result', 'GroupNesting.delete.queued.ok', [$id]));

      return $this->redirect([
        'controller' => 'jobs',
        'action' => 'view',
        $job->id
      ]);
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }
  }
}