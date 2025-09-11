<?php
/**
 * COmanage Registry Cous Controller
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

class CousController extends StandardController {


  protected array $paginate = [
    'order' => [
      'Cous.name' => 'asc'
    ]
  ];
  
  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */
  
  public function beforeRender(\Cake\Event\EventInterface $event) {
    if(!$this->request->is('restful')) {
      // Pull the set of potential Parent COUs
      
      switch($this->request->getParam('action')) {
        case 'add':
          $this->set('parents', $this->Cous->potentialParents($this->getCOID(), null, true));
          break;
        case 'edit':
          $p = $this->request->getParam('pass');
          $couId = (int)$p[0];
          $this->set('parents', $this->Cous->potentialParents($this->getCOID(), $couId, true));
          break;
        case 'index':
          $this->set('parents', $this->Cous->potentialParents($this->getCOID()));
          break;
        default:
          break;
      }
    }
    
    return parent::beforeRender($event);
  }
}