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
//use \App\Lib\Enum\PermissionEnum;

use Cake\ORM\TableRegistry;

class CosController extends StandardController {
  public $paginate = [
    'order' => [
      'Cos.name' => 'asc'
    ]
  ];
  
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