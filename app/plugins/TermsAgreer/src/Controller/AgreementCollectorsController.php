<?php
/**
 * COmanage Registry T&C Agreement Collectors Controller
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace TermsAgreer\Controller;

use App\Controller\StandardEnrollerController;
use App\Lib\Enum\SuspendableStatusEnum;
use Cake\ORM\TableRegistry;
use TermsAgreer\Lib\Enum\TAndCEnrollmentModeEnum;

class AgreementCollectorsController extends StandardEnrollerController {
  protected array $paginate = [
    'order' => [
      'AgreementCollectors.id' => 'asc'
    ]
  ];

  /**
   * Dispatch an Enrollment Flow Step.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string  $id    Approval Collector ID
   */

  public function dispatch(string $id) {
    $request = $this->getRequest();
    $petition = $this->getPetition();
    $coId = $this->getCOID();

    // Pull the set of T&C as well as the plugin step configuration.
    // Explicit vs Implicit is handled by the frontend.

    $cfg = $this->AgreementCollectors->get($id);

    if($cfg->t_and_c_mode == TAndCEnrollmentModeEnum::Ignore) {
      // If the Plugin is set to Ignore, we simply skip this step and move on.

      return $this->finishStep(
        enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
        petitionId:           $petition->id,
        comment:              __d('terms_agreer', 'result.AgreementCollectors.ignored')
      );
    }

    // We pull Active T&C only, We pull all T&C that are not COU specific,
    // and if there is a COU associated with this Petition then we also pull T&C
    // for that COU.

    $TermsAndConditions = TableRegistry::getTableLocator()->get('TermsAndConditions');

    $whereClause = [
      'TermsAndConditions.co_id' => $coId,
      'TermsAndConditions.status' => SuspendableStatusEnum::Active
    ];

    if(!empty($petition->cou_id)) {
      $whereClause['OR'] = [
        'cou_id IS NULL',
        'cou_id' => $petition->cou_id
      ];
    } else {
      $whereClause[] = 'cou_id IS NULL';
    }

    $tandc = $TermsAndConditions->find()
                                ->contain(['MostlyStaticPages'])
                                ->where($whereClause)
                                ->order('ordr ASC')
                                ->all();

    // Calculating status of current Agreements is a bit tricky, because we need to look
    // in both the Petition (PetitionAgreements) and if this is a Petition for a Person
    // that already exists in TAndCAgreements. As a first pass, we simply ignore any
    // existing Agreements (eg if this is an Additional Role Enrollment) and we always
    // require this Step to be completed. It's valid to have multiple T&C Agreements,
    // and even desirable if they expired.

    if($request->is('post')) {
      // Walk the set of $tandc and look for an agreement in the POST data.
      // We shouldn't really get here without all $tandc agreed to since the
      // frontend shouldn't allow the enrollee to get here otherwise.

      $data = $request->getData();

      $ok = true;

      foreach($tandc as $tc) {
        // The post data is keyed on the string "tc" appended to the T&C id,
        // and the expected value is "1".

        $key = "tc".$tc->id;

        if(!isset($data[$key]) || $data[$key] != "1") {
          $ok = false;

          $this->Flash->error(__d('terms_agreer','error.TAndCAgreement.missing', [$tc->description, $tc->id]));
        }
      }

      if($ok) {
        // Record the agreements and update the Petition

        // Similar to v4, we use the Petition Token (formerly the Enrollee Token)
        // for unauthenticated Enrollments.

        $authIdentifier = $request->getSession()->read('Auth.external.user');

        if(empty($authIdentifier)) {
          $authIdentifier = $petition->token;
        }

        $this->AgreementCollectors->record(
          petitionId: $petition->id,
          agreementCollectorId: $cfg->id,
          identifier: $authIdentifier,
          tAndC: $tandc
        );

        // Redirect to the next step

        return $this->finishStep(
          enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
          petitionId:           $petition->id,
          comment:              __d('terms_agreer', 'result.AgreementCollectors.recorded.summary', $tandc->count())
        );
      }
    }

    // If there are no pending T&C, redirect to the next step.

    // Otherwise let the view render.
    $this->set('vv_tandc_mode', $cfg->t_and_c_mode);
    $this->set('vv_tandc', $tandc);

    $this->render('/Standard/dispatch');
  }
}