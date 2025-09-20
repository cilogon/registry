<?php
/**
 * COmanage Registry Message Template Entity
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
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Entity;

use Cake\ORM\Entity;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

class MessageTemplate extends Entity {
  use \App\Lib\Traits\EntityMetaTrait;
  
  protected array $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false, 
  ];

  // Unlike most Entities, MessageTemplate has a bit more going on here. The idea is that
  // code that needs to send messages from templates can retrieve a MessageTemplate
  // configuration from the database, then attach context to it and pass the entity around
  // (eg: to Notifications and DeliveryUtilities) instead of passing a bunch of context.
  // The MessageTemplate itself then creates the populated messages. (This requires the
  // Entity to call other Tables, which isn't the standard pattern, but makes the code
  // that relies on templates much cleaner.)

  // (Attaching the context to the Table instead complicates things when a single page action
  // could use multiple Templates.)

  protected ?string             $ctx_code = null;
  protected ?array              $ctx_entryUrl = null;
  protected ?Notification       $ctx_notification = null;
  protected ?Petition           $ctx_petition = null;
  protected ?EnrollmentFlowStep $ctx_last_step = null;
  protected ?EnrollmentFlowStep $ctx_next_step = null;
  protected ?Person             $ctx_subjectPerson = null;

  // We also cache the generated message, which is less clunky than returning an array
  // of the essage components

  protected array $msg = [
    'subject' => "",
    'body_html' => "",
    'body_text' => ""
  ];

  /**
   * Generate a message based on this Message Template, using the provided entities to
   * perform variable substitution.
   * 
   * @since  COmanage Registry v5.2.0
   * @return void
   */

  public function generateMessage(): void {
    // We generate "" instead of null by default for compatibility with DeliveryUtilities

    // First build an array of supported substitutions for which appropriate context was provided.

    $substitutions = [];

    // Lookup the CO Name
    $Cos = TableRegistry::getTableLocator()->get('Cos');

    $co = $Cos->get($this->co_id);

    $substitutions['CO_NAME'] = $co->name;

    if($this->ctx_code) {
      $substitutions['VERIFICATION_CODE'] = $this->ctx_code;
    }

    if($this->ctx_entryUrl) {
      $substitutions['ENTRY_URL'] = Router::url(
        array_merge($this->ctx_entryUrl, ['_full' => true])
      );
    }

    if($this->ctx_notification) {
      $substitutions['NOTIFICATION_COMMENT'] = $this->ctx_notification->comment;
      $substitutions['NOTIFICATION_SOURCE'] = $this->ctx_notification->source;
    }

    if($this->ctx_petition) {
      $Petitions = TableRegistry::getTableLocator()->get('Petitions');

      $substitutions['ENROLLEE_EMAIL'] = $this->ctx_petition->enrollee_email;
      $substitutions['ENROLLEE_NAME'] = $Petitions->getEnrolleeName($this->ctx_petition->id)
                                        ?? __d('field', 'Petitions.enrollee.new');
      $substitutions['PETITION_URL'] = Router::url([
        'plugin'      => null,
        'controller'  => 'petitions',
        'action'      => 'view',
        $this->ctx_petition->id,
        '_full'       => true
      ]);

      if($this->ctx_petition->cou_id) {
        $cou = $Cos->Cous->get($this->ctx_petition->cou_id);

        $substitutions['COU_NAME'] = $cou->name;
      }
    }

    if($this->ctx_last_step) {
      $substitutions['EF_LAST_STEP_DESC'] = $this->ctx_last_step->description;
    }
    
    if($this->ctx_next_step) {
      $substitutions['EF_NEXT_STEP_DESC'] = $this->ctx_next_step->description;
    }

    if($this->ctx_subjectPerson && !empty($this->ctx_subjectPerson->primary_name)) {
      $substitutions['SUBJECT_NAME'] = $this->ctx_subjectPerson->primary_name->full_name;
    }

    // Finally run the substitutions through each of the supported parts
    
    foreach(array_keys($this->msg) as $part) {
      if(!empty($this->$part)) {
        // Process the (@SUBSTITUTIONS) for this part
        $searchKeys = [];
        $replaceVals = [];

        foreach(array_keys($substitutions) as $k) {
          $searchKeys[] = "(@" . $k . ")";
          $replaceVals[] = $substitutions[$k] ?? "(?)";
        }

        $this->msg[$part] = str_replace($searchKeys, $replaceVals, $this->$part);
      }
    }
  }

  /**
   * Obtain the post-substitution message part, available after generateMessage()
   * is called.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string $part   "subject", "body_html", or "body_text"
   * @return string         Requested message part
   */

  public function getMessagePart(string $part): string {
    return $this->msg[$part];
  }

  /**
   * Set the code for the Message Template context.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string  $code  Code, eg for Email verification
   * @return MessageTemplate
   */

  public function setContextCode(string $code) {
    $this->ctx_code = $code;

    return $this;
  }
  
  /**
   * Set the Enrollment Flow Step for the Message Template context.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  EnrollmentFlowStep   $lastStep   Enrollment Flow Step that just completed
   * @param  EnrollmentFlowStep   $nextStep   Enrollment Flow Step that is next to run
   * @return MessageTemplate
   */

  public function setContextEnrollmentFlowSteps(
    ?EnrollmentFlowStep $lastStep,
    ?EnrollmentFlowStep $nextStep
  ) {
    $this->ctx_last_step = $lastStep;
    $this->ctx_next_step = $nextStep;

    return $this;
  }

  /**
   * Set the entry URL for the Message Template context.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  array   $url   Cake URL array for notification re-entry
   * @return MessageTemplate
   */

  public function setContextEntryUrl(array $url) {
    $this->ctx_entryUrl = $url;

    return $this;
  }
  
  /**
   * Set the Notification for the Message Template context.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Notification   $notification   Notification
   * @return MessageTemplate
   */

  public function setContextNotification(Notification $notification) {
    $this->ctx_notification = $notification;

    return $this;
  }
  
  /**
   * Set the Petition for the Message Template context.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Petition   $petition   Petition
   * @return MessageTemplate
   */

  public function setContextPetition(Petition $petition) {
    $this->ctx_petition = $petition;

    return $this;
  }
  
  /**
   * Set the Subject Person for the Message Template context.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Person  $subjectPerson   Subject Person with PrimaryName
   * @return MessageTemplate
   */

  public function setContextSubjectPerson(Person $subjectPerson) {
    $this->ctx_subjectPerson = $subjectPerson;

    return $this;
  }
}