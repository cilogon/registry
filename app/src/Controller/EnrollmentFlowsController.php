<?php
/**
 * COmanage Registry Enrollment Flows Controller
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
use \App\Lib\Enum\EnrollmentAuthzEnum;

class EnrollmentFlowsController extends StandardController {
  use \App\Lib\Traits\EnrollmentControllerTrait;

  public $paginate = [
    'order' => [
      'EnrollmentFlows.name' => 'asc'
    ]
  ];

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

    // We should only get called for 'start', based on willHandleAuth(), below.
    if($action == 'start') {
      $actorInfo = $this->getCurrentActor();

      // We need to pull the config to get the Petitioner Authorization mode
      $params = $this->request->getParam('pass');

      if(empty($params[0])) {
        throw new \InvalidArgumentException(__d('error', 'notprov', 'enrollment_flow_id'));
      }

      $flow = $this->EnrollmentFlows->get($params[0]);

      switch($flow->authz_type) {
        case EnrollmentAuthzEnum::AuthUser:
          $authorized = !empty($actorInfo['identifier']);
          break;
        case EnrollmentAuthzEnum::CoAdmin:
          $authorized = $this->RegistryAuth->isCoAdmin($flow->co_id);
          break;
// CFM-31 implement these (and enable in EnrollmentAuthzEnum, and possibly start() below
//        and fields.inc)
/*
        case EnrollmentAuthzEnum::CoOrCouAdmin:
// XXX
          break;
        case EnrollmentAuthzEnum::CouAdmin:
// XXX
          break;
        case EnrollmentAuthzEnum::CouPerson:
// XXX
          break;
        case EnrollmentAuthzEnum::GroupMember:
// XXX
          break;
        case EnrollmentAuthzEnum::Person:
// XXX
          break;
        case EnrollmentAuthzEnum::None:
// XXX willHandleAuth needs to check for this mode and then return 'open' if set
          $authorized = true;
          break;*/
      }
    }

    return $authorized;
  }

  /**
   * Copy an Enrollment Flow.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string   $id   Enrollment Flow ID
   */

  public function copy(string $id) {
    try {
      $related = [
        'EnrollmentFlowSteps' => $this->EnrollmentFlows->EnrollmentFlowSteps->getPluginRelations()
      ];

      $obj = $this->EnrollmentFlows->copy((int)$id, $related);
      $this->Flash->success(__d('result', 'copied'));

      // Redirect to the newly created flow
      return $this->generateRedirect($obj);
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->generateRedirect(null);
  }

  /**
   * Determine if MFA, if otherwise required, is not required for this action.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string   $action   Controller action
   * @return bool               true if MFA can be skipped, false otherwise
   */

  public function skipMfa(string $action): bool {
    if($action == 'start') {
      // We allow unregistered identities (any actor type other than "person")
      // to skip MFA because they might not have MFA until they finish enrollment.
      // (This is similar to PetitionsController::dispatch().) Note that since this
      // is the 'start' call we don't yet have a Petition ID.

      $actorInfo = $this->getCurrentActor();

      if(!empty($actorInfo['type']) && $actorInfo['type'] != 'person') {
        // This implies that someone who is registered on the platform but NOT enrolled
        // in the current CO (including, potentially, Platform Admins, will be exempt from MFA).
        // This is probably ok since these people presumably don't have any authorization,
        // and if anything are trying to run an enrollment. One exception is Platform Admins,
        // so we check for that specifically.

        if(!in_array('cmpadmin', $actorInfo['roles'])) {
          return true;
        }
      }
    }
    
    return false;
  }

  /**
   * Start an Enrollment Flow.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string   $id   Enrollment Flow ID
   */

  public function start(string $id) {
    $flow = $this->EnrollmentFlows->get((int)$id);

// XXX Is this an AR?
    // By default, the Petitioner is the Enrollee if the Flow Authorization is not some
    // sort of Admin. (This can be changed by a Plugin later, if appropriate.)

    $isEnrollee = in_array($flow->authz_type, [
      EnrollmentAuthzEnum::AuthUser,
// XXX not yet implemented
      // EnrollmentAuthzEnum::CouPerson,
      // EnrollmentAuthzEnum::GroupMember,
      // EnrollmentAuthzEnum::Person,
      // EnrollmentAuthzEnum::None
    ]);

    $actor = $this->getCurrentActor();

    if($this->request->is(['post', 'put'])) {
      // We should now have an enrollee email, so we can create the Pettion.
      // Saving the entity should syntactically validate the email address.

      $petition = $this->EnrollmentFlows->Petitions->start(
        enrollmentFlowId:       (int)$id, 
        petitionerIdentifier:   $actor['identifier'],
        petitionerPersonId:     $actor['person_id'],
        isEnrollee:             $isEnrollee,
        enrolleeEmail:          $this->request->getData('enrollee_email')
      );
      
      // No form to render, simply redirect to the next (ie: first) step
      return $this->transitionToStep(petitionId: $petition->id, start: true);
    } else {
      if(isset($flow->collect_enrollee_email) && $flow->collect_enrollee_email) {
        // We need to render a form, so we'll delay creating the petition
        // until we come back from the form. Since there's no petition there's
        // no meaningful information to pass through and back.
        
        // Get the title
        // XXX We should have a "Title" for end-users that is different from the Enrollment Flow "Name"
        //     for start and dispatch.
        $this->set('vv_title', $flow->name);
        
      } else {
        // No form, so just allocate a new Petition and set appropriate metadata

        $petition = $this->EnrollmentFlows->Petitions->start(
          enrollmentFlowId:       (int)$id, 
          petitionerIdentifier:   $actor['identifier'],
          petitionerPersonId:     $actor['person_id'],
          isEnrollee:             $isEnrollee
        );
        
        // No form to render, simply redirect to the next (ie: first) step
        return $this->transitionToStep(petitionId: $petition->id, start: true);
      }
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
    
    // We only need to take over authz for start
    return ($action == 'start') ? 'authz' : 'no';
  }
}