<?php
/**
 * COmanage Registry COU Selectors Controller
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
use CoreEnroller\Lib\Enum\CouSelectorModeEnum;

class CouSelectorsController extends StandardEnrollerController {
  protected array $paginate = [
    'order' => [
      'CouSelectors.id' => 'asc'
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

    $cfg = $this->CouSelectors->get($id);

    // Populate the list of available COUs and a default COU ID, according to
    // the configuration.

    $Cous = TableRegistry::getTableLocator()->get('Cous');

    $candidateCous = [];
    $default_cou_id = null;

    if($cfg->mode == CouSelectorModeEnum::All) {
      // Any available COU may be selected.

      $candidateCous = $Cous->find('list')
                            ->where(['co_id' => $this->getCOID()])
                            ->order(['name' => 'asc'])
                            ->toArray();
    } elseif($cfg->mode == CouSelectorModeEnum::Member) {
      // Only COUs the Actor is a Member of may be selected. Note this implies this
      // Step is run by someone who is already a member of the CO. For CO Admins,
      // all available COUs are presented.

      if($this->RegistryAuth->isCoAdmin($this->getCOID())) {
        $candidateCous = $Cous->find('list')
                              ->where(['co_id' => $this->getCOID()])
                              ->order(['name' => 'asc'])
                              ->toArray();
      } else {
        $personId = $this->RegistryAuth->getPersonID($this->getCOID());

        $candidateCous = $Cous->PersonRoles->getValidCous($personId);
      }
    } elseif($cfg->mode == CouSelectorModeEnum::Specific) {
      // The configuration requires a specific COU.

      if(empty($cfg->default_cou_id)) {
        throw new \InvalidArgumentException(__d('core_enroller', 'error.CouSelectors.default_cou_id'));
      }

      $candidateCous = $Cous->find('list')
                            ->where(['id' => $cfg->default_cou_id])
                            ->order(['name' => 'asc'])
                            ->toArray();

    } else {
      // Unknown mode
      throw new \InvalidArgumentException(__d('error', 'unknown', $cfg->mode));
    }

    if(!empty($petition->cou_id) 
       && isset($candidateCous[ $petition->cou_id ])) {
      // If a COU ID is already set in the Petition (presumably because this
      // Step already ran), and it is a valid candidate COU, make it the form default.

      $default_cou_id = $petition->cou_id;
    } elseif(!empty($cfg->default_cou_id)
             && isset($candidateCous[ $cfg->default_cou_id ])) {
      // If the configured default COU is a valid option, set it as the default

      $default_cou_id = $cfg->default_cou_id;
    }

    if(isset($cfg->auto_select) && $cfg->auto_select) {
      // If there is exactly one option (which could happen in any mode)
      // select it without requesting anything from the Actor.

      if(count($candidateCous) == 1) {
        // Update $petition->cou_id and redirect

        $autoCouId = array_key_first($candidateCous);

        try {
          $this->CouSelectors->attachCou(
            id: (int)$id,
            petitionId: $petition->id,
            couId: $autoCouId
          );

          return $this->finishStep(
            enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
            petitionId:           $petition->id,
            comment:               __d('core_enroller', 'result.CouSelectors.attached', [$autoCouId, __d('core_enroller', 'enumeration.CouSelectorModeEnum.'.$cfg->mode)])
          );
        }
        catch(\Exception $e) {
          $this->Flash->error($e->getMessage());
        }
      }
    }

    if($this->request->is(['post', 'put'])) {
      $data = $this->getRequest()->getData();

      if(!empty($data['cou_id'])) {
        // FormProtection should prevent the cou_id from being an invalid value,
        // but there are edge cases (like where the configuration was changed while
        // the Petition was in progress) and we already have the data we need to
        // perform the check, so we'll just sanity check it here.

        if(!isset($candidateCous[ $data['cou_id'] ])) {
          throw new \InvalidArgumentException(__d('core_enroller', 'error.CouSelectors.cou_id.invalid', $data['cou_id']));
        }

        // Attach the COU to the Petition

        try {
          $this->CouSelectors->attachCou(
            id: (int)$id,
            petitionId: $petition->id,
            couId: (int)$data['cou_id']
          );

          return $this->finishStep(
            enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
            petitionId:           $petition->id,
            comment:               __d('core_enroller', 'result.CouSelectors.attached', [$data['cou_id'], __d('core_enroller', 'enumeration.CouSelectorModeEnum.'.$cfg->mode)])
          );
        }
        catch(\Exception $e) {
          $this->Flash->error($e->getMessage());
        }
      }
      // We shouldn't get here, but if we do we can just fall through and let the
      // form render again
    }

    // Fall through and let the form render

    $this->set('vv_config', $cfg);
    $this->set('cous', $candidateCous);
    $this->set('vv_default_cou_id', $default_cou_id);

    $this->render('/Standard/dispatch');
  }
}