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

use App\Controller\StandardEnrollerController;
use App\Lib\Enum\ApplicationStateEnum;
use App\Lib\Enum\PetitionStatusEnum;
use App\Lib\Traits\ApplicationStatesTrait;
use App\Lib\Util\StringUtilities;
use Cake\Http\Exception\BadRequestException;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\AllTernaryEnum;
use \App\Lib\Enum\HttpStatusCodesEnum;

class EmailVerifiersController extends StandardEnrollerController {
  use ApplicationStatesTrait;

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
    
    // We use the viewvar to determine the op since 'index' isn't always present
    $op = $this->viewBuilder()->getVar('vv_op');

    if($op == "verify" || $op == "index") {
      // This will suppress the default behavior. By default, we print the submit button in the
      // unorderedList.php element. But for the verify view we want to override and customize
      $this->set('suppress_submit', true);
    }

    return parent::beforeRender($event);
  }

  /**
   * Resend the email verification request.
   *
   * @param string $id Email Verifier ID
   * @throws BadRequestException If the request is not AJAX
   * @throws \InvalidArgumentException If required query parameters are missing
   * @return void
   * @since COmanage Registry v5.1.0
   */
  
  public function resend($id) {
    $this->viewBuilder()->setClassName('Json');

    if (!$this->getRequest()->is('ajax')) {
      throw new BadRequestException(__('Bad Request'));
    }

    if (!$this->getRequest()->getQuery('petition_id') || !$this->getRequest()->getQuery('m')) {
      throw new \InvalidArgumentException(__('error', 'invalid.request'));
    }

    // Generate a Verification request and send it
    $Petitions = TableRegistry::getTableLocator()->get('Petitions');
    $petition = $Petitions->get($this->getRequest()->getQuery('petition_id'));
    $cfg = $this->EmailVerifiers->get($id);
    $mail = StringUtilities::urlbase64decode($this->requestParam('m'));
    $status = $this->EmailVerifiers->sendVerificationRequest($cfg, $petition, $mail, true);

    if ($status) {
      return $this->response
        ->withType('application/json')
        ->withStatus(HttpStatusCodesEnum::HTTP_OK)
        ->withStringBody(json_encode(['status' => 'ok']));
    }


    return $this->response
      ->withType('application/json')
      ->withStatus(HttpStatusCodesEnum::HTTP_INTERNAL_SERVER_ERROR)
      ->withStringBody(json_encode(['status' => 'failed']));
  }

  /**
   * Dispatch an Enrollment Flow Step.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string  $id    Email Verifier ID
   */

  public function dispatch(string $id) {
    $request = $this->getRequest();
    $session = $request->getSession();
    $username = $session->read('Auth.external.user');

    $op = $this->requestParam('op');
    
    if(!$op) {
      $op = 'index';
    }

    $this->set('vv_op', $op);

    $petition = $this->getPetition();

    $cfg = $this->EmailVerifiers->get($id);

    $candidateAddresses = $this->EmailVerifiers->assembleVerifiableAddresses($cfg, $petition);

    $this->set('vv_config', $cfg);
    $this->set('controller', $this);
    $this->set('vv_email_addresses', $candidateAddresses);

    // To make things easier for the view, we'll create a separate view var with the
    // addresses that have actually been verified.

    $verifiedAddresses = [];

    foreach($candidateAddresses as $a => $v) {
      // true indicates verified by the plugin that collected the address
      if($v === true || !empty($v->verification->verification_time)) {
        $verifiedAddresses[$a] = true;
      }
    }

    $this->set('vv_verified_addresses', $verifiedAddresses);

    // And perform some calculations
    $doneCount = count($verifiedAddresses);
    $totalCount = count($candidateAddresses);
    $allDone = $doneCount == $totalCount;
    $minimumMet = $cfg->mode == AllTernaryEnum::None
                  || ($cfg->mode == AllTernaryEnum::One
                      && $doneCount > 0)
                  || ($cfg->mode == AllTernaryEnum::All
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
        $PetitionVerifications = TableRegistry::getTableLocator()->get('CoreEnroller.PetitionVerifications');
        $pVerification = $PetitionVerifications->getPetitionVerification($petition->id, $mail, false);
        // Reset the counter if nothing happened for the last 30 minutes
        if (!empty($pVerification->modified) && !$pVerification->modified->wasWithinLast('30 minute')) {
          $pVerification->attempts_count = 0;
          $PetitionVerifications->save($pVerification);
        }

        // Tell dispatch.inc to render a verification form
        $this->set('vv_verify_address', $mail);
        $this->set('vv_attempts_count', $pVerification->attempts_count ?? 0);

        if($this->request->is('post')) {

          // We're back with the code. Note many parameters (but not code) will be in
          // both the URL and the post body because of how dispatch.php sets up
          // FormHelper.

          $code = $this->requestParam('code');
          // Strip any dashes from the code
          $code = str_replace('-', '', $code);
          
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

            if ($e->getMessage() === __d('error', 'Verifications.code')) {
              // Add a flag to the session to instruct the UI to handle blocking.
              $this->request->getSession()->write('verification_error', 1);
              // Get preferences if we have an Auth.User.co_person_id
              if(!empty($username)) {
                $ApplicationStates = $this->fetchTable('ApplicationStates');
                $columnStatement = $this->viewBuilder()->getVar('vv_person_id') === null ? 'person_id IS'  : 'person_id';
                $data = [
                  'tag' => ApplicationStateEnum::VerifyEmailBlocked,
                  'username' => $username,
                  'co_id' => $this->getCOID(),
                  $columnStatement => $this->viewBuilder()->getVar('vv_person_id') ?? null
                ];
                $ApplicationStates->createOrUpdate($data, 'lock');
              }
            }
          }
        } else {
          // Generate a Verification request, then render a form to collect it.
          // If there is already a pending request, overwrite it (generate a new code).

          $this->EmailVerifiers->sendVerificationRequest($cfg, $petition, $mail);
        }
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