<?php
/**
 * COmanage Registry Notification Command
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

declare(strict_types=1);

namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

class NotificationCommand extends BaseCommand
{
  use \Cake\ORM\Locator\LocatorAwareTrait;

  /**
   * Register command specific options.
   *
   * @param   ConsoleOptionParser  $parser  Console Option Parser
   *
   * @return ConsoleOptionParser         Console Option Parser
   * @since  COmanage Registry v5.0.0
   */

  public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
  {
    $parser->addOption(
      'coId',
      [
        'required'  => true,
        'short'     => 'c',
        'help'      => __d('command', 'opt.coId')
      ]
    )->addOption(
      'typeLabel',
      [
        'required'  => true,
        'short'     => 't',
        'help'      => __d('command', 'opt.notify.type')
      ]
    )->addOption(
      'subjectIdentifier',
      [
        'required'  => false,
        'short'     => 's',
        'help'      => __d('command', 'opt.notify.subjectid')
      ]
    )->addOption(
      'subjectGroupIdentifier',
      [
        'required'  => false,
        'short'     => 'g',
        'help'      => __d('command', 'opt.notify.subjectgroupid')
      ]
    )->addOption(
      'actorIdentifier',
      [
        'required'  => true,
        'short'     => 'a',
        'help'      => __d('command', 'opt.notify.actorid')
      ]
    )->addOption(
      'recipientIdentifier',
      [
        'required'  => false,
        'short'     => 'r',
        'help'      => __d('command', 'opt.notify.recipientid')
      ]
    )->addOption(
      'recipientGroupIdentifier',
      [
        'required'  => false,
        'short'     => 'G',
        'help'      => __d('command', 'opt.notify.recipientgroupid')
      ]
    )->addOption(
      'action',
      [
        'required'  => true,
        'short'     => 'A',
        'help'      => __d('command', 'opt.notify.action')
      ]
    )->addOption(
      'comment',
      [
        'required'  => true,
        'short'     => 'C',
        'help'      => __d('command', 'opt.notify.comment')
      ]
    )->addOption(
      'messageTemplateId',
      [
        'required'  => true,
        'short'     => 'm',
        'help'      => __d('command', 'opt.notify.templateid')
      ]
    )->addOption(
      'source',
      [
        'required'  => true,
        'short'     => 'S',
        'help'      => __d('command', 'opt.notify.source')
      ]
    )->addOption(
      'mustResolve',
      [
        'required'  => false,
        'boolean'   => true,
        'short'     => 'M',
        'help'      => __d('command', 'opt.notify.mustresolve')
      ]
// XXX given how many required fields don't apply to -R, maybe this should be a different command or mode?
/*)
    )->addOption(
      'resolve',
      [
        'required'  => false,
        'boolean'   => true,
        'short'     => 'R',
        'help'      => __d('command', 'opt.notify.resolve')
      ]*/
    );

    return $parser;
  }

  /**
   * Execute the Notification Command.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Arguments  $args  Command Arguments
   * @param  ConsoleIo  $io    Console IO
   */

  public function execute(Arguments $args, ConsoleIo $io)
  {
    $Identifiers = $this->getTableLocator()->get('Identifiers');
    $Notifications = $this->getTableLocator()->get('Notifications');
    $Types = $this->getTableLocator()->get('Types');

    $coId = (int)$args->getOption('coId');

    // Map the type label to a type ID, used for Identifier lookups

    $typeId = $Types->getTypeId($coId, 'Identifiers.type', $args->getOption('typeLabel'));

    // Subject
    $subjectGroupId = null;
    $subjectPersonId = null;

    if($args->getOption('subjectGroupIdentifier')) {
      $subjectGroupId = $Identifiers->lookupGroup($typeId, $args->getOption('subjectGroupIdentifier'));
    }

    if($args->getOption('subjectIdentifier')) {
      $subjectPersonId = $Identifiers->lookupPerson($typeId, $args->getOption('subjectIdentifier'));
    }

    // Actor

    $actorPersonId = $Identifiers->lookupPerson($typeId, $args->getOption('actorIdentifier'));

    // Recipient
    $recipientGroupId = null;
    $recipientPersonId = null;

    if($args->getOption('recipientGroupIdentifier')) {
      $recipientGroupId = $Identifiers->lookupGroup($typeId, $args->getOption('recipientGroupIdentifier'));
    }

    if($args->getOption('recipientIdentifier')) {
      $recipientPersonId = $Identifiers->lookupPerson($typeId, $args->getOption('recipientIdentifier'));
    }

    $io->out("Registering notification:");
    $io->out("- Subject Person ID: " . $subjectPersonId);
    $io->out("- Subject Group ID: " . $subjectGroupId);
    $io->out("- Actor Person ID: " . $actorPersonId);
    $io->out("- Recipient Person ID: " . $recipientPersonId);
    $io->out("- Recipient Group ID: " . $recipientGroupId);

    $MessageTemplates = TableRegistry::getTableLocator()->get('MessageTemplates');
    $template = $MessageTemplates->get($args->getOption('messageTemplateId'));


    $notificationIds = $Notifications->register(
      subjectPersonId:    $subjectPersonId,
      subjectGroupId:     $subjectGroupId,
      actorPersonId:      $actorPersonId,
      recipientPersonId:  $recipientPersonId,
      recipientGroupId:   $recipientGroupId,
      action:             $args->getOption('action'),
      comment:            $args->getOption('comment'),
      messageTemplate:    $template,
      source:             $args->getOption('source'),
      mustResolve:        $args->getOption('mustResolve')
    );

    foreach($notificationIds as $id) {
      $io->out("Registered new Notification ID: $id");
    }

    return;
  }
}