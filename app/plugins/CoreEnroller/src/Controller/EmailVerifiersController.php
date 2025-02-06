<?php
/**
 * COmanage Registry Email Verifiers Controller
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
use App\Lib\Enum\PetitionStatusEnum;
use App\Lib\Util\StringUtilities;
use CoreEnroller\Lib\Enum\VerificationModeEnum;

class EmailVerifiersController extends StandardEnrollerController {
  public $paginate = [
    'order' => [
      'EmailVerifiers.id' => 'asc'
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
      $this->set('vv_bc_parent_obj', $this->EmailVerifiers->EnrollmentFlowSteps->get($link->value));
      $this->set('vv_bc_parent_displayfield', $this->EmailVerifiers->EnrollmentFlowSteps->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->EmailVerifiers->EnrollmentFlowSteps->getPrimaryKey());
    }
    
    return parent::beforeRender($event);
  }

  /**
   * Dispatch an Enrollment Flow Step.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string  $id    Email Verifier ID
   */

  public function dispatch(string $id) {
    $op = $this->requestParam('op');
    
    if(!$op) {
      $op = 'index';
    }

    $this->set('vv_op', $op);

    $petition = $this->getPetition();

    $cfg = $this->EmailVerifiers->get($id);

    $candidateAddresses = $this->EmailVerifiers->assembleVerifiableAddresses($cfg, $petition);

    $this->set('vv_config', $cfg);
    $this->set('vv_email_addresses', $candidateAddresses);

    // To make things easier for the view, we'll create a separate view var with the
    // addresses that have actually been verified.

    $verifiedAddresses = [];

    foreach($candidateAddresses as $a => $v) {
      if(!empty($v->verification->verification_time)) {
        $verifiedAddresses[$a] = true;
      }
    }

    $this->set('vv_verified_addresses', $verifiedAddresses);

    // And perform some calculations
    $doneCount = count($verifiedAddresses);
    $totalCount = count($candidateAddresses);
    $allDone = $doneCount == $totalCount;
    $minimumMet = $cfg->mode == VerificationModeEnum::None
                  || ($cfg->mode == VerificationModeEnum::One
                      && $doneCount > 0)
                  || ($cfg->mode == VerificationModeEnum::All
                      && $allDone);

    $this->set('vv_all_done', $allDone);
    $this->set('vv_minimum_met', $minimumMet);

    if($op == 'verify') {
      // Before we get into the actual logic, check that the requested email address
      // is in the set of candidate addresses.

      $mail = StringUtilities::urlbase64decode($this->requestParam('m'));

      if(!array_key_exists($mail, $candidateAddresses)) {
        $this->llog('error', "Requested address $mail is not a valid candidate");

        $this->Flash->error(__d('core_enroller', 'error.EmailVerifiers.candidate'));
      } elseif(isset($verifiedAddresses[$mail])) {
        $this->llog('debug', "Requested address $mail is already verified");

        $this->Flash->error(__d('core_enroller', 'error.EmailVerifiers.verified'));
      } else {
        if($this->request->is('post')) {
          $PetitionVerifications = TableRegistry::getTableLocator()->get('CoreEnroller.PetitionVerifications');

          // We're back with the code. Note many parameters (but not code) will be in
          // both the URL and the post body because of how dispatch.php sets up
          // FormHelper.

          $code = $this->requestParam('code');
          
          try {
            $PetitionVerifications->verifyCode(
              $petition->id, 
              $cfg->enrollment_flow_step_id,
              $mail,
              $code
            );

            $this->llog('debug', "Successfully verified $mail");

            // On success we need to regenerate the verified address array.
            // We redirect back to ourself rather than rebuild all the logic we need.

            $url = [
              'plugin'      => 'CoreEnroller',
              'controller'  => 'email_verifiers',
              'action'      => 'dispatch',
              $cfg->id,
              '?' => [
                'op'          => 'index',
                'petition_id' => $petition->id
              ]
            ];
            
            $token = $this->injectToken($petition->id);

            if($token) {
              $url['?']['token'] = $token;
            }

            return $this->redirect($url);
          }
          catch(\Exception $e) {
            $this->llog('error', $e->getMessage());
            $this->Flash->error($e->getMessage());
          } 
        } else {
          // Generate a Verification request, then render a form to collect it.
          // If there is already a pending request, overwrite it (generate a new code).

          $this->EmailVerifiers->sendVerificationRequest($cfg, $petition, $mail);
        }

        // Tell dispatch.inc to render a verification form
        $this->set('vv_verify_address', $mail);
      }
    } elseif($op == 'finish') {
      if($minimumMet) {
        // We're done, set the Petition status to "Verified"

        $this->llog('debug', "Finished verifying email addresses");

        $Petitions = TableRegistry::getTableLocator()->get('Petitions');

        $petition->status = PetitionStatusEnum::Verified;

        $Petitions->saveOrFail($petition);

        // Redirect to the next step

        return $this->finishStep(
          enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
          petitionId:           $petition->id,
          comment:              __d('core_enroller', 
                                    'result.EmailVerifiers.verified', 
                                    [$doneCount, $totalCount, __d('controller', 'EmailAddresses', $doneCount)])
        );
      } else {
        $this->llog('error', "Finish attempted but minimum number of addresses not met");
        $this->Flash->error(__d('core_enroller', 'error.EmailVerifiers.minimum'));

        // Reset the op so the view renders correctly
        $this->set('vv_op', 'index');
      }
    }

    $this->render('/Standard/dispatch');
  }
}