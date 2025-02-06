<?php
/**
 * COmanage Registry Basic Invitation Accepters Controller
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
 * @package       registry-plugins
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreEnroller\Controller;

use Cake\ORM\TableRegistry;
use App\Controller\StandardEnrollerController;
use App\Lib\Enum\PetitionActionEnum;

class InvitationAcceptersController extends StandardEnrollerController {
  public $paginate = [
    'order' => [
      'InvitationAccepters.id' => 'asc'
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
  
  public function beforeRender(\Cake\Event\EventInterface $event) {
    $link = $this->getPrimaryLink(true);
    
    if(!empty($link->value)) {
      $this->set('vv_bc_parent_obj', $this->InvitationAccepters->EnrollmentFlowSteps->get($link->value));
      $this->set('vv_bc_parent_displayfield', $this->InvitationAccepters->EnrollmentFlowSteps->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->InvitationAccepters->EnrollmentFlowSteps->getPrimaryKey());
    }
    
    return parent::beforeRender($event);
  }

  /**
   * Dispatch an Enrollment Flow Step.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string  $id    Invitation Accepter ID
   */

  public function dispatch(string $id) {
    $petition = $this->getPetition();

    $cfg = $this->InvitationAccepters->get($id);

    $this->set('vv_config', $cfg);

    $PetitionAcceptances = TableRegistry::getTableLocator()->get('CoreEnroller.PetitionAcceptances');

    $pa = $PetitionAcceptances->find()->where(['petition_id' => $petition->id])->first();

    $this->set('vv_pa', $pa);

    if($this->request->is(['post', 'put'])) {
      $data = $this->request->getData();

      $accepted = (bool)$data['accepted'];

      try {
        $PetitionAcceptances->processReply(
          $petition->id, 
          $cfg->enrollment_flow_step_id,
          $accepted
        );

        if($accepted) {
          // On acceptance, indicate the step is completed and generate a redirect
          // to the next step

          $link = $this->getPrimaryLink(true);

          return $this->finishStep(
            enrollmentFlowStepId: $link->value,
            petitionId:           $petition->id,
            comment:               __d('core_enroller', ($accepted ? 'result.accept.accepted' : 'result.accept.declined'))
          );
        } else {
          // On decline, set a flash message and redirect to the petition complete landing
          $this->Flash->success(__d('core_enroller', 'result.accept.declined'));

          $coId = $this->getCOID();

          return $this->redirect("/$coId/petition-complete");          
        }
      }
      catch(\Exception $e) {
        $this->Flash->error($e->getMessage());
      }
    } else {
      // Record that the invitation was viewed
      $PetitionHistoryRecords = TableRegistry::getTableLocator()->get('PetitionHistoryRecords');

      $PetitionHistoryRecords->record(
        petitionId:           $petition->id,
        enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
        action:               PetitionActionEnum::InvitationViewed,
        comment:              __d('result', 'Petitions.viewed.inv')
        // actorPersonId
      );
    }

    // Fall through and let the form render*/

    $this->render('/Standard/dispatch');
  }
}
