<?php
/**
 * COmanage Registry Plugins Controller
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

class PluginsController extends StandardController {
  protected array $paginate = [
    'order' => [
      'Plugins.plugin' => 'asc'
    ]
  ];

  /**
   * Apply the database schema for a Plugin.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $id Plugin ID
   */

  public function applySchema(string $id) {
    try {
      $this->Plugins->applySchema((int)$id);
      $this->Flash->success(__d('result', 'applied.schema'));
    }
    catch(Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->redirect(['action' => 'index']);
  }

  /**
   * Activate a Plugin.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $id Plugin ID
   */

  public function activate(string $id) {
    try {
      $this->Plugins->activate((int)$id);
      $this->Flash->success(__d('result', 'activated', __d('controller', 'Plugins', [1])));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->redirect(['action' => 'index']);
  }
  
  /**
   * Callback run prior to the view rendering.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */
  
  public function beforeRender(\Cake\Event\EventInterface $event) {
    // In order to get the sidebar to render we need to set the current CO,
    // which for plugins is the COmanage CO.
    $CosTable = $this->fetchTable('Cos');
    $this->set('vv_cur_co', $CosTable->find('COmanageCO')->firstOrFail());
    
    // The Plugins table is a "meta" table rather than configuration, but we should load the 
    // "configuration" breadcrumb. Override vv_bc_configuration_link to be true so that the
    // breadcrumb renders.  
    $this->set('vv_bc_configuration_link', true);
    
    return parent::beforeRender($event);
  }

  /**
   * Deactivate a Plugin.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $id Plugin ID
   */

  public function deactivate(string $id) {
    try {
      $this->Plugins->deactivate((int)$id);
      $this->Flash->success(__d('result', 'deactivated', __d('controller', 'Plugins', [1])));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->redirect(['action' => 'index']);
  }
  
  /**
   * Generate an index for the global set of Plugins.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function index() {
    // Loading this page (Configuration > Plugins) triggers the Plugin Registry refresh (AR-Plugin-11).
    $this->Plugins->syncPluginRegistry();
    
    parent::index();
  }
}