<?php
/**
 * COmanage Registry Approval Collectors Controller
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

namespace CoreEnroller\Controller;

use App\Controller\StandardEnrollerController;
use App\Lib\Util\DeliveryUtilities;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\PetitionStatusEnum;

class ApprovalCollectorsController extends StandardEnrollerController {
  protected array $paginate = [
    'order' => [
      'ApprovalCollectors.id' => 'asc'
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
    $session = $request->getSession();
    // $username = $session->read('Auth.external.user');

    $petition = $this->getPetition();
    $coId = $this->getCOID();
    
    // We need the petition to have more information in the approval view, so 
    // build a view variable that holds more than vv_petition.
    $Petition = TableRegistry::getTableLocator()->get('Petitions');
    if(!empty($petition->id)) {
      $this->set('vv_approval_petition', $Petition->get($petition->id, contain: $Petition->getViewContains()));
    } else {
      $this->set('vv_approval_petition', null);
    }

    if($request->is('post')) {
      $cfg = $this->ApprovalCollectors->get($id);

      try {
        // Record approval or denial

        $approved = $this->requestParam('approved');
        $comment = $this->requestParam('comment');

        // record() will handle updatind the Petition status and performing other
        // recordkeeping transactions, including enforcing comment if required

        $this->ApprovalCollectors->record(
          petitionId:           $petition->id,
          approvalCollectorId:  (int)$id,
          approverPersonId:     $this->RegistryAuth->getPersonID($coId),
          approved:             $approved == PetitionStatusEnum::Approved,
          comment:              $comment
        );

        if($approved != PetitionStatusEnum::Approved) {
          // If we have a denial Message Template, send the notification to the enrollee
          // email address. We don't currently support using a Notification, since in most
          // cases the Enrollee will not have a Person record yet. (There are some edge
          // cases around processes like Additional Role Enrollment where we might want
          // to be able to Notify the Person using their existing preferred Email Address,
          // but for now we don't support that.)

          if(!empty($cfg->denial_message_template_id)
             && !empty($petition->enrollee_email)) {
            $MessageTemplates = TableRegistry::getTableLocator()->get('MessageTemplates');

            // Generate the message and send

            $template = $MessageTemplates->get($cfg->denial_message_template_id);

            $template->setContextPetition($petition);

            $template->generateMessage();

            // Send the message. sendEmailToAddress will throw an Exception if SMTP failed,
            // but if there is no SMTP server configured we'll just get false back.

            if(!DeliveryUtilities::sendEmailToAddress(
              coId:       $this->getCOID(),
              recipient:  $petition->enrollee_email,
              subject:    $template->getMessagePart('subject'),
              body_text:  $template->getMessagePart('body_text'),
              body_html:  $template->getMessagePart('body_html')
            )) {
              throw new \RuntimeException("Message delivery failed"); // XXX I18n. can we get an exception from sendEmailToAddress instead?
            }
          }

          // If we have a redirect on denial configured, send the Approver there
          if(!empty($cfg->redirect_on_denial)) {
            return $this->redirect($cfg->redirect_on_denial);
          } else {
            // Redirect to the default Enrollment Handoff URL for this CO
            return $this->redirect(StringUtilities::pagesUrl($coId, "default-handoff"));
          }
        }

        // Where do we redirect? On approval, it's possible that the next step has the
        // same Approver's group on handoff, in which case we just let the flow continue.
        // However on denial, we need to stop the flow. So basically we need a separate
        // "redirect on denial" target (or we use the default Enrollment Flow handoff if
        // not configured).

        // Redirect to the next step

        return $this->finishStep(
          enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
          petitionId:           $petition->id,
          comment:              __d('core_enroller', 'result.ApprovalCollectors.' . ($approved == PetitionStatusEnum::Approved ? 'approved' : 'denied'))
        );
      }
      catch(\Exception $e) {
        $this->llog('error', $e->getMessage());

        $this->Flash->error($e->getMessage());
      }
    }

    // Check for existing values in case we're re-running the step
    $this->set('petition_approvals',
               $this->ApprovalCollectors->PetitionApprovals->find()
                    ->where(['petition_id' => $petition->id, 'approval_collector_id' => $id])
                    ->first());

    $this->render('/Standard/dispatch');
  }
}