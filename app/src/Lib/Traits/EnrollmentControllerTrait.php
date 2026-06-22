<?php
/**
 * COmanage Registry Enrollment Controller Trait
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

namespace App\Lib\Traits;

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\EnrollmentActorEnum;
use \App\Lib\Enum\PetitionStatusEnum;
use \App\Lib\Util\DeliveryUtilities;
use \App\Lib\Util\StringUtilities;
use \App\Model\Entity\Petition;

trait EnrollmentControllerTrait {
  protected $cache = [];

  /**
   * Determine information about the current actor.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $petitionId   Petition ID (if null, no role information is retrieved)
   * @param  int    $stepId       Step ID (if null, no approver information is retrieved)
   * @return array                Array of actor information
   */

  protected function getCurrentActor(?int $petitionId=null, ?int $stepId=null): array {
    // We only check the cache if we have a Petition ID, see below. We also track
    // the stepId since we might get different results for different Steps (in particular
    // for Approvers).

    if($petitionId && !empty($this->cache['actor'][$stepId ?? "null"])) {
      return $this->cache['actor'][$stepId ?? "null"];
    }

    $ret = [
      'type' => 'anonymous',
      'person_id' => null,
      'identifier' => $this->RegistryAuth->getAuthenticatedUser(),
      'token_ok' => false,
      'roles' => [],
      'petition' => null
    ];

    if(empty($ret['identifier'])) {
      // Under certain circumstances (eg: EnvSource::dispatch) we may run before
      // RegistryAuth::beforeFilter, in which case getAuthenticatedUser() won't have
      // an authenticated user yet. As a workaround, we manually read the session to see
      // if an authenticated identifier has been set.

      $request = $this->getRequest();
      $session = $request->getSession();
      
      $ret['identifier'] = $session->read('Auth.external.user');
    }

    if(!empty($ret['identifier'])) {
      // Can we map this identifier to a Person ID?

      $Identifiers = TableRegistry::getTableLocator()->get('Identifiers');

      try {
        $ret['person_id'] = $Identifiers->lookupPersonByLogin($this->getCOID(), $ret['identifier']);

        // We also store platform administator as a role to faciliate superuser access.
        // (We don't currently recognize CO Admins the same way pending further requirements.)
      } catch(RecordNotFoundException $e) {
        $ret['person_id'] = null;
      }

      if(!empty($ret['person_id'])) {
        $ret['type'] = 'person';
      } else {
        $ret['type'] = 'identifier';
      }

      if($this->RegistryAuth->isPlatformAdmin()) {
        // We don't define an Enum for this because we currently don't support
        // configuring a Step to be runnable only by a Platform Administrator.
        // (Note a Platform Admin will have a valid identifier, but may not have
        // a person_id if they're not also registered in the current CO.)
        $ret['roles'][] = 'cmpadmin';
      }
    }

    if($petitionId) {
      // Pull the Petition to figure out what roles the person has

      $Petitions = TableRegistry::getTableLocator()->get('Petitions');

      $petition = $Petitions->get($petitionId);

      if($ret['type'] == 'person' && !empty($ret['person_id'])) {
        // A person can be both Petitioner and Enrollee, so check both

        if($ret['person_id'] === $petition->petitioner_person_id) {
          $ret['roles'][] = EnrollmentActorEnum::Petitioner;
        }
        
        if($ret['person_id'] === $petition->enrollee_person_id) {
          $ret['roles'][] = EnrollmentActorEnum::Enrollee;
        }

        // Only People (as opposed to authenticated, unregistered Enrollees)
        // can be Approvers, so check here. We only check for the current Step
        // because different Steps can have different Approvers (and we don't
        // currently have a use case for "Any Approver for the entire Petition").

        if($stepId) {
          if($Petitions->isApprover(id: $petitionId, stepId: $stepId, personId: $ret['person_id'])) {
            $ret['roles'][] = EnrollmentActorEnum::Approver;
          }
        }
      } elseif($ret['type'] == 'identifier' && !empty($ret['identifier'])) {
        if($ret['identifier'] === $petition->petitioner_identifier) {
          $ret['roles'][] = EnrollmentActorEnum::Petitioner;
        }

        if($ret['identifier'] === $petition->enrollee_identifier) {
          $ret['roles'][] = EnrollmentActorEnum::Enrollee;
        }

        if(//empty($petition->petitioner_identifier) &&
           //in_array(EnrollmentActorEnum::Enrollee, $ret['roles'])
           empty($petition->enrollee_identifier)) {
          // We have an identifier at run time but none in the petition.
          // If we can validate a token we can store the identifier and 
          // use it instead. (eg: An Enrollee receives an initial handoff
          // email/invitation, or an Enrollee is asked to authenticate.)

          // Note in general we should only accept an Enrollee identifier
          // this way. Petitioner identifiers should be collected at Petition
          // start, and Approvers shouldn't use tokens.

          $tokenRoles = $this->validateToken($petition);

          if(!empty($tokenRoles) && in_array(EnrollmentActorEnum::Enrollee, $tokenRoles)) {
            $this->llog('trace', "Transitioning Enrollee to authenticated identifier " 
                                 . $ret['identifier'] . " for Petition " . $petition->id);

            // Update the Petition to store the identifier and remove the token
            $petition->enrollee_identifier = $ret['identifier'];
            $petition->token = null;

// XXX Also add petition history?
            $Petitions->saveOrFail($petition);

            $ret['roles'][] = EnrollmentActorEnum::Enrollee;
          }
        }
      } elseif($ret['type'] == 'anonymous') {
        $ret['roles'] = $this->validateToken($petition);

        if($ret['roles'] !== false) {
          $ret['token_ok'] = true;

          // Tell the form generator (dispatch.php) to use the token
          $this->set('vv_token_ok', true);
        }
      }

      // XXX need to add checks for Approver somehow; a Petitioner can also be an Approver
      // though probably not commonly

      // Since we have the Petition entity, make it easier for other functions
      // to access it
      $ret['petition'] = $petition;
    }

    if($petitionId) {
      // If we have a Petition ID we cache the info. We don't cache without one
      // since that is how the EnrollmentFlowsControlller::start() calls us, and
      // no role data is available at that point.

      $this->cache['actor'] = $ret;
    }

    $this->llog('trace', (!empty($ret['petition']->id) ? "Petition " . $ret['petition']->id : "New Petition")
                         . " current actor: type=" . $ret['type']
                         . ", personid=" . $ret['person_id'] 
                         . ", identifier=" . $ret['identifier']
                         . ", token=" . $ret['token_ok']);

    return $ret;
  }

  /**
   * Determine whether or not a token should be injected into URLs created by Enroller plugins.
   * 
   * For normal use cases, tranisitionToStep() and dispatch.php will handle token management,
   * but if Enroller plugins need to create custom flows for unregistered enrollees, this call
   * will determine if a token needs to be injected into the URL.
   * 
   * @since  COmanage Registry v5.1.0
   * @return string       Token to insert, or false if no token is required
   */

  protected function injectToken(int $petitionId): string|false {
    $actor = $this->getCurrentActor($petitionId);

    if($actor['token_ok']) {
      return $actor['petition']->token;
    }

    return false;
  }

  /**
   * Transition to an Enrollment Flow. $petitionId should be finalized before calling
   * this function, which will register a new Petition associated with $enrollmentFlowId
   * and copy appropriate metadata.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  int  $petitionId       Petition ID
   * @param  int  $enrollmentFlowId Enrollment Flow ID
   * @return Cake\Http\Response     Redirect to new Enrollment Flow or landing page
   */

  protected function transitionToFlow(int $petitionId, int $enrollmentFlowId) {
    // We ignore the Petitioner Autherization for $enrollmentFlowId, and also skip the
    // start step, so what actually matters is the Actor Type for the first Enrollment
    // Flow Step in the new flow, which will substantially be handled by
    // transitionToStep(), below.

    $Petitions = TableRegistry::getTableLocator()->get('Petitions');

    $newPetition = $Petitions->startFromPetition($petitionId, $enrollmentFlowId);

    // getCurrentActor() should basically do the right thing in transitionToStep()
    // since it won't have any cached data for $newPetitionId and so will reconstruct
    // authz based on the current user.

    return $this->transitionToStep(petitionId: $newPetition->id, start: true);
  }

  /**
   * Transition to an Enrollment Flow Step. Typically this will be the next step,
   * but this also permits re-entering a flow.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $petitionId Petition ID
   * @param  bool $start      True if transitioning from start
   * @throws Cake\Network\Exception\SocketException   On SMTP error
   * @throws RuntimeException                         If no SMTP server configured
   */

  protected function transitionToStep(int $petitionId, bool $start=false) {
    $EnrollmentFlows = TableRegistry::getTableLocator()->get('EnrollmentFlows');

    $stepInfo = $EnrollmentFlows->calculateNextStep($petitionId);
    $petition = $stepInfo['petition'];
    $flow = $EnrollmentFlows->get($petition->enrollment_flow_id);
    $coId = $flow->co_id;

/* no need to to this, we don't cache on start()
    if($start) {
      // $actorInfo was cached before the Petition was created, so force it to reload
      unset($this->cache['actor']);
    }*/

    // Approvers vary according to the current step. If there is no current step, we
    // want the approvers from the last step so finalization can run. Note that
    // because approvers can vary from step to step, two consecutive Approval steps
    // that use two different Approvers Groups will generate a handoff (unless the
    // current Actor is in both Groups).

    $stepId = ($stepInfo['step']->id ?? ($stepInfo['lastStep']->id ?? null));

    $actorInfo = $this->getCurrentActor($petitionId, $stepId);

    // Before we process the handoff, we take care of some other tasks that we want
    // to run before any redirect is issued. First, issue any Notifications configured
    // on either the Enrollment Flow or the _previous_ Step. (If we're transitioning
    // from start, there are no notifications to send.)

    if(!$start) {
      $MessageTemplates = TableRegistry::getTableLocator()->get('MessageTemplates');

      if(!empty($flow->notification_group_id)) {
        // There is a Notification Group on the Flow, make sure we have a Message Template

        if(!empty($flow->notification_message_template_id)) {
          $template = $MessageTemplates->get($flow->notification_message_template_id);

          $template->setContextPetition($petition);
          $template->setContextEnrollmentFlowSteps($stepInfo['lastStep'], $stepInfo['step']); 

          $MessageTemplates->Notifications->register(
            subjectPersonId: $petition->enrollee_person_id,
            subjectGroupId: null,
            actorPersonId: $actorInfo['person_id'],
            recipientPersonId: null,
            recipientGroupId: $flow->notification_group_id,
            action: ActionEnum::PetitionUpdated,
            comment: __d('result', 'Petitions.step.completed', [$petition->id, $stepInfo['lastStep']->id]),
            messageTemplate: $template,
            // We'll set the source to be the URL to the Petition itself
            source: [
              'controller'  => 'petitions',
              'action'      => 'view',
              $petitionId
            ],
            mustResolve: false
          );
        } else {
          $this->log('debug', "Enrollment Flow " . $flow->id . " has a Notification Group configured, but no Message Template");
        }
      }

      if(!empty($stepInfo['lastStep']->notification_group_id)) {
        // There is a Notification Group on this Step. Note we do _not_ check 'lastStep'
        // since if we're finalizing notifications will be handled by PetitionsTable::finalize.

        if(!empty($stepInfo['lastStep']->notification_message_template_id)) {
          $template = $MessageTemplates->get($stepInfo['lastStep']->notification_message_template_id);

          $template->setContextPetition($petition);
          $template->setContextEnrollmentFlowSteps($stepInfo['lastStep'], $stepInfo['step']); 

          $MessageTemplates->Notifications->register(
            subjectPersonId: $petition->enrollee_person_id,
            subjectGroupId: null,
            actorPersonId: $actorInfo['person_id'],
            recipientPersonId: null,
            recipientGroupId: $stepInfo['lastStep']->notification_group_id,
            action: ActionEnum::PetitionUpdated,
            comment: __d('result', 'Petitions.step.completed', [$petition->id, $stepInfo['lastStep']->id]),
            messageTemplate: $template,
            // We'll set the source to be the URL to the Petition itself
            source: [
              'controller'  => 'petitions',
              'action'      => 'view',
              $id
            ],
            mustResolve: false
          );
        } else {
          $this->log('debug', "Enrollment Flow Step " . $stepInfo['step']->id . " has a Notification Group configured, but no Message Template");
        }
      }
    }

    // Next, give the plugin an opportunity to run any preparatory steps. We don't
    // specifically support errors here, ie: if a plugin throws an Exception we let it
    // bubble up because it's not really clear what we should do if a plugin fails.

    // (If this is the last step, 'step' will be null, and there's no prepare() to call.)

    if(!empty($stepInfo['step'])) {
      $EnrollmentFlows->EnrollmentFlowSteps->prepare($stepInfo['step'], $petition);
    }

    // We need to compare the current actor type with the actor type configured for the
    // next step. If they are the same, we can simply redirect. If they are different,
    // we need to hand off via a notification, and then redirect the current actor to
    // a generic landing page.

    // Note we perform basically the same logic for finalize as regular steps,
    // except we need to explicitly set $nextActorType to the current actor type.
    // In particular, if there is a token we need to insert it for finalize as well.

    $nextActorType = null;

    if($stepInfo['finalize']) {
      // Authorization for finalize is the same as the last step configured to run.
      $nextActorType = $stepInfo['lastStep']->actor_type;
    } else {
      $nextActorType = $stepInfo['step']->actor_type;
    }

    if(in_array($nextActorType, $actorInfo['roles'])) {
      // The current actor is eligible to perform the next step, so simply redirect.
      // Note we will need to re-insert the token if currently in use.

      if($petition->useToken($nextActorType)) {
        $stepInfo['url']['?']['token'] = $this->requestParam('token');
      }

      return $this->redirect($stepInfo['url']);
    } else {
      // We need to hand off. We do this by creating a Notification for the recipient
      // (or recipient group) and then redirect to a landing page.

      // The target URL can be used as is if we have a person_id or identifier
      // for the appropriate role. If not, we need to append the petition token.
      // Note that we only permit a single non-authenticated email address since
      // we don't support different anonymous petitioners and enrollees.

      // Pull the MessageTemplate and attach context to it.
      $MessageTemplates = TableRegistry::getTableLocator()->get('MessageTemplates');

      // Message Template is _not_ a required field in the Enrollment Flow Step data model
      // because steps can transition without it. However, for approval notifications to be
      // sent we need a Message Template. We can't easily enforce this properly at configuration
      // time, but at run time this will throw an Exception.

      if(empty($stepInfo['step']->message_template_id)) {
        // Throw a more helpful error than "Record not found"
        throw new \RuntimeException(__d('error', 'EnrollmentFlowSteps.message_template', [ $stepInfo['step']->id ?? $stepInfo['lastStep']->id ]));
      }

      $template = $MessageTemplates->get($stepInfo['step']->message_template_id);

      $template->setContextPetition($petition);

      if($petition->useToken($nextActorType)) {
        // We only have an enrollee_email field to use since either the petitioner _is_
        // the enrollee (in which case that address is sufficient, eg: self signup),
        // or they are not the same person, in which case the petition _must_ be
        // authenticated (eg: an admin).

        $token = $EnrollmentFlows->Petitions->getToken($petitionId);

        // For simplicity, we just inject the continue URL into the message.

        $template->setContextEntryUrl([
          'plugin'      => null,
          'controller'  => 'petitions',
          'action'      => 'continue',
          $petition->id,
          '?' => [
            'token' => $token
          ]
       ]);

        // Send the message. sendEmailToAddress will throw an Exception if SMTP failed,
        // but if there is no SMTP server configured we'll just get false back.

        // Because we're calling DeliveryUtilities directly we need to generate the
        // message from the template here.

        $template->generateMessage();

        if(!DeliveryUtilities::sendEmailToAddress(
          coId:       $coId,
          recipient:  $petition->enrollee_email,
          subject:    $template->getMessagePart('subject'),
          body_text:  $template->getMessagePart('body_text'),
          body_html:  $template->getMessagePart('body_html')
        )) {
          throw new \RuntimeException("Message delivery failed"); // XXX I18n. can we get an exception from sendEmailToAddress instead?
        }
      } else {
        // For simplicity, we just inject the continue URL. Note that SUBJECT_NAME
        // (and subjectPersonId) is (probably) not available yet, at least for new
        // enrollments where a Person hasn't been created yet.

        $template->setContextEntryUrl([
          'plugin'      => null,
          'controller'  => 'petitions',
          'action'      => 'continue',
          $petition->id,
        ]);

        $Notifications = TableRegistry::getTableLocator()->get('Notifications');

        // Although we register the Notification here, the Plugin must resolve it
        // since we don't know at what point the Plugin will consider the Notification
        // handled.

        $Notifications->register(
          subjectPersonId: $petition->enrollee_person_id,
          subjectGroupId: null,
          actorPersonId: $actorInfo['person_id'],
          recipientPersonId: null,
          recipientGroupId: $EnrollmentFlows->Petitions->approverGroupId($petition->id, $stepId),
          action: ActionEnum::PetitionUpdated,
          comment: __d('information', 'Petitions.pending.approval', [$petition->id, $stepInfo['step']->description]),
          // Message Template is _not_ a required field in the Enrollment Flow Step data model
          // because steps can transition without it. However, for approval notifications to be
          // sent we need a Message Template. We can't easily enforce this properly at configuration
          // time, but at run time DeliveryUtilities::sendEmailFromTemplate will throw an error.
          messageTemplate: $template,
          source: $stepInfo['url'],
          mustResolve: true
        );
      }

      // Redirect to a landing page indicating that no further action is required at this time
      if(!empty($stepInfo['step']->redirect_on_handoff)) {
        // Use the step specific handoff URL
        return $this->redirect($stepInfo['step']->redirect_on_handoff);
      } else {
        // Redirect to the default Enrollment Handoff URL for this CO
        return $this->redirect(StringUtilities::pagesUrl($coId, "default-handoff"));
      }
    }
  }

  /**
   * Validate a token associated with the requested petition.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  Petition $petition Petition entity
   * @return array|bool         Roles associated with the token, or false on token error
   */

  protected function validateToken(Petition $petition): array|bool {
    $reqToken = $this->requestParam('token');

    // We can't use $petition->useToken because we don't have a role

    if(!empty($petition->token)
        // Completed Petitions no longer accept tokens for authorization
        && !$petition->isComplete()
        && ($reqToken == $petition->token)) {
      // Token match. The roles are whichever of petitioner and enrollee
      // _don't_ have a Petition value.

      $roles = [];

      if(empty($petition->petitioner_identifier) 
          && empty($petition->petitioner_person_id)) {
        $roles[] = EnrollmentActorEnum::Petitioner;
      }

      if(empty($petition->enrollee_identifier) 
          && (empty($petition->enrollee_person_id)
              || $petition->status == PetitionStatusEnum::Finalizing)) {
        $roles[] = EnrollmentActorEnum::Enrollee;
      }

      return $roles;
    }

    return false;
  }
}
