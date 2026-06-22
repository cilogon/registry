<?php
/**
 * COmanage Registry Password Collectors Controller
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

namespace PasswordAuthenticator\Controller;

use Cake\ORM\TableRegistry;
use App\Controller\StandardEnrollerController;
use App\Lib\Enum\ProvisioningContextEnum;
use App\Lib\Enum\RequiredEnum;
use App\Lib\Enum\SuspendableStatusEnum;

class PasswordCollectorsController extends StandardEnrollerController {
  protected array $paginate = [
    'order' => [
      'PasswordCollectors.id' => 'asc'
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

    $cfg = $this->PasswordCollectors->get($id, contain: ['Authenticators' => 'PasswordAuthenticators']);

    if($cfg->authenticator->status != SuspendableStatusEnum::Active) {
      throw new \InvalidArgumentException(__d('error', 'inactive', [__d('controller', 'Authenticators', [1]), $cfg->authenticator_id]));
    }

    // If the Password Collector is set as Not Permitted simply redirect to the next Step
    if($cfg->required == RequiredEnum::NotPermitted) {
      return $this->finishStep(
        enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
        petitionId:           $petition->id,
        comment:              __d('password_authenticator', 'result.PasswordCollector.notpermitted')
      );
    }

    if($this->getRequest()->is(['post', 'put'])) {
      if($cfg->required == RequiredEnum::Optional) {
        // This Password is optional, see if the Enrollee actually selected one

        $skip = $this->request->getData('skip');

        if(!empty($skip)) {
          // The Enrollee requested to skip this Password setup. Record that and finish the Step.

          return $this->finishStep(
            enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
            petitionId:           $petition->id,
            comment:              __d('password_authenticator', 'result.PasswordCollector.skipped')
          );
        }
      }

      if(isset($cfg->authenticator->enable_ptp)
         && $cfg->authenticator->enable_ptp) {
        // If PTP is enabled, we immediately call PasswordsTable::process() to handle
        // the request. This requires an Enrollee Person ID to already be
        // set, so we fail if we don't have one.

        $Passwords = TableRegistry::getTableLocator()->get('PasswordAuthenticator.Passwords');

        // For consistency with PeopleTable::marshalProvisioningData, we expect an
        // array of entities rather than a ResultSet.
        $ptpdata = $Passwords->process($cfg->authenticator, $petition->enrollee_person_id, $this->request->getData());

        // We also need to call provisioning now.

        $Passwords->People->requestProvisioning(
          id: $petition->enrollee_person_id, 
          context: ProvisioningContextEnum::Enrollment,
          passThroughData: $ptpdata
        );
      } else {
        // If PTP is not enabled, we simply store the selected Password
        // in the Petition for later processing during finalization.

        try {

          $this->PasswordCollectors->stash(
            config:   $cfg,
            petition: $petition,
            data:     $this->request->getData()
          );

          // On success, indicate the step is completed and generate a redirect
          // to the next step

          return $this->finishStep(
            enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
            petitionId:           $petition->id,
            comment:              __d('password_authenticator', 'result.PasswordCollector.stashed')
          );
        }
        catch(\Exception $e) {
          // This is most likely a Password validation error, just show
          // the flash and let the form render again

          $this->Flash->error($e->getMessage());
        }
      }
    }

    $this->set('vv_config', $cfg);
    
    $this->render('/Standard/dispatch');
  }
}