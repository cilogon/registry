<?php
/**
 * COmanage Registry Standard Enroller Plugin Controller
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Controller;

// XXX not doing anything with Log yet
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\EnrollmentActorEnum;
use \App\Lib\Util\StringUtilities;

class StandardEnrollerController extends StandardPluginController {
  use \App\Lib\Traits\EnrollmentControllerTrait;

  // We cache the $petition, but flag it as private to force plugins
  // (which might be written by third parties) to use the interfaces.
  private $petition = null;

  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.1.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */

  public function beforeRender(\Cake\Event\EventInterface $event) {
    $Petition = TableRegistry::getTableLocator()->get('Petitions');

    // Make the Petition available to the view. Note there may not be a Petition,
    // eg if we're editing the plugin's configuration.

    if(!empty($this->petition->id)) {
      $this->set(
        'vv_petition',
        $Petition->findById($this->petition->id)
          // We need to include the Enrollment Flow of the Petition.
          // The least, we can get if the co id which cannot be calculated
          // for unauthenticated use cases.
          ->contain(['EnrollmentFlows'])
          ->firstOrFail()
      );
    } else {
      $this->set('vv_petition', null);
    }

    return parent::beforeRender($event);
  }

  /**
   * Calculate authorization for the current request.
   * 
   * @since  COmanage Registry v5.1.0
   * @return bool     True if the current request is permitted, false otherwise
   */

  public function calculatePermission(): bool {
    $request = $this->getRequest();
    $action = $request->getParam('action');

    // We currently support $actions of 'dispatch' and 'display'

    $petitionId = $this->requestParam('petition_id');

    if(!$petitionId) {
      $this->llog('error', "petition_id not found in request");
      return false;
    }

    $actorInfo = $this->getCurrentActor((int)$petitionId);
    $this->petition = $actorInfo['petition'];

    // We only accept anonymous requests for 'dispatch', and only if the token matches.
    // We'll further check authorization below.
    if($actorInfo['type'] == 'anonymous') {
      if($action != 'dispatch') {
        $this->llog('trace', "Rejecting anonymous access to unsupported enroller action for petition " . $petitionId);
        return false;
      }

      // We do the token check here rather than in willHandleAuth() because
      // we don't know in willHandleAuth which auth metchanism is in use yet.

      if(!isset($actorInfo['token_ok']) || !$actorInfo['token_ok']) {
        $this->llog('trace', "Rejecting incorrect token for access to petition " . $petitionId);
        return false;
      }
    }

    if($action == 'dispatch') {
      // We already validated the petition state in willHandleAuth

      $modelsName = $this->name;
      $modelId = $this->request->getParam('pass.0'); // XXX check if empty

      if(!$modelId) {
        $this->llog('error', "Model ID missing from request");
        return false;
      }
      
      $stepConfig = $this->$modelsName->get($modelId, ['contain' => ['EnrollmentFlowSteps' => ['EnrollmentFlows']]]);
      $this->set('vv_step_config', $stepConfig);
      $this->set('vv_title', $stepConfig['enrollment_flow_step']['enrollment_flow']['name']);

      // Check that the current actor has the role required for this step.
      // Note that role validation has already been performed for anonymous access
      // via tokens (via getcurrentActor) so we don't have to recheck that here.

      if(in_array($stepConfig->enrollment_flow_step->actor_type,
                  $actorInfo['roles'])) {
        $this->llog('trace', "Authorizing access to petition " . $petitionId . " step " . $stepConfig->enrollment_flow_step_id);
        return true;
      }
    } elseif($action == 'display') {
// XXX need to replace this with better logic
      return true;
    }

    $this->llog('trace', "Rejecting unauthorized access to petition " . $petitionId . " step " . $stepConfig->enrollment_flow_step_id);
    return false;
  }

  /**
   * Record a result for the Enrollment Step and redirect to the next Step.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $enrollmentFlowStepId Enrollment Flow Step Id
   * @param  int    $petitionId           Petition ID
   * @param  string $status               PetitionStatusEnum
   * @param  string $comment              Comment
   * @return \Cake\Http\Response          Redirect to next step
   */

  protected function finishStep(
    int     $enrollmentFlowStepId,
    int     $petitionId,
    // string  $status,
    string  $comment
  ): \Cake\Http\Response {
    $PetitionStepResults = TableRegistry::getTableLocator()->get('PetitionStepResults');

    $PetitionStepResults->record(
      enrollmentFlowStepId: $enrollmentFlowStepId,
      petitionId:           $petitionId,
      // status:               $status,
      comment:              $comment
    );
    
    $EnrollmentFlows = TableRegistry::getTableLocator()->get('EnrollmentFlows');

    return $this->transitionToStep(petitionId: $petitionId);
  }

  /**
   * Obtain the Petition artifact associated with this request.
   * 
   * @since  COmanage Registry v5.1.0
   * @return Petition       Petition artifact
   */

  public function getPetition(): ?\App\Model\Entity\Petition {
    return $this->petition;
  }

  /**
   * Indicate whether this Controller will handle some or all authnz.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  EventInterface   $event  Cake event, ie: from beforeFilter
   * @return string                   "no", "open", "authz", "yes", or "notauth"
   */

  public function willHandleAuth(\Cake\Event\EventInterface $event): string {
    $request = $this->getRequest();
    $action = $request->getParam('action');

    if($action == 'dispatch') {
      $petitionId = (int)$this->requestParam('petition_id');

      if(empty($petitionId)) {
        $this->llog('error', "No Petition ID specified for dispatch");
        return 'notauth';
      }

      // Determine if we're going to use a token to authenticate the current request.
      // For this, we need the current step's authorization.

      // $this->name = Models (ie: from ModelsTable)
      $modelsName = $this->name;
      $modelId = $this->request->getParam('pass.0');

      if(empty($modelId)) {
        $this->llog('error', "No step ID specified for dispatch");
        return 'notauth';
      }

      $stepConfig = $this->$modelsName->get($modelId, ['contain' => 'EnrollmentFlowSteps']);

      // Determine if the requested step is past the current/next step.
      // We don't allow steps that haven't run yet to be run out of order.

      $EnrollmentFlows = TableRegistry::getTableLocator()->get('EnrollmentFlows');

      // "next" means "uncompleted step with the lowest ordr value".
      // calculateNextStep() will also throw an error if the Petition is complete.
      $nextStep = $EnrollmentFlows->calculateNextStep($petitionId);

      if(!empty($nextStep['step']->id)) {
        if($stepConfig->enrollment_flow_step->ordr > $nextStep['step']->ordr) {
          $this->llog('trace', "Requested step " . $stepConfig->enrollment_flow_step->enrollment_flow_id . " for petition " . $petitionId . " has not yet been reached");
          return 'notauth';
        }
      }

      $petition = $nextStep['petition'];

      if($petition->enrollment_flow_id
         != $stepConfig->enrollment_flow_step->enrollment_flow_id) {
        // Mismatch between Petition Enrollment Flow and requested Step's Enrollment Flow
        $this->llog('trace', "Requested step " . $stepConfig->enrollment_flow_step->enrollment_flow_id . " and requested petition " . $petitionId . " are not associated with the same Enrollment Flow");
        return 'notauth';
      }

      if($petition->useToken($stepConfig->enrollment_flow_step->actor_type)) {
        // A token is required

        $tokenRoles = $this->validateToken($petition);
        
        if(!$tokenRoles) {
          // Token validation failed
          $this->llog('trace', "Token validation failed for Petition " . $petitionId);
          return 'notauth';
        }

        // If we have a valid token, we need to call calculatePermission now
        // since RegistryAuthComponent won't (when we return 'yes').

        return $this->calculatePermission() ? 'yes' : 'notauth';
      }

      // Token not in use, we'll just handle authz

      return 'authz';
    } elseif($action == 'display') {
      return 'authz';
    }

    return 'no';
  }
}