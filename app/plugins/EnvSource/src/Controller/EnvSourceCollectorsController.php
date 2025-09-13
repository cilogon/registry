<?php
/**
 * COmanage Registry Env Source Collectors Controller
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

namespace EnvSource\Controller;

use App\Controller\StandardEnrollerController;
use App\Lib\Enum\PetitionActionEnum;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;

class EnvSourceCollectorsController extends StandardEnrollerController {
  protected array $paginate = [
    'order' => [
      'EnvSourceCollectors.id' => 'asc'
    ]
  ];

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
      $this->set('vv_bc_parent_obj', $this->EnvSourceCollectors->EnrollmentFlowSteps->get($link->value));
      $this->set('vv_bc_parent_displayfield', $this->EnvSourceCollectors->EnrollmentFlowSteps->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->EnvSourceCollectors->EnrollmentFlowSteps->getPrimaryKey());
    }

    return parent::beforeRender($event);
  }

  /**
   * Dispatch an Enrollment Flow Step.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string  $id    Env Source Collector ID
   */

  public function dispatch(string $id) {
    $petition = $this->getPetition();

    // The $link also has the Enrollment Flow Step ID
    // $link = $this->getPrimaryLink(true);

    // Pull our configuration

    $envSource = $this->EnvSourceCollectors->get((int)$id, contain: ['ExternalIdentitySources' => 'EnvSources']);

    try {
      $vars = $this->EnvSourceCollectors->parse($envSource->external_identity_source->env_source);

      $this->set('vv_env_source_vars', $vars);

      if($this->request->is(['post', 'put'])) {
        // We'll upsert the collected attributes. Generally this should always be an insert,
        // but we could imagine a scenario where an admin reruns the step to change the
        // collected identity. Or maybe if the enrollee just hits the back button.

        $this->EnvSourceCollectors->upsert(
          id:         (int)$id,
          petitionId: $petition->id,
          attributes: $vars
        );

        // On success, indicate the step is completed and generate a redirect
        // to the next step

        return $this->finishStep(
          enrollmentFlowStepId: $envSource->enrollment_flow_step_id,
          petitionId:           $petition->id,
          comment:              __d('env_source', 'result.env.saved')
        );
      }
    }
    catch(\OverflowException $e) {
      // The requested Source Key is already attached to an External Identity, so we throw
      // an error now rather than wait until finalization

      // Flag the Petition as a duplicate
      $Petitions = TableRegistry::getTableLocator()->get("Petitions");

      // The exception from upsert will have a bit more detail than the generic
      // flagDuplicate() message, so we'll stuff that into the Petition History
      $Petitions->PetitionHistoryRecords->record(
        petitionId:           $petition->id,
        enrollmentFlowStepId: $envSource->enrollment_flow_step_id,
        action:               PetitionActionEnum::FlaggedDuplicate,
        comment:              $e->getMessage()
      );

      $Petitions->flagDuplicate($petition->id, $envSource->enrollment_flow_step_id);

      // Redirect to configured URL or default location
      if(!empty($envSource->external_identity_source->env_source->redirect_on_duplicate)) {
        // Use the EnvSource specific redirect URL
        return $this->redirect($envSource->external_identity_source->env_source->redirect_on_duplicate);
      } else {
        // Redirect to the default Duplicate Landing URL for this CO
        $coId = $envSource->external_identity_source->co_id;

        return $this->redirect("/$coId/duplicate-landing");
      }
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    // Fall through and let the form render

    $this->render('/Standard/dispatch');
  }

  /**
   * Indicate whether this Controller will handle some or all authnz.
   *
   * @since  COmanage Registry v5.1.0
   * @param  EventInterface   $event  Cake event, ie: from beforeFilter
   * @return string                   "no", "open", "authz", or "yes"
   */

  public function willHandleAuth(\Cake\Event\EventInterface $event): string {
    $request = $this->getRequest();
    $action = $request->getParam('action');

    if($action == 'dispatch') {
      // We need to perform special logic (vs StandardEnrollerController)
      // to ensure that web server authentication is triggered.
      // (This is the same logic as IdentifierCollectorsController.)
// XXX We could maybe move this into StandardEnrollerController with a flag like
// $this->alwaysAuthDispatch(true);

      // To start, we trigger the parent logic. This will return
      //  notauth: Some error occurred, we don't want to override this
      //  authz: No token in use
      //  yes: Token validated

      $auth = parent::willHandleAuth($event);

      // The only status we need to override is 'yes', since we always want authentication
      // to run in order to be able to grab $REMOTE_USER.

      return ($auth == 'yes' ? 'authz' : $auth);
    }

    return parent::willHandleAuth($event);
  }
}
