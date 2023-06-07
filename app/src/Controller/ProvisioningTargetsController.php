<?php
/**
 * COmanage Registry Provisioning Targets Controller
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

class ProvisioningTargetsController extends StandardPluggableController {
  public $paginate = [
    'order' => [
      'ProvisioningTargets.description' => 'asc'
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
    $this->Breadcrumb->skipConfig(['/^\/provisioning-targets\/status/']);
    $this->Breadcrumb->skipParents(['/^\/provisioning-targets\/status/']);
  }

  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */

  public function beforeFilter(\Cake\Event\EventInterface $event) {
    if(!$this->request->is('restful')) {
      // Provide additional hints to BreadcrumbsComponent. This needs to be here
      // and not in beforeRender because the component beforeRender will run first.
      
      if($this->request->getParam('action') == 'status') {
        $this->Breadcrumb->injectPrimaryLink($this->getPrimaryLink(true));
      }
    }

    return parent::beforeFilter($event);
  }

  /**
   * Generate a status index.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function status() {
    // PrimaryLinkTrait - Look up our primary link to see which object type we're
    // working with, an also get our CO ID
    $link = $this->getPrimaryLink(true);

    if($link->attr == 'person_id') {
      $statuses = $this->ProvisioningTargets->status(coId: $link->co_id, personId: (int)$link->value);
    } elseif($link->attr == 'group_id') {
      $statuses = $this->ProvisioningTargets->status(coId: $link->co_id, groupId: (int)$link->value);
    }

    $this->set('vv_provisioning_statuses', $statuses);

    if(!$this->request->is('restful')) {
      $this->set('vv_title', __d('operation', 'provisioning.status'));
    }
  }
}