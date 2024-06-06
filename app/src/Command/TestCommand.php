<?php
/**
 * COmanage Registry Test Command
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
use Cake\Datasource\ConnectionManager;
use \App\Lib\Util\DeliveryUtilities;

class TestCommand extends Command
{
  protected $io = null;

  /**
   * Register command specific options.
   *
   * @param  ConsoleOptionParser  $parser   Console Option Parser
   * @return ConsoleOptionParser            Console Option Parser
   * @since  COmanage Registry v5.0.0
   */

  public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
  {
    $parser->addOption('test', [
      'help'    => __d('command', 'opt.test.test'),
      'short'   => 't',
      'choices' => ['database', 'mail', 'setup']
    ])->addOption('datasource', [
      'help'    => __d('command', 'opt.test.database.source'),
      'default' => 'default'
    ])->addOption('recipient', [
      'help'    => __d('command', 'opt.test.mail.recipient')
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

    $this->io = $io;

    // The test we want to run
    $test = $args->getOption('test');

    switch($test) {
      case 'database':
        $this->testDatabase($args->getOption('datasource'));
        break;
      case 'mail':
        $this->testMail((int)$args->getOption('recipient'));
        break;
      case 'setup':
        $this->testSetup();
        break;
      default:
        $io->out("Command $test unknown");
        break;
    }
  }

  /**
   * Test database connectivity for the requested datasource.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $source Datasource
   * @return int            Return Code (CODE_SUCCESS or CODE_ERROR)
   */

  protected function testDatabase(string $source): int {
    try {
      $cxn = ConnectionManager::get($source);
      $this->io->out(__d('result', 'test.database.ok'));
    }
    catch(\Exception $e) {
      $this->io->error($e->getMessage());
      $this->abort(static::CODE_ERROR);
    }

    return static::CODE_SUCCESS;
  }

  /**
   * Test mail delivery to the specified address.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int $recipient Recipient Person ID
   * @return int            Return Code (CODE_SUCCESS or CODE_ERROR)
   */

  protected function testMail(int $recipient): int {
    try {
      DeliveryUtilities::sendEmailToPerson(
        personId:   $recipient,
        subject:    "TestCommand Test Message",
        body_text:  "This is the test message requested via TestCommand."
      );
      $this->io->out(__d('result', 'test.mail.ok'));
    }
    catch(\Exception $e) {
      $this->io->error($e->getMessage());
      $this->abort(static::CODE_ERROR);
    }

    return static::CODE_SUCCESS;
  }

  /**
   * Test COmanage setup
   *
   * @since  COmanage Registry v5.0.0
   * @return int            Return Code (CODE_SUCCESS or CODE_ERROR)
   */

  protected function testSetup(): int {

    // Check if the COmanage CO already exists, and if so abort.

    $coTable = $this->getTableLocator()->get('Cos');
    $query = $coTable->find();
    $comanageCO = $coTable->findCOmanageCO($query)->first();

    if($comanageCO !== null) {
      $this->io->out(__d('command', 'se.already'));
      $this->abort(static::CODE_ERROR);
    }

    return static::CODE_SUCCESS;
  }
}