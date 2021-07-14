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

declare(strict_types = 1);

namespace App\Command;

use App\Application;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\CommandRunner;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Cake\Utility\Security;
use \App\Lib\Enum\PermissionEnum;

class SetupCommand extends Command {
  /**
   * Register command specific options.
   *
   * @since  COmanage Registry v6.0.0
   * @param  ConsoleOptionParser $parser Console Option Parser
   * @return ConsoleOptionParser         Console Option Parser
   */
  
  public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser {
    $parser->addOption('admin-username', [
                        'help' => __('registry.cmd.opt.admin-username')
                      ])->addOption('force', [
                        'help' => __('registry.cmd.opt.force'),
                        'boolean' => true
                      ]);

    return $parser;
  }

  /**
   * Execute the Setup Command.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Arguments $args Command Arguments
   * @param  ConsoleIo $io   Console IO
   */
  
  public function execute(Arguments $args, ConsoleIo $io) {
    global $argv;
    
    // Check if the security salt file already exists, and if so abort.
    
    $securitySaltFile = LOCAL . DS . "Config" . DS . "security.salt";
    
    if(file_exists($securitySaltFile)) {
      $io->out(__('registry.cmd.se.already'));
      
      if(!$args->getOption('force')) {
        exit;
      }
    }
    
    // Before we get going, prompt for whatever information we need in case
    // the user hits ctrl-c.
  /*  
    $user = $args->getOption('admin-username');
    
    while(!$user) {
      $user = $io->ask(__('match.cmd.se.admin.user'));
    }
  */  
    // Set the salt now in case we need it. (Normally this is done in bootstrap.php.)
    // We'll write it out after we're done with the database updates.
    $salt = hash('sha256', Security::randomBytes(64));
    Security::setSalt($salt);
    
    // Perform database related setup. Start by trying to run the database schema.
/*    
    // Build the runner with an application and root executable name. (based on bin/cake.php)
    $runner = new CommandRunner(new Application(dirname(__DIR__) . DS . '..' . DS . 'config'), 'cake');
    $runner->run([ $argv[0], 'database' ]);
    
    // Create the initial admin permission
    $io->out(__('match.cmd.se.admin'));
    
    $permissionsTable = TableRegistry::get('Permissions');
    $permission = $permissionsTable->newEntity();
    
    $permission->username = $user;
    $permission->matchgrid_id = null;
    $permission->permission = PermissionEnum::PlatformAdmin;
    
    if(!$permissionsTable->save($permission)) {
      throw new \RuntimeException(__('match.er.save', ['Permissions']));
    }
    
    // Register the current version for future upgrade purposes
    // Read the current release from the VERSION file
    $versionFile = CONFIG . "VERSION";

    $targetVersion = rtrim(file_get_contents($versionFile));
    
    $metaTable = TableRegistry::get('Meta');
    $metaTable->setUpgradeVersion($targetVersion, true);
  */  
    // Write out the salt file
    $io->out(__('registry.cmd.se.salt'));
    
    if(file_put_contents($securitySaltFile, $salt)===false) {
      $err = error_get_last();
      throw new \RuntimeException($err[message]);
    }
    
    // We set 444 to prevent accidental changing of the salt, but also so the 
    // web server user can read it if this script is run by (say) root.
    // We assume we're not installed on a shared, semi-public server.
    chmod($securitySaltFile, 0444);
  }
}