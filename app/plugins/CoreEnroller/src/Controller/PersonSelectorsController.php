<?php
/**
 * COmanage Registry Person Selectors Controller
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreEnroller\Controller;

use Cake\ORM\TableRegistry;
use App\Controller\StandardEnrollerController;
use CoreEnroller\Lib\Enum\PersonSelectorConfirmationModeEnum;
use CoreEnroller\Lib\Enum\PersonSelectorModeEnum;

class PersonSelectorsController extends StandardEnrollerController {
  protected array $paginate = [
    'order' => [
      'PersonSelectors.id' => 'asc'
    ]
  ];

  /**
   * Dispatch an Enrollment Flow Step.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  string  $id    Invitation Accepter ID
   */

  public function dispatch(string $id) {
    $petition = $this->getPetition();

    // Note we do not check to see if there is already an Enrollee Person attached to
    // the Petition. This is primarily so that the Step can be re-run, eg if an
    // Approver realizes the wrong Person was selected, the Petitioner (or Approver)
    // could simply rerun this Step.

    $cfg = $this->PersonSelectors->get($id, contain: ['RoeEnrollmentFlows']);

    if($cfg->mode == PersonSelectorModeEnum::Self) {
      // For Self mode all we do is check if we have an authenticated Person ID
      // in the current CO, and if so we attach the Person to the Petition.
      // No need for an interstitial page.
      
      try {
        $personId = $this->RegistryAuth->getPersonID($this->getCOID());

        $this->PersonSelectors->attachPerson(
          id: (int)$id,
          petitionId: $petition->id,
          personId: $personId
        );

        return $this->finishStep(
          enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
          petitionId:           $petition->id,
          comment:               __d('core_enroller', 'result.PersonSelectors.attached', [$personId,  __d('core_enroller', 'enumeration.PersonSelectorModeEnum.'.$cfg->mode)])
        );
      }
      catch(\Exception $e) {
        $this->Flash->error($e->getMessage());
      }
    } elseif($cfg->mode == PersonSelectorModeEnum::Select) {
      // SELECT

      if($this->request->is(['post', 'put'])) {
        $data = $this->getRequest()->getData();

        if(!empty($data['enrollee_person_id'])) {
          if($cfg->confirmation_page_mode == PersonSelectorConfirmationModeEnum::Full
             || (!empty($data['confirm']) && $data['confirm'] === 'yes')) {
            // We're ready to update the Petiiton and move on to the next step

            try {
              $this->PersonSelectors->attachPerson(
                id: (int)$id,
                petitionId: $petition->id,
                personId: (int)$data['enrollee_person_id']
              );

              return $this->finishStep(
                enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
                petitionId:           $petition->id,
                comment:               __d('core_enroller', 'result.PersonSelectors.attached', [$data['enrollee_person_id'],  __d('core_enroller', 'enumeration.PersonSelectorModeEnum.'.$cfg->mode)])
              );
            }
            catch(\Exception $e) {
              $this->Flash->error($e->getMessage());
            }
          }

          // Pull the requested Person and provide information for the Petitioner to confirm

          $People = TableRegistry::getTableLocator()->get('People');

          $person = $People->get(
            $data['enrollee_person_id'],
            contain: [
              'PrimaryName',
              'Names' => ['Types'],
              'Addresses' => ['Types'],
              'AdHocAttributes',
              'EmailAddresses' => ['Types'],
              'Identifiers' => ['Types'],
              'PersonRoles' => ['Cous'],
              'Pronouns',
              'TelephoneNumbers' => ['Types'],
              'Urls'
            ]
          );

          $this->set('vv_enrollee_candidate', $person);
        }
      } elseif($this->request->is(['get'])) {
        // We support some additional actions based on our configuration

        $op = $this->request->getQuery('op');

        if($op == 'terminate') {
          // Terminate the Petition and end the flow

          // Note currently only CO Admins can terminate a flow, this URL will
          // throw Permission Denied if a less privileged user tries to terminate here
          return $this->redirect([
            'plugin' => null,
            'controller' => 'petitions',
            'action' => 'terminate',
            $petition->id
          ]);
        } elseif($op == 'transition') {
          // Transition to the configured flow

          if(!empty($cfg->roe_enrollment_flow_id)) {
            return $this->transitionToFlow($petition->id, $cfg->roe_enrollment_flow_id);
          } else {
            // We shouldn't get here, but if we do throw an error
            throw new \RuntimeException('Transition Flow not configured');
          }
        }
        // else fall through and let the people picker render
      }
    } else {
      throw new \RuntimeException('NOT IMPLEMENTED');
    }

    // Fall through and let the form render

    $this->set('vv_config', $cfg);

    $this->render('/Standard/dispatch');
  }
}