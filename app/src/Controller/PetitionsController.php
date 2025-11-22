<?php
/**
 * COmanage Registry Petitions Controller
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
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use \App\Lib\Enum\EnrollmentActorEnum;
use \App\Lib\Enum\SuspendableStatusEnum;
use \App\Lib\Util\StringUtilities;

class PetitionsController extends StandardController {
  use \App\Lib\Traits\EnrollmentControllerTrait;

  protected array $paginate = [
    'order' => [
      'Petitions.modified' => 'desc'
    ]
  ];

  // Cached copy of the next step information
  private $nextStep = null;

  /**
   * Callback run prior to the request render.
   *
   * @param   EventInterface  $event  Cake Event
   *
   * @return Response|void
   * @since  COmanage Registry v5.1.0
   */

  public function beforeRender(EventInterface $event) {
    $link = $this->getPrimaryLink(true);

    if(!empty($link->value)) {
      $this->set('vv_bc_parent_obj', $this->Petitions->EnrollmentFlows->get($link->value));
      $this->set('vv_bc_parent_displayfield', $this->Petitions->EnrollmentFlows->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->Petitions->EnrollmentFlows->getPrimaryKey());
    }

    if($this->request->getParam('action') == 'view') {
      $id = $this->request->getParam('pass.0');

      if($id) {
        $this->set('vv_enrollee_name', $this->Petitions->getEnrolleeName((int)$id));
      }
      
      $this->set('vv_alternate_template', 'petition.inc');
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

    $authorized = false;

    if($action == 'continue') {
      // If we're called here it's because we have an authenticated user step.
      // (The token for unauthenticated users was validated by willHandleAuth()).
      
      $currentActor = $this->getCurrentActor(
        petitionId: $this->nextStep['petition']->id,
        stepId: $this->nextStep['step']->id
      );

      $authorized = in_array($this->nextStep['step']->actor_type, $currentActor['roles']);
    } elseif($action == 'finalize' && ($this->nextStep['finalize'] === true)) {
      // If we're using token auth, we checked the token in willHandleAuth(),
      // so all we really need to do here is compare the actor roles (including
      // for actors authenticated via the web server) against the role for the
      // last step.

      // willHandleAuth() already checked that we have a valid Petition ID, and
      // also set $this->nextStep. We need to specifically request the permissions
      // for the previous step to ensure Approver permissions are calculated properly.

      $currentActor = $this->getCurrentActor(
        petitionId: $this->nextStep['petition']->id,
        stepId: $this->nextStep['lastStep']->id
      );

      $authorized = in_array($this->nextStep['lastStep']->actor_type, $currentActor['roles']);
    }

    return $authorized;
  }

  /**
   * Continue a Petition (re-enter an Enrollment Flow).
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string   $id   Petition ID
   */

  public function continue(string $id) {
    return $this->transitionToStep((int)$id);
  }

  /**
   * Finalize a Petition.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string   $id   Petition ID
   */

  public function finalize(string $id) {
    // We split finalization up into several tasks, since we are constrained by browser and
    // web server timeouts, and each step relies on plugins that might or might not behave
    // as expected. We use an 'op' flag rather than separate actions in order to simplify
    // the authorization logic (which is already custom for finalize).

    // hydrate: Tell all plugins to hydrate (construct) the Person record
    // assign: Assign Identifiers (if any)
    // derive: Tell all plugins to perform any derivations from the Person record
    // provision: Run provisioning, then set petition status to Finalized

    $op = $this->requestParam('op');

    $baseUrl = [
      'controller'  => 'petitions',
      'action'      => 'finalize',
      $id
    ];

    $token = $this->injectToken((int)$id);

    if($token) {
      $baseUrl['?']['token'] = $token;
    }

    if(!$op) {
      $op = 'hydrate';
    }

    try {
      if($op == 'hydrate') {
        // Step 1
        $this->Petitions->hydrate((int)$id);

        // Next operation is assign
        $baseUrl['?']['op'] = 'assign';

        return $this->redirect($baseUrl);
      } elseif($op == 'assign') {
        // Step 2
        $this->Petitions->assignIdentifiers((int)$id);

        // Next operation is derive
        $baseUrl['?']['op'] = 'derive';

        return $this->redirect($baseUrl);
      } elseif($op == 'derive') {
        // Step 3
        $this->Petitions->derive((int)$id);

        // Next operation is provision
        $baseUrl['?']['op'] = 'provision';

        return $this->redirect($baseUrl);
      } elseif($op == 'provision') {
        // Step 4
        $this->Petitions->provision((int)$id);

        // We're really done now, update the Petition status and redirect appropriately
        // (This should be very fast and not require a separate page reload)
        $this->Petitions->finalize((int)$id);

        $this->Flash->success(__d('result', 'Petitions.finalized'));

        // We only use the Redirect on Finalize URL (if specified) on success,
        // since otherwise the Flash error won't render

        $petition = $this->Petitions->get((int)$id, contain: ['EnrollmentFlows']);

        if(!empty($petition->enrollment_flow->redirect_on_finalize)) {
          return $this->redirect($petition->enrollment_flow->redirect_on_finalize);
        }
      } else {
        // Unknown op, throw error

        throw new \InvalidArgumentException(__d('error', 'unknown', $op));
      }
    }
    catch(\OverflowException $e) {
      // Note we use the general Flow redirect on duplicate here, not any plugin specific
      // configuration (eg: EnvSource's duplicate redirect URL) since in theory any plugin
      // can trigger this.

      $petition = $this->Petitions->get((int)$id, contain: ['EnrollmentFlows']);

      // Redirect to configured URL or default location
      if(!empty($petition->enrollment_flow->redirect_on_duplicate)) {
        // Use the Enrollment Flow specific redirect URL
        return $this->redirect($petition->enrollment_flow->redirect_on_duplicate);
      } else {
        // Redirect to the default Duplicate Landing URL for this CO
        $coId = $this->getCOID();

        return $this->redirect("/$coId/duplicate-landing");
      }
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    // Redirect to the default Petition Complete landing page.

    $coId = $this->getCOID();

    return $this->redirect("/$coId/petition-complete");
  }

  /**
   * Redirect into a plugin to render the result of an Enrollment Flow Step.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string   $id   Petition ID
   */

  public function result(string $id) {
    try {
      $stepId = $this->getRequest()->getQuery('enrollment_flow_step_id');

      if(!$stepId) {
        throw new \InvalidArgumentException(__d('error', 'notprov', 'enrollment_flow_step_id'));
      }

      // Start by pulling the petition

      $petition = $this->Petitions->get((int)$id);
      
      // And the Step Result and Configuration

      $stepResult = $this->Petitions
                         ->PetitionStepResults
                         ->find()
                         ->where([
                           'PetitionStepResults.enrollment_flow_step_id' => $stepId,
                           'PetitionStepResults.petition_id' => $id
                         ])
                         ->contain(['EnrollmentFlowSteps' => $this->Petitions->PetitionStepResults->EnrollmentFlowSteps->getPluginRelations()])
                         ->firstOrFail();

      // Redirect to /registry-pe/plugin/controller/display/x?petition_id=y

      $pluginEntity = Inflector::singularize(Inflector::underscore(StringUtilities::pluginModel($stepResult->enrollment_flow_step->plugin)));

      return $this->redirect([
        'plugin'      => StringUtilities::pluginPlugin($stepResult->enrollment_flow_step->plugin),
        'controller'  => StringUtilities::pluginModel($stepResult->enrollment_flow_step->plugin),
        'action'      => 'display',
        $stepResult->enrollment_flow_step->$pluginEntity->id,
        '?' => [
          'petition_id' => $petition->id
        ]
      ]);
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
      return $this->generateRedirect(null);
    }
  }

  /**
   * Resume an Enrollment Flow.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string   $id   Petition ID
   */

  public function resume(string $id) {
    try {
      // First retrieve the petition
      $petition = $this->Petitions->get((int)$id);

      if($petition->isComplete()) {
        // A number of checks should prevent us from having to test for this,
        // but just in case...
        throw new \InvalidArgumentException(__d('error', 'Petitions.completed', [$id]));
      }

      $this->set('vv_petition', $petition);

      // We pull the Petition steps separately (instead of via contains) because
      // we want to get all Enrollment Steps to render them
      $steps = $this->Petitions->EnrollmentFlows->EnrollmentFlowSteps->find()
                    ->where(['EnrollmentFlowSteps.enrollment_flow_id' => $petition->enrollment_flow_id,
                             'EnrollmentFlowSteps.status' => SuspendableStatusEnum::Active])
                    ->contain(array_merge(
                        ['PetitionStepResults' => ['conditions' => ['PetitionStepResults.petition_id' => $petition->id]]],
                        $this->Petitions->EnrollmentFlows->EnrollmentFlowSteps->getPluginRelations()
                      ))
                    ->orderBy(['EnrollmentFlowSteps.ordr'])
                    ->all();
      
      $this->set('vv_steps', $steps);

      $urls = [];
      $nextStepId = null;

      if(!empty($steps)) {
        // We need to create dispatch URLs for each step _except_ anything after the
        // current one. (ie: the first one with no result is OK, but not after.)

        foreach($steps as $step) {
          $pluginModel = StringUtilities::pluginModel($step->plugin);
          $pluginName = Inflector::singularize(Inflector::underscore($pluginModel));

          $urls[ $step->id ] = [
            'plugin'      => StringUtilities::pluginPlugin($step->plugin),
            'controller'  => StringUtilities::pluginModel($step->plugin),
            'action'      => 'dispatch',
            $step->$pluginName->id,
            '?' => [
              'petition_id' => $petition->id
            ]
          ];

          // We might need to insert the token...

          if($petition->useToken($step->actor_type)) {
            $urls[ $step->id ]['?']['token'] = $petition->token;
          }

          if(!$nextStepId && empty($step->petition_step_results)) {
            // There is no result for this step, and we haven't found a step
            // without a result yet, so this is the next step

            $nextStepId = $step->id;
          }
        }
      }

      $this->set('vv_dispatch_urls', $urls);
      $this->set('vv_next_step_id', $nextStepId);
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
      return $this->generateRedirect(null);
    }
  }

  /**
   * Determine if MFA, if otherwise required, is not required for this action.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string   $action   Controller action
   * @return bool               true if MFA can be skipped, false otherwise
   */

  public function skipMfa(string $action): bool {
    if($action == 'dispatch') {
      // We allow unregistered identities (any actor type other than "person")
      // to skip MFA because they might not have MFA until they finish enrollment.
      // However, we want to enforce MFA for registered People who might (eg) be
      // approving a Petition. (There is a similar check in EnrollmentFlowsController::start().)
      // We call getActorInfo without a Petition ID because we only need the person type.

      $actorInfo = $this->getCurrentActor();

      if(!empty($actorInfo['type']) && $actorInfo['type'] != 'person') {
        // Perform the same check for Platform admins as PetitionsController.

        if(!in_array('cmpadmin', $actorInfo['roles'])) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Terminate an in-progress Petition.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string   $id   Petition ID
   */

  public function terminate(string $id) {
    try {
      $this->Petitions->terminate((int)$id);
      $this->Flash->success(__d('result', 'Petitions.terminated'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->generateRedirect(null);
  }

  /**
   * Indicate whether this Controller will handle some or all authnz.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  EventInterface   $event  Cake event, ie: from beforeFilter
   * @return string                   "no", "notauth", "open", "authz", or "yes"
   */

  public function willHandleAuth(\Cake\Event\EventInterface $event): string {
    $request = $this->getRequest();
    $action = $request->getParam('action');

    // We take over authz for continue (which is really just a glorified redirect,
    // but which will send handoff emails under certain circumstances); and for
    // finalize but only if the request will be authenticated via Petition Token.

    $petitionId = (int)$this->request->getParam('pass.0');

    if(!in_array($action, ['continue', 'finalize'])) {
      return 'no';
    }
    
    if(empty($petitionId)) {
      $this->llog('error', "No Petition ID specified for finalize");
      return 'notauth';
    }

    if($action == 'continue') {
      // We can't reliably determine the actor type yet since we haven't necessarily
      // run authentication. In particular, a new enrollee clicking a continue link
      // sent out of band and an approver clicking a continue link sent out of band
      // will look the same to us most likely (unless the approver happend to login
      // already).
      
      // We are basically just issuing a redirect here (the target of which will
      // implement authnz anyway), but there is a small bit of information leakage
      // possible by introspecting the redirect target, so we try to make sure we at
      // least have some sort of authenticated user.

      $this->nextStep = $this->Petitions->EnrollmentFlows->calculateNextStep($petitionId);

      if($this->nextStep['petition']->useToken($this->nextStep['step']->actor_type)) {
        // A token is required, so make sure we have one and that it validates

        $actorInfo = $this->getCurrentActor($petitionId);

        if($actorInfo['token_ok']) {
          // We can return 'yes' without calling calculatePermission since we're
          // just issuing a redirect, and presumably the person with the token already
          // more or less knows the state of the Petition.

          return 'yes';
        } else {
          $this->llog('trace', "Token validation failed for Petition " . $petitionId);
          return 'notauth';
        }
      } else {
        // No token is needed, but we do need authentication to run.

        return 'authz';
      }
    } elseif($action == 'finalize') {
      // For finalize, the relevant Step is the last one. We'll use calculateNextStep()
      // to get the last Step, which will also check if the petition is already completed.
      $this->nextStep = $this->Petitions->EnrollmentFlows->calculateNextStep($petitionId);

      if(!$this->nextStep['finalize']) {
        // Petition is not ready for finalization
        $this->llog('trace', "Petition " . $petitionId . " is not ready for finalization");
        return 'notauth';
      }

      if($this->nextStep['petition']->useToken($this->nextStep['lastStep']->actor_type)) {
        // A token is required

        $tokenRoles = $this->validateToken($this->nextStep['petition']);
        
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
    }

    return 'no';
  }
}