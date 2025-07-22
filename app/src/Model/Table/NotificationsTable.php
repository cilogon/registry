<?php
/**
 * COmanage Registry Notifications Table
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

namespace App\Model\Table;

use Cake\Event\EventInterface;
use Cake\Mailer\Mailer;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Cake\Validation\Validator;
use \App\Lib\Enum\ActionEnum;
use \App\Lib\Enum\NotificationStatusEnum;
use \App\Lib\Util\DeliveryUtilities;
use \App\Model\Entity\MessageTemplate;
use \App\Model\Entity\Notification;

class NotificationsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);
    
    // Define associations
    $this->belongsTo('MessageTemplates');
    $this->belongsTo('ActorPeople')
         ->setClassName('People')
         ->setForeignKey('actor_person_id')
         ->setProperty('actor_person');
    $this->belongsTo('RecipientGroups')
         ->setClassName('Groups')
         ->setForeignKey('recipient_group_id')
         ->setProperty('recipient_group');
    $this->belongsTo('RecipientPeople')
         ->setClassName('People')
         ->setForeignKey('recipient_person_id')
         ->setProperty('recipient_person');
    $this->belongsTo('ResolverPeople')
         ->setClassName('People')
         ->setForeignKey('resolver_person_id')
         ->setProperty('resolver_person');
    $this->belongsTo('SubjectGroups')
         ->setClassName('Groups')
         ->setForeignKey('subject_group_id')
         ->setProperty('subject_group');
    $this->belongsTo('SubjectPeople')
         ->setClassName('People')
         ->setForeignKey('subject_person_id')
         ->setProperty('subject_person');
    
    $this->setDisplayField('comment');
    
    $this->setPrimaryLink([
      // The subject could be null, eg during an Enrollment Flow where no Person has
      // been allocated yet, so we also accept recipients as primary keys (since there
      // has to be at least one recipient within the CO)
      'subject_person_id' => 'People',
      'subject_group_id' => 'Groups',
      'recipient_person_id' => 'People',
      'recipient_group_id' => 'Groups'
    ]);
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['acknowledge', 'cancel', 'resend']);
    
    // These are required for the link to work from the Artifacts page
    $this->setAllowUnkeyedPrimaryCO(['index']);
    $this->setAllowEmptyPrimaryLink(['index']);

    $this->setAutoViewVars([
      'statuses' => [
        'type'  => 'enum',
        'class' => 'NotificationStatusEnum'
      ]
    ]);

    $this->setIndexContains([
      'RecipientGroups',
      'RecipientPeople' => ['PrimaryName'],
      'SubjectGroups',
      'SubjectPeople' => ['PrimaryName']
    ]);

    $this->setViewContains([
      'ActorPeople' => ['PrimaryName'],
      'RecipientGroups',
      'RecipientPeople' => ['PrimaryName'],
      'ResolverPeople' => ['PrimaryName'],
      'SubjectGroups',
      'SubjectPeople' => ['PrimaryName']
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'acknowledge' =>  ['platformAdmin', 'coAdmin'],
        'cancel' =>       ['platformAdmin', 'coAdmin'],
        'delete' =>       false,
        'edit' =>         false,
        'resend' =>       ['platformAdmin', 'coAdmin'],
        'view' =>         ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false,
        'index' =>    ['platformAdmin', 'coAdmin']
      ],
      'readOnly' => ['acknowledge', 'cancel', 'resend']
    ]);
  }
  
  /**
   * Acknowledge an outstanding notification.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int  $id         Notification ID
   * @param  int  $personId   Person ID of person acknowledging the notification
   * @throws InvalidArgumentException
   */

  public function acknowledge(
    int $id,
    int $personId
  ) {
    $this->processResolution($this->get($id), NotificationStatusEnum::Acknowledged, $personId);
  }

  /**
   * Callback before data is marshaled into an entity.
   *
   * @since  COmanage Registry v5.2.0
   * @param  EventInterface  $event   beforeMarshal event
   * @param  ArrayObject     $data    Entity data
   * @param  ArrayObject     $options Callback options
   */

  public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options) {
    if(!empty($data['source'] && is_array($data['source']))) {
      // Convert the URL array to a string so we can store it in the database
      $data['source'] = Router::url(url: $data['source'], full: true);
    }
  }

  /**
   * Cancel an outstanding notification.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int  $id         Notification ID
   * @param  int  $personId   Person ID of person canceling the notification
   * @throws InvalidArgumentException
   */

  public function cancel(
    int $id,
    int $personId
  ) {
    $this->processResolution($this->get($id), NotificationStatusEnum::Canceled, $personId);
  }

  /**
   * Deliver a notification.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Notification     $notification     Notification
   * @param  MessageTemplate  $messageTemplate  MessageTemplate
   * @param  int              $actorPersonId    Person ID that caused the Notification to be generated
   * @param  bool                               Return true on success
   */

  public function deliver(
    Notification    $notification,
    MessageTemplate $messageTemplate,
    ?int            $actorPersonId=null
  ) {
    // We call this function "deliver" because ultimately we might support other
    // mechanisms besides email, but for now this basically just delivers the
    // notification via email.

    // Only notifications in the correct state can be deliverd.
    if(!$notification->canNotify()) {
      throw new \InvalidArgumentException(__d('error', 'Notifications.notify.status'));
    }

    // Construct the message content. We do this here rather than rely on DeliveryUtilities
    // so we can update the Notification, and so DeliveryUtilities doesn't have to generate
    // the message multiple times when there are multiple recipients.

    $messageTemplate->generateMessage();

    // Update the notification with the message content
    $notification->email_subject = $messageTemplate->getMessagePart('subject');
    $notification->email_body_text = $messageTemplate->getMessagePart('body_text');
    $notification->email_body_html = $messageTemplate->getMessagePart('body_html');

    $this->save($notification);

    // If we have a recipient Group, convert it to a list of People
    $recipients = [];

    if(!empty($notification->recipient_group_id)) {
      // The iterator will only return active, valid members. Since notification groups
      // are typically small, we simply store the member IDs in $recipients and do the
      // processing below.

      $iterator = $this->RecipientGroups->getMembers($notification->recipient_group_id);

      foreach($iterator as $k => $gm) {
        $recipients[] = $gm->person_id;
      }
    }

    if(!empty($notification->recipient_person_id)) {
      $recipients[] = $notification->recipient_person_id;
    }

    $HistoryRecords = TableRegistry::getTableLocator()->get('HistoryRecords');

    foreach($recipients as $rpid) {
      $result = DeliveryUtilities::sendEmailToPerson(
        personId:   $rpid,
        subject:    $messageTemplate->getMessagePart('subject'),
        body_text:  $messageTemplate->getMessagePart('body_text'),
        body_html:  $messageTemplate->getMessagePart('body_html'),
        cc:         $messageTemplate->cc,
        bcc:        $messageTemplate->bcc,
        replyTo:    $messageTemplate->reply_to
      );

      if(!empty($result['recipient'])) {
        // Create a History Record

        $HistoryRecords->recordForPerson(
          personId:       $rpid,
          action:         ActionEnum::NotificationDelivered,
          comment:        __d('result', 'Notifications.delivered', [$notification->id, $result['recipient']]),
          actorPersonId:  $actorPersonId
        );
      }
    }

    return true;
  }

  /**
   * Modify an index Query to specify how to filter on the requested CO.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Query  $query  Query object
   * @param  int    $coId   CO ID to filter on
   * @return Query          Modified query
   */

  public function filterIndexByCO(Query $query, int $coId): Query {
    return $query->where([
      'OR' => [
        'RecipientPeople.co_id' => $coId,
        'RecipientGroups.co_id' => $coId
      ]
    ]);
  }

  /**
   * Process the resolution of a Notification.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Notification           $notification     Notification
   * @param  NotificationStatusEnum $resolution       NotificationStatusEnum
   * @param  int                    $resolverPersonId Resolver Person ID
   * @throws InvalidArgumentException
   */

  protected function processResolution(
    Notification  $notification,
    string        $resolution,
    ?int          $resolverPersonId
  ) {
    $actions = [
      NotificationStatusEnum::Acknowledged => ActionEnum::NotificationAcknowledged,
      NotificationStatusEnum::Canceled => ActionEnum::NotificationCanceled,
      NotificationStatusEnum::Resolved => ActionEnum::NotificationResolved
    ];

    // There is some logic here that could be surfaced as Application Rules and/or
    // implemented via buildRules(), but for now we'll just put it here since all
    // resolutions are handled via this function.

    // Resolutions require a current corresponding status
    if($resolution == NotificationStatusEnum::Acknowledged
       && $notification->status != NotificationStatusEnum::PendingAcknowledgment) {
      throw new \InvalidArgumentException(_d('error', 'Notifications.acknowledge'));
    } elseif($resolution == NotificationStatusEnum::Canceled
       && !$notification->canCancel()) {
      throw new \InvalidArgumentException(__d('error', 'Notifications.cancel'));
    } elseif($resolution == NotificationStatusEnum::Resolved
       && $notification->status != NotificationStatusEnum::PendingResolution) {
      throw new \InvalidArgumentException(__d('error', 'Notifications.resolve'));
    } elseif(!isset($actions[$resolution])) {
      // This status is not a valid resolution
      throw new \InvalidArgumentException(__d('error', 'Notifications.status', [$resolution]));
    }

    // Update the Notification

    $notification->status = $resolution;
    $notification->resolver_person_id = $resolverPersonId;
    $notification->resolution_time = date('Y-m-d H:i:s');

    $this->save($notification);

    // Create a History Record, though note during Enrollment Flows we won't necessarily
    // have a Subject Person, in which case there's no point recording History.

    if(!empty($notification->subject_person_id)) {
      $HistoryRecords = TableRegistry::getTableLocator()->get('HistoryRecords');

      $HistoryRecords->recordForPerson(
        personId:       $notification->subject_person_id,
        action:         $actions[$resolution],
        comment:        __d('result', 'Notifications.'.$resolution, [$notification->comment]),
        actorPersonId:  $resolverPersonId
      );
    }

    // If a notification is resolved (not acknowledged) and had a receipient group,
    // send email to the non-actor group members notifying them it was resolved.
    // Do this after the history record is created in case something goes wrong.
// XXX implement with notfication groups
  }

  /**
   * Register a Notification.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int              $subjectPersonId    Person ID the Notification is about
   * @param  int              $subjectGroupId     Group ID the Notification is about
   * @param  int              $actorPersonId      Person ID that caused the Notification to be generated
   * @param  int              $recipientPersonId  Person ID to receive the Notification (either this or $recipientGroupId must be specified)
   * @param  int              $recipientGroupId   Group ID to receive the Notification (either this or $recipientPersonId must be specified)
   * @param  string           $action             Action code related to this Notification
   * @param  string           $comment            Summary human readable comment for this Notification
   * @param  MessageTemplate  $messageTemplate    Message Template to be used when sending email for this Notification
   * @param  mixed            $source             Source URL for this Notification, either as a string or a Cake URL array
   * @param  bool             $mustResolve        If true, this Notification must be resolved, it cannot be acknowledged
   * @return array                                Array of Notification IDs (one per Notification recipient)
   */
  
  public function register(
    ?int            $subjectPersonId,
    ?int            $subjectGroupId,
    ?int            $actorPersonId,
    ?int            $recipientPersonId,
    ?int            $recipientGroupId,
    string          $action,
    string          $comment,
    MessageTemplate $messageTemplate,
    mixed           $source,
    bool            $mustResolve=false
  ): array {
    $obj = $this->newEntity([
      'subject_person_id'     => $subjectPersonId,
      'subject_group_id'      => $subjectGroupId,
      'actor_person_id'       => $actorPersonId,
      'recipient_person_id'   => $recipientPersonId,
      'recipient_group_id'    => $recipientGroupId,
      'resolver_person_id'    => null,
      'action'                => $action,
      'comment'               => $comment,
      'message_template_id'   => $messageTemplate->id,
      'source'                => $source,
      'status'                => ($mustResolve
                                  ? NotificationStatusEnum::PendingResolution
                                  : NotificationStatusEnum::PendingAcknowledgment)
    ]);

    $this->saveOrFail($obj);

    // Attach the Notification to the Message Template
    $messageTemplate->setContextNotification($obj);

    if($subjectPersonId) {
      // Retrieve and attach the Subject Person
      $messageTemplate->setContextSubjectPerson(
        $this->SubjectPeople->get($subjectPersonId, ['contain' => 'PrimaryName'])
      );
    }

    // Note the various message fields will be set by DeliveryUtilities.
    $this->deliver($obj, $messageTemplate, $actorPersonId);

    return [ $obj->id ];
  }

  /**
   * Resolve a notification.
   *

  public function resolve(
    int $id
  ) { 
    // XXX call processResolution()
  }*/

  /**
   * Resolve all outstanding notifications from the specified source.
   *
   * @since  COmanage Registry v5.2.0
   * @param  mixed                  $source             Source array or URL, exactly matching what was provided previously to register()
   * @param  int                    $resolverCoPersonId Person ID who resolved the notification
   * @param  NotificationStatusEnum $resolution         NotificationStatusEnum
   */

  public function resolveFromSource(
    mixed   $source,
    string  $resolution=NotificationStatusEnum::Resolved,
    ?int    $resolverPersonId=null,
  ) {
    $sourceUrl = $source;

    if(is_array($sourceUrl)) {
      $sourceUrl = Router::url(url: $source, full: true);
    }

    // Pull any Notifications from $source that are Pending Resolution
    $notifications = $this->find()
                          ->where([
                            'source' => $sourceUrl,
                            'status' => NotificationStatusEnum::PendingResolution
                          ])
                          ->all();
    
    foreach($notifications as $n) {
      $this->processResolution(
        $n,
        $resolution,
        $resolverPersonId
      );
    }
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

// XXX one of subject_person_id or subject_group_id should be required,
    $validator->add('subject_person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('subject_person_id');

    $validator->add('subject_group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('subject_group_id');

    $validator->add('actor_person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('actor_person_id');

    $validator->add('recipient_person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('recipient_person_id');

    $validator->add('resolver_person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('resolver_person_id');

    $this->registerStringValidation($validator, $schema, 'action', true);

    $this->registerStringValidation($validator, $schema, 'comment', true);
    
    $validator->add('message_template_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('message_template_id');

    $this->registerStringValidation($validator, $schema, 'source', true);

    $this->registerStringValidation($validator, $schema, 'email_subject', false);

    $this->registerStringValidation($validator, $schema, 'email_body_text', false);

    $this->registerStringValidation($validator, $schema, 'email_body_html', false);

    $this->registerStringValidation($validator, $schema, 'resolution_subject', false);

    $this->registerStringValidation($validator, $schema, 'resolution_body', false);

    $validator->add('status', [
      'content' => ['rule' => ['inList', NotificationStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $validator->add('notification_time', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('notification_time');

    $validator->add('resolution_time', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('resolution_time');

    return $validator; 
  }
}