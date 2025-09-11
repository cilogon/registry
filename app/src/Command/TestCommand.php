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
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;
use \App\Lib\Util\DeliveryUtilities;

class TestCommand extends BaseCommand
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
      'choices' => ['database', 'http', 'mail', 'setup']
    ])->addOption('datasource', [
      'help'    => __d('command', 'opt.test.database.source'),
      'default' => 'default'
    ])->addOption('http_server_id', [
      'help'    => __d('command', 'opt.test.http.http_server_id')
    ])->addOption('recipient', [
      'help'    => __d('command', 'opt.test.mail.recipient')
    ])->addOption('url', [
      'help'    => __d('command', 'opt.test.http.url')
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
      case 'http':
        $this->testHttp((int)$args->getOption('http_server_id'), $args->getOption('url'));
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
   * Test HTTP connectivity via a configured HttpServer and request URL.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $httpServerId   HttpServer ID
   * @param  string $url            URL to request (relative to HttpServer configured URL)
   * @return int                    Return Code (CODE_SUCCESS or CODE_ERROR)
   */

  protected function testHttp(int $httpServerId, string $url): int {
    try {
      $HttpServers = $this->getTableLocator()->get('CoreServer.HttpServers');

      $Client = $HttpServers->createHttpClient($httpServerId);

      $response = $Client->get($url);

      if(!$response->isOk() || $response->isRedirect()) {
        throw new \RuntimeException($response->getReasonPhrase());
      }
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
   * Test COmanage setup. Primarily intended for use by SetupCommand.
   *
   * @since  COmanage Registry v5.0.0
   * @return int            CODE_SUCCESS if the CO does NOT exist or CODE_ERROR if it does
   */

  protected function testSetup(): int {
    // Check if the COmanage CO already exists, and if so abort.

    $coTable = $this->getTableLocator()->get('Cos');
    $comanageCO = $coTable->find('COmanageCO')->first();

    if($comanageCO !== null) {
      $this->io->out(__d('command', 'se.already'));
      $this->abort(static::CODE_ERROR);
    }

    // Because this is primarily intended for use by SetupCommand, we want to return
    // SUCCESS if there is no COmanage CO, ie it is OK to proceed.
    return static::CODE_SUCCESS;
  }
}