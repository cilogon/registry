<?php
/**
 * COmanage Registry Cos Controller
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

class CosController extends StandardController {
  protected array $paginate = [
    'order' => [
      'Cos.name' => 'asc'
    ]
  ];
  
  /**
   * Perform Controller initialization.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function initialize(): void {
    parent::initialize();
    
    // Configure breadcrumb rendering
    $this->Breadcrumb->skipAll(['/^\/cos\/select/']);
  }

  /**
   * Callback run prior to the view rendering.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */
    
  public function beforeRender(\Cake\Event\EventInterface $event) {
    // In order to get the sidebar to render we need to set the current CO,
    // which for cos is the COmanage CO.
    $this->set('vv_cur_co', $this->Cos->find('COmanageCO')->firstOrFail());
    
    return parent::beforeRender($event);
  }
  
  /**
   * Handle a delete action for a Standard object.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Integer $id Object ID
   */
  
  public function delete($id) {
    // XXX this could ultimately merge into StandardController
    if(!empty($this->request->getQuery('queue'))
       && $this->request->getQuery('queue') == 'yes') {
      // Register a Job to delete the requested entity

      $JobTable = TableRegistry::getTableLocator()->get("Jobs");

      try {
        $comanageco = $this->Cos->find('COmanageCO')->firstOrFail();

        $JobTable->register(
          coId:             $comanageco->id,
          plugin:           'CoreJob.DeletionJob',
          parameters:       ['target_model' => 'Cos', 'target_id' => $id],
          registerSummary:  __d('core_job', 'Deletion.register_summary', ['Cos', $id])
        );

        // Because (unlike v4 Garbage Collection) Deletion Job doesn't use
        // a special status, we update the entity description to provide a
        // simple indicator to administrators. See also CFM-94.

        $co = $this->Cos->get($id);
        $co->description = __d('information', 'cos.delete.sched');
        $this->Cos->save($co);

        $this->Flash->success(__d('core_job', 'Deletion.register_summary', ['Cos', $id]));
      }
      catch(\Exception $e) {
        $this->Flash->error($e->getMessage());
      }

      return $this->generateRedirect(null);
    } else {
      return parent::delete($id);
    }
  }

  /*
   * XXX implement, also REST API
   *
   * @since  COmanage Registry v5.0.0
   * @param  Integer $id CO ID
   */
  
  public function duplicate(int $id) {
    
  }
  
  /**
   * Provide a set of COs to operate on.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function select() {
    // Population of vv_available_cos is currently done in AppController
    // since it's also used to determine if the "change collaboration" menu
    // should render.
    
    // If only one CO is found, auto-redirect into it.
    
    $availableCos = $this->viewBuilder()->getVar('vv_available_cos');
    
    if($availableCos && count($availableCos) === 1) {
      return $this->redirect([
        'controller'  => 'dashboards',
        'action'      => 'dashboard',
        '?' => [
          'co_id' => $availableCos[0]->id
        ]
      ]);
    }
  }
}