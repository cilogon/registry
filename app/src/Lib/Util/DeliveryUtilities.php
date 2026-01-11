<?php
/**
 * COmanage Registry Delivery Utilities
 * 
 * While this is initially for email delivery, ultimately it could be useful for
 * (eg) SMS or other transports.
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

namespace App\Lib\Util;

use Cake\Mailer\Message;
use Cake\Mailer\Transport\SmtpTransport;
use Cake\ORM\TableRegistry;
use App\Model\Entity\MessageTemplate;

class DeliveryUtilities {
  use \App\Lib\Traits\LabeledLogTrait;

  /**
   * Send an email to an Address. If no Outgoing SMTP Server is configured,
   * an InvalidArgumentException will be thrown.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $coId       CO ID
   * @param  string $recipient  Recipient email address
   * @param  string $subject    Message subject
   * @param  string $body_text  Message body (plain text)
   * @param  string $body_html  Message body (HTML)
   * @param  string $cc         Addresses to cc
   * @param  string $bcc        Addresses to bcc
   * @param  string $replyTo    Reply-To address to use, instead of the default
   * @return bool               Returns true if mail was sent
   * @throws Cake\Network\Exception\SocketException
   * @throws InvalidArgumentException
   */

  public static function sendEmailToAddress(
    int     $coId,
    string  $recipient,
    string  $subject,
    string  $body_text="",
    string  $body_html="",
    string  $cc="",
    string  $bcc="",
    string  $replyTo=""
  ) {
    // We start by trying to pull the CO Outgoing SMTP Server configuration.
    // If one isn't available, we log a warning, but we don't throw an exception
    // since it's valid to not have an outgoing SMTP server set.

    $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');

    $smtp = $CoSettings->getSmtpServer($coId);

    if(empty($smtp)) {
      // No SMTP configured
      self::slog('debug', "No Outgoing SMTP Server is configured for CO $coId, so no mail will be sent");

      // Originally we returned false here, but that means the only indication of a missing
      // SMTP server configuration is in the error logs, which doesn't seem helpful.
      // So we throw an Exception instead, and if the calling code doesn't like that
      // it can catch it and do something else.
      throw new \InvalidArgumentException(__d('error', 'smtp_server.none'));
    }

    // Next figure out the recipient
    $to = $recipient;

    if(!empty($smtp->override_to)) {
      self::slog('debug', "Overriding deliver address for $recipient to " . $smtp->override_to);
      $to = $smtp->override_to;
    }

    // We use Message and MailTransport because Mailer only allows both HTML and Text
    // when using Layouts, which we don't want to use.

    $message = new Message();

    $message->setTo($to)
            ->setSubject($subject)
            ->setFrom($smtp->default_from)
            // Use the provided Reply-To if set, else the default
            // Note we can't use ?? here because the default value is "", which is not null
            ->setReplyTo(!empty($replyTo) ? $replyTo : $smtp->default_reply_to);

    // Overriding the to address suppressess the cc and bcc addresses
    if(!empty($cc) && empty($smtp->override_to)) {
      $message->setCc($cc);
    }

    if(!empty($bcc) && empty($smtp->override_to)) {
      $message->setBcc($bcc);
    }
    
    if(!empty($body_text)) {
      $message->setBodyText($body_text);
      $message->setEmailFormat('text');
    }

    if(!empty($body_html)) {
      $message->setBodyHtml($body_html);
      $message->setEmailFormat(!empty($body_text) ? 'both' : 'html');
    }

    // This could go in SmtpServer, but for now we only create an SmtpTransport here
    $transport = new SmtpTransport([
      'host' => $smtp->hostname,
      'port' => $smtp->port,
      'username' => $smtp->username,
      'password' => $smtp->password,
      'tls' => $smtp->use_tls
    ]);

    try {
      $result = $transport->send($message);
    }
    catch(Cake\Network\Exception\SocketException $e) {
      self::slog('error', $e->getMessage());

      throw $e;
    }
    
    self::slog('debug', "Mail for $to sent successfully");

    return true;
  }

  /**
   * Send an email using a Message Template, accounting for preferred delivery addresses
   * and other settings.
   * 
   * Either $personId or $address must be specified.
   * 
   * If $messageTemplateId is specified, it will take precedence over $subject and $body.
   * The various entities are used to populate the Message Template, and are not required
   * if $subject and $body are used intead.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  MessageTemplate  $template   Message Template
   * @param  int              $personId   Recipient Person ID
   * @param  string           $address    Recipient Email Address
   * @param  int              $groupId    Recipient Group ID
   * @return array                        'recipient': Recipient email address ("to" only, not "cc" or "bcc")
   */

  public static function sendEmailFromTemplate(
    MessageTemplate                   $template,
    ?int                              $personId=null,
    ?string                           $address=null,
    ?int                              $groupId=null
  ): array {
    $template->generateMessage();

    if($personId) {
      return self::sendEmailToPerson(
        personId:   $personId,
        subject:    $template->getMessagePart('subject'),
        body_text:  $template->getMessagePart('body_text'),
        body_html:  $template->getMessagePart('body_html'),
        cc:         $template->cc,
        bcc:        $template->bcc,
        replyTo:    $template->reply_to
      );
    } else {
      self::sendEmailToAddress(
        coId:       $template->co_id,
        recipient:  $address,
        subject:    $template->getMessagePart('subject'),
        body_text:  $template->getMessagePart('body_text'),
        body_html:  $template->getMessagePart('body_html'),
        cc:         $template->cc,
        bcc:        $template->bcc,
        replyTo:    $template->reply_to
      );

      return [
        'recipient' => $address
      ];
    }
  }

  /**
   * Send an email to a Person, accoungting for available email addresses
   * and other settings.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $personId   Recipient Person ID
   * @param  string $subject    Message subject
   * @param  string $body_text  Message body (plain text)
   * @param  string $body_html  Message body (HTML)
   * @param  string $cc         Addresses to cc
   * @param  string $bcc        Addresses to bcc
   * @param  string $replyTo    Reply-To address to use, instead of the default
   * @return array              'recipient': Recipient email address ("to" only, not "cc" or "bcc")
   */

  public static function sendEmailToPerson(
    int     $personId,
    string  $subject,
    string  $body_text="",
    string  $body_html="",
    string  $cc="",
    string  $bcc="",
    string  $replyTo=""
  ): array {
    // Find a deliverable Email Address for $personId

    $EmailAddresses = TableRegistry::getTableLocator()->get('EmailAddresses');

    $recipient = $EmailAddresses->getDeliveryAddress($personId);

    self::slog('debug', "Mapped Person ID $personId to $recipient for mail delivery");

    // We also need the CO ID. This effectively causes the Person to be retrieved twice
    // (once by getDeliveryAddress), but that's a rounding error in the overall number
    // of queries.

    $People = TableRegistry::getTableLocator()->get('People');

    $person = $People->get($personId);

    self::sendEmailToAddress(
      coId:       $person->co_id,
      recipient:  $recipient,
      subject:    $subject,
      body_text:  $body_text,
      body_html:  $body_html,
      cc:         $cc,
      bcc:        $bcc,
      replyTo:    $replyTo
    );

    return [
      'recipient' => $recipient
    ];
  }
}