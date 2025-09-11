<?php
/**
 * COmanage Registry External Identities Controller
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

use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;

// Use extend MVEAController for breadcrumb rendering. ExternalIdentities is
// sort of an MVEA, so maybe it makes sense to treat it as such.
class ExternalIdentitiesController extends MVEAController {
  protected array $paginate = [
    'order' => [
      'Name.family' => 'asc'
    ],
    'sortableFields' => [
      'Names.given',
      'Names.family'
    ]
  ];

  /**
   * Adopt an External Identity.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string   $id     External Identity ID
   */

  public function adopt(string $id) {
    try {
      $personId = $this->ExternalIdentities->adopt((int)$id);

      $this->Flash->success(__d('result', 'ExternalIdentities.adopted', [$id]));
      
      // Redirect to the Person that adopted this External Identity

      return $this->redirect([
        'controller'  => 'people',
        'action'      => 'edit',
        $personId
      ]);
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());

      return $this->generateRedirect($this->ExternalIdentities->get((int)$id));
    }
  }

  /**
   * Callback run prior to the request render.
   *
   * @param   EventInterface  $event  Cake Event
   *
   * @return Response|void
   * @since  COmanage Registry v5.0.0
   */

  public function beforeRender(EventInterface $event) {
    // Pull the Person name for breadcrumb rendering

    $link = $this->getPrimaryLink(true);

    if(!empty($link->value)) {
      $this->set('vv_bc_parent_obj', $this->ExternalIdentities->People->get($link->value));
      $this->set('vv_bc_parent_displayfield', $this->ExternalIdentities->People->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->ExternalIdentities->People->getPrimaryKey());
    }

    return parent::beforeRender($event);
  }

  /**
   * Relink an External Identity.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string   $id     External Identity ID
   */

  public function relink(string $id) {
    if($this->request->is('post')) {
      $reqData = $this->getRequest()->getData();

      if(!empty($reqData['target_person_id'])) {
        try {
          $Pipelines = TableRegistry::getTableLocator()->get('Pipelines');

          $Pipelines->relink((int)$id, (int)$reqData['target_person_id']);

          $this->Flash->success(__d('result', 'ExternalIdentities.relinked', [$id, $reqData['target_person_id']]));
        
          // Redirect to the External Identity
          return $this->redirect([
            'controller'  => 'external-identities',
            'action'      => 'view',
            $id
          ]);
        }
        catch(\Exception $e) {
          $this->Flash->error($e->getMessage());
        }
      } else {
        $this->Flash->error(__d('error', 'notprov', ['target_person_id']));
      }
    }

    // Fall through to the view to render a People Picker

    $this->set('vv_title', __d('operation', 'relink.a', [__d('controller', 'ExternalIdentities', [1])]));

    $this->set('vv_external_identity', $this->ExternalIdentities->get((int)$id, ['contain' => 'Names']));
  }}