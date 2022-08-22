<?php
/**
 * COmanage Registry Setup Command
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
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Utility\Security;
use App\Lib\Enum\PermissionEnum;
use App\Lib\Enum\SuspendableStatusEnum;

class SetupCommand extends Command
{
  /**
   * Register command specific options.
   *
   * @param   ConsoleOptionParser  $parser  Console Option Parser
   *
   * @return ConsoleOptionParser         Console Option Parser
   * @since  COmanage Registry v6.0.0
   */

  public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
  {
    $parser->addOption('admin-username', [
      'help' => __d('command', 'opt.admin-username'),
    ])->addOption('admin-given-name', [
      'help' => __d('command', 'opt.admin-given-name'),
    ])->addOption('admin-family-name', [
      'help' => __d('command', 'opt.admin-family-name'),
    ])->addOption('force', [
      'help'    => __d('command', 'opt.force'),
      'boolean' => true,
    ]);

    return $parser;
  }

  /**
   * Execute the Setup Command.
   *
   * @param   Arguments  $args  Command Arguments
   * @param   ConsoleIo  $io    Console IO
   *
   * @since  COmanage Registry v5.0.0
   */

  public function execute(Arguments $args, ConsoleIo $io)
  {
    global $argv;

    // Check if the security salt file already exists, and if so abort.

    $securitySaltFile = LOCAL . DS . "config" . DS . "security.salt";
    
    if(file_exists($securitySaltFile)) {
      $io->out(__d('command', 'se.already'));

      if(!$args->getOption('force')) {
        exit;
      }
    }
    
    // Collect the admin info before we try to do anything
    
    $givenName = $args->getOption('admin-given-name');
    $sn = $args->getOption('admin-family-name');
    $username = $args->getOption('admin-username');
    
    if(empty($givenName)) {
      $givenName = $io->ask(__d('command', 'opt.admin-given-name'));
    }
    
    if(empty($sn)) {
      $sn = $io->ask(__d('command', 'opt.admin-family-name'));
    }
    
    if(empty($username)) {
      $username = $io->ask(__d('command', 'opt.admin-username'));
    }
    
    $coTable = $this->getTableLocator()->get('Cos');
    
    // Add the first CMP Administrator
    
    $io->out(__d('command', 'se.db.cmpadmin'));
    
    // We disable validation here because there may be dependencies on
    // validation aspects that aren't set up yet or aren't available here
    
    $person = $coTable->People->newEntity([
      'co_id'   => $co_id,
      'status'  => SuspendableStatusEnum::Active
    ],
    ['validate' => false]);
    
    $person->names = [$coTable->People->Names->newEntity([
      'type_id'       => $coTable->Types->getTypeId(coId:       $co_id, 
                                                    attribute:  'Names.type',
                                                    value:      'official'),
      'given'         => $givenName,
      'family'        => $sn,
      'primary_name'  => true
    ],
    ['validate' => false])];
    
    $person->identifiers = [$coTable->People->Identifiers->newEntity([
      'type_id'       => $coTable->Types->getTypeId(coId:       $co_id, 
                                                    attribute:  'Identifiers.type',
                                                    value:      'network'),
      'identifier'    => $username,
      'login'         => true,
      'status'        => SuspendableStatusEnum::Active
    ],
    ['validate' => false])];
    
    $person->person_roles = [$coTable->People->PersonRoles->newEntity([
      'affiliation_type_id'   => $coTable->Types->getTypeId(coId:       $co_id, 
                                                            attribute:  'PersonRoles.affiliation',
                                                            value:      'staff'),
      'title'                 => __d('command', 'se.person_role.title'),
      'status'                => SuspendableStatusEnum::Active
    ],
    ['validate' => false])];
    
    $person->group_members = [$coTable->People->GroupMembers->newEntity([
      'group_id' => $coTable->Groups->getAdminGroupId(coId: $co_id)
    ],
    ['validate' => false])];
    
    $person->group_owners = [$coTable->People->GroupOwners->newEntity([
      'group_id' => $coTable->Groups->getAdminGroupId(coId: $co_id)
    ],
    ['validate' => false])];
    
    $coTable->People->save($person);
    
    $io->out(__d('command', 'se.done'));
  }
}