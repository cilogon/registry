<?php
/**
 * COmanage Database Command
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

use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

use App\Lib\Util\SchemaManager;

class DatabaseCommand extends Command {
  /**
   * Build an Option Parser.
   *
   * @since  COmanage Registry v5.0.0
   * @param  ConsoleOptionParser $parser ConsoleOptionParser
   * @return ConsoleOptionParser         ConsoleOptionParser
   */
  
  protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser {
    $parser->addOption('not', [
      'short' => 'n',
      'boolean' => true,
      'help'  => __d('command', 'opt.not')
    ]);

    return $parser;
  }
  
  /**
   * Execute the Database Command.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Arguments $args Command Arguments
   * @param  ConsoleIo $io   Console IO
   * @throws RuntimeException
   */
  
  public function execute(Arguments $args, ConsoleIo $io) {
    $SchemaManager = new SchemaManager(io: $io);

    // First apply the core schema
    $schemaFile = ROOT . DS . 'config' . DS . 'schema' . DS . 'schema.json';

    $io->out(__d('command', 'db.schema', [$schemaFile]));

    $SchemaManager->applySchemaFile(schemaFile: $schemaFile,
                                    diffOnly: $args->getOption('not'));

    // Next see which plugins are active and have database configurations
    $Plugins = TableRegistry::getTableLocator()->get('Plugins');

    // AR-Plugin-6 Only apply schemas from active plugins
    $activePlugins = $Plugins->find('active')->all();

    if(!empty($activePlugins)) {
      foreach($activePlugins as $p) {
        $pSchemaConfig = $Plugins->getPluginSchema($p);

        if($pSchemaConfig) {
          $io->out(__d('command', 'db.schema.plugin', [$p->plugin]));
          $SchemaManager->applySchemaObject($pSchemaConfig);
        } else {
          $io->out(__d('command', 'db.schema.plugin.none', [$p->plugin]));
        }
      }
    }

    if($args->getOption('not')) {
      $io->out(__d('command', 'db.noop'));
    } else {
      $io->out(__d('command', 'db.ok'));
    }
  }
}
