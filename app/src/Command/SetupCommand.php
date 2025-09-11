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
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Utility\Security;
use App\Lib\Enum\PermissionEnum;
use App\Lib\Enum\SuspendableStatusEnum;

class SetupCommand extends BaseCommand
{
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
   * @since  COmanage Registry v5.0.0
   * @param  Arguments  $args  Command Arguments
   * @param  ConsoleIo  $io    Console IO
   */

  public function execute(Arguments $args, ConsoleIo $io)
  {
    global $argv;

    $force = $args->getOption('force');

    // Check if the COmanage CO already exists, and if so abort.
    if($this->executeCommand(TestCommand::class, ['-t', 'setup']) === static::CODE_ERROR
       && !$force) {
      $this->abort(static::CODE_ERROR);
    }

    // Collect the admin info before we try to do anything.

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
    
    // Setup the COmanage CO, but first check if it already exists.

    $coTable = $this->getTableLocator()->get('Cos');

    $coExists = $this->executeCommand(TestCommand::class, ['-t', 'setup']) === static::CODE_ERROR;
    $co_id = null;

    if($coExists) {
      if(!$force) {
        // TestCommand already echoed an error
        $this->abort(static::CODE_ERROR);
      }

      // Pull the CO ID and keep going. We're basically reimplementing the same logic
      // that was refactored into TestCommand because we need the CO ID.

      $comanageCO = $coTable->find('COmanageCO')->first();
      $co_id = $comanageCO->id;
    }

    if(is_null($co_id)) {
      $io->out(__d('command', 'se.db.co'));
      $co_id = $coTable->setupCOmanageCO();

      if(is_null($co_id)) {
        throw new \RuntimeException('setup.co.comanage');
      }

      $io->out(__d('command', 'se.db.co.done', [$co_id]));
    } else {
      $co_id = $comanageCO->id;
    }

    // Add the first CMP Administrator.
    
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
                                                            attribute:  'PersonRoles.affiliation_type',
                                                            value:      'staff'),
      'title'                 => __d('command', 'se.person_role.title'),
      'status'                => SuspendableStatusEnum::Active
    ],
    ['validate' => false])];
    
    $g = $coTable->Groups->find('adminGroup', co_id: $co_id)->firstOrFail();

    $person->group_members = [
      $coTable->People->GroupMembers->newEntity(
        ['group_id' => $g->id],
        ['validate' => false]
      ),
      $coTable->People->GroupMembers->newEntity(
        ['group_id' => $g->owners_group_id],
        ['validate' => false]
      )
    ];
    
    $coTable->People->save($person);

    // Write the salt file if not set in environment and file does not exist.
    if(!env('SECURITY_SALT', null)) {
      $securitySaltFile = LOCAL . "config" . DS . "security.salt";

      if(file_exists($securitySaltFile)) {
        $io->out(__d('command', 'se.already'));
      } else {
        $salt = substr(bin2hex(random_bytes(1024)), 0, 40);
        file_put_contents($securitySaltFile, $salt);
        $io->out(__d('command', 'se.salt', [$securitySaltFile]));
      }
    }

    // Set the current version in the meta table
    $metaTable = $this->getTableLocator()->get('Meta');
    $metaTable->setUpgradeVersion();

    $io->out(__d('command', 'se.done'));
  }
}