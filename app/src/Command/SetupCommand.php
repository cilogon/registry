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

    // Set the salt now in case we need it. Normally this is done in bootstrap.php.
    $salt = hash('sha256', Security::randomBytes(64));
    Security::setSalt($salt);

    // Write out the salt file
    $io->out(__d('command', 'se.salt'));

    if(file_put_contents($securitySaltFile, $salt) === false) {
      $err = error_get_last();
      throw new \RuntimeException($err[message]);
    }
    // We set 444 to prevent accidental changing of the salt, but also so the
    // web server user can read it if this script is run by (say) root.
    // We assume we're not installed on a shared, semi-public server.
    chmod($securitySaltFile, 0444);

    // We need the following:
    // - The COmanage CO
    // - Register the current version for future upgrade purposes

    // Start with the COmanage CO

    $io->out(__d('command', 'se.db.co'));

    $coTable = $this->getTableLocator()->get("Cos");

    $co_id = $coTable->setupCOmanageCO();
    if(!is_null($co_id)) {
      $io->out(__d('command', 'se.db.co.done', [$co_id]));
    }
  }
}