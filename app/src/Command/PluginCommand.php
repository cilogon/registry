<?php
/**
 * COmanage Plugin Command
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

class PluginCommand extends BaseCommand {
  /**
   * Build an Option Parser.
   *
   * @since  COmanage Registry v5.2.0
   * @param  ConsoleOptionParser $parser ConsoleOptionParser
   * @return ConsoleOptionParser         ConsoleOptionParser
   */
  
  protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser {
    $parser->addOption('action', [
      'short' => 'a',
      'choices' => ['activate', 'applySchema', 'deactivate'],
      'required' => true,
      'help'  => __d('command', 'opt.plugin.action')
    ])->addOption('plugin', [
      'short' => 'p',
      'required' => true,
      'help'  => __d('command', 'opt.plugin.plugin')
    ]);

    return $parser;
  }
  
  /**
   * Execute the Reset MFA Command.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Arguments $args Command Arguments
   * @param  ConsoleIo $io   Console IO
   * @throws RuntimeException
   */
  
  public function execute(Arguments $args, ConsoleIo $io) {
    $Plugins = TableRegistry::getTableLocator()->get('Plugins');

    // First, update the plugin registry
    $Plugins->syncPluginRegistry();

    $action = $args->getOption('action');

    // We accept a comma separated list of plugin names
    foreach(explode(',', $args->getOption('plugin')) as $name) {
      // For each Plugin, we need to find the corresponding record in the Plugins table
      // in order to pass its record key to the relevant function call. This creates a
      // bit of extra work since those interfaces then look up the record by its key,
      // but this is not going to be a heavily used function.

      // syncPluginRegistry() will resolve plugins with the same name existing
      // in multiple directories by preference (AR-Plugin-5), so we should only
      // ever find one record.

      $cfg = $Plugins->find()->where(['plugin' => $name])->firstOrFail();

      // In general, the functions we support calling will handle error detection
      // (eg: trying to activate an already active plugin)
      $Plugins->$action($cfg->id);

      $io->out(__d('command', 'pl.action.done', [$action, $name]));
    }
  }
}
