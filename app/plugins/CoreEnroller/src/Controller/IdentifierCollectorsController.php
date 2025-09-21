<?php
/**
 * COmanage Registry Identifier Collectors Controller
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

class IdentifierCollectorsController extends StandardEnrollerController {
  protected array $paginate = [
    'order' => [
      'IdentifierCollectors.id' => 'asc'
    ]
  ];

  /**
   * Dispatch an Enrollment Flow Step.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string  $id    Invitation Accepter ID
   */

  public function dispatch(string $id) {
    $petition = $this->getPetition();

    $cfg = $this->IdentifierCollectors->get($id);

    // Because we're not in the "protected" web server application space, we need to read
    // the username from the session (as set via TrafficController) rather than via getenv().
    // This also precludes us from using a variable other than $REMOTE_USER.

    $request = $this->getRequest();
    $session = $request->getSession();
    $username = $session->read('Auth.external.user');

    $PetitionIdentifiers = TableRegistry::getTableLocator()->get('CoreEnroller.PetitionIdentifiers');

    try {
      $PetitionIdentifiers->record($petition->id, $cfg->enrollment_flow_step_id, $username);

      return $this->finishStep(
        enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
        petitionId:           $petition->id,
        comment:               __d('core_enroller', 'result.IdentifierCollector.collected', [$username])
      );
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }
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
      // (This logic is also used in EnvSourceCollectorsController.)

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