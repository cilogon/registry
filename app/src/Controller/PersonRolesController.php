<?php
/**
 * COmanage Registry Person Roles Controller
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

// Use extend MVEAController for breadcrumb rendering. PersonRoles is sort of
// an MVEA, so maybe it makes sense to treat it as such.
class PersonRolesController extends MVEAController {
  public $paginate = [
    'order' => [
      'PersonRoles.ordr' => 'asc',
      'PersonRoles.title' => 'asc'
    ]
  ];

  // Cache the personStatus on add/edit actions, in order to render a flash message
  protected $cachedPerson = null;

  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */

  public function beforeFilter(\Cake\Event\EventInterface $event) {
    if(!$this->request->is('restful') 
       && ($this->request->is('post') || $this->request->is('put'))
       && in_array($this->request->getParam('action'), ['add', 'edit'])) {
      // Cache the current Person so to see if status was recalculated
      $this->cachedPerson = $this->PersonRoles->People->get($this->request->getData('person_id'));
    }

    return parent::beforeFilter($event);
  }

  /**
   * Set supplemental Flash messages.
   * 
   * @since  COmanage Registry v5.0.0
   */

  public function setSupplementalFlash($entity) {
    // If we auto-recalculated the Person Role status, set a Flash message
    $autoStatus = $this->PersonRoles->getAutoStatus();

    if(!empty($autoStatus)) {
      $this->Flash->information(__d('result', 
                                    'PersonRoles.status.recalculated', 
                                    [__d('enumeration', 'StatusEnum.'.$autoStatus['from']), 
                                    __d('enumeration', 'StatusEnum.'.$autoStatus['to'])]));
    }

    if(!empty($this->cachedPerson)) {
      // See if we have a new Person status value, and if so set a Flash message

      $person = $this->PersonRoles->People->get($this->cachedPerson->id);

      if($this->cachedPerson->status != $person->status) {
        $this->Flash->information(__d('result', 
                                      'People.status.recalculated', 
                                      [__d('enumeration', 'StatusEnum.'.$this->cachedPerson->status), 
                                      __d('enumeration', 'StatusEnum.'.$person->status)]));
      }
    }
  }
}