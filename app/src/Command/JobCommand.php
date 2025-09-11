<?php
/**
 * COmanage Registry Job Command
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
use Cake\Event\EventManager;
use Cake\Utility\Security;
use App\Lib\Enum\JobStatusEnum;
use App\Lib\Events\CoIdEventListener;

class JobCommand extends BaseCommand
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
    $parser->addOption(
      'co_id',
      [
        'required'  => true,
        'short'     => 'c',
        'help'      => __d('command', 'opt.co_id')
      ]
    )->addOption(
      'job',
      [
        'required'  => false,
        'short'     => 'j',
        'help'      => __d('command', 'opt.job.plugin')
      ]
    )->addOption(
      'parallel',
      [
        'required'  => false,
        'short'     => 'p',
        'default'   => '1',
        'help'      => __d('command', 'opt.job.parallel')
      ]
    )->addOption(
      'max',
      [
        'required'  => false,
        'short'     => 'm',
        'default'   => '10',
        'help'      => __d('command', 'opt.job.max')
      ]
    )->addOption(
      'run',
      [
        'short'     => 'r',
        'boolean'   => true,
        'help'      => __d('command', 'opt.job.run')
      ]
    )->addOption(
      'synchronous',
      [
        'short'     => 's',
        'boolean'   => true,
        'help'      => __d('command', 'opt.job.synchronous')
      ]
    );

    return $parser;
  }

  /**
   * Execute the Job Command.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Arguments  $args  Command Arguments
   * @param  ConsoleIo  $io    Console IO
   */

  public function execute(Arguments $args, ConsoleIo $io)
  {
    $CosTable = $this->getTableLocator()->get('Cos');
    $JobTable = $this->getTableLocator()->get('Jobs');

    if($args->getOption('run')) {
      // Run the Job queue

      $coIds = [];

      if($args->getOption('co_id') == 'all') {
        $cos = $CosTable->find()
                        ->where(['status' => \App\Lib\Enum\SuspendableStatusEnum::Active])
                        ->toArray();

        $coIds = \Cake\Utility\Hash::extract($cos, '{n}.id');
      } else {
        $coIds[] = (int)$args->getOption('co_id');

        // Verify that the requested CO exists and is active
        $co = $CosTable->get($coIds[0]);

        if(!$co->isActive()) {
          throw new \InvalidArgumentException(__d('error', 'Cos.active', [$coIds[0]]));
        }
      }

      // The set of PIDs launched
      $pids = [];
      // The total number of actively running children
      $pidcount = 0;

      // The maximum number of runners to run at any one time
      $max = (int)$args->getOption('max');
      // The number of parallel runners for each CO
      $parallel = (int)$args->getOption('parallel');

      // The maximum number of jobs a queue runner will process before exiting
      $maxjobs = 100;

      foreach($coIds as $coId) {
        // We probably need to do something like this (from synchronous running, below)
        //       $CoIdEventListener = new CoIdEventListener((int)$args->getOption('co_id'));
        //      EventManager::instance()->on($CoIdEventListener);
        // but this wouldn't remove the previous $coId, so for now Sync Jobs can't be run
        // via the queue. See CFM-400.

        // We start counting from 1 rather than 0 to simplify console output
        for($i = 1;$i <= $parallel;$i++) {
          $io->out(__d('command', 'job.run.start', [$i, $parallel, $coId]));

          $newPid = pcntl_fork();

          switch($newPid) {
            case -1:
              throw new \RuntimeException('fork failed');
              break;
            case 0:
              // We are the child, process jobs from the requested CO's queue.
              // We'll run up to 100 jobs, the same behavior as v4, then exit.
              // This could become configurable at some point.

              // We need to open a new database connection for the child after the fork
              // so we don't run into problems with other processes (the parent or more
              // likely the other children) closing the connection. We'll use the default
              // configuration and create a new configuration on the fly so we don't have
              // to pollute the database config file.

              ConnectionManager::setConfig('plugin', ConnectionManager::getConfig('default'));
// XXX this doesn't seem to work, so plugins must always access the 'plugin' database, at least for now (CFM-253)
//              ConnectionManager::alias('plugin', 'default');
              $cxn = ConnectionManager::get('plugin');

              $JobTable->setConnection($cxn);

              for($j = 1;$j <= $maxjobs;$j++) {
                $io->verbose(__d('command', 'job.run.child.request', [$i, $j, $coId]));

                // Request a job to run
                $job = $JobTable->assignNext($coId);

                if(!$job) {
                  // Nothing to do, exit
                  $io->verbose(__d('command', 'job.run.child.done.empty', [$newPid]));
                  exit;
                }

                $io->verbose(__d('command', 'job.run.child.running', [$newPid, $job->id]));

                try {
                  $JobTable->process($job);
                }
                catch(\Exception $e) {
                  // The only Exception would be if the Job is in an invalid state,
                  // which shouldn't happen because we just ran assignNext()
                  $io->error($e->getMessage());
                }
              }
              
              $io->verbose(__d('command', 'job.run.child.done.max', [$newPid, $maxjobs]));
              exit;
              break;
            default:
              // We are the parent, keep launching
              $pids[$newPid] = $coId;
              $pidcount++;
              break;
          }

          if($pidcount == $max) {
            $io->out(__d('command', 'job.run.max', [$max]));

            $status = -1;
            $pid = pcntl_waitpid(-1, $status);

            // Confirm the Job was properly finished.
            $JobTable->confirmFinished($pid);

            $io->verbose(__d('command', 'job.run.piddone', [$pid]));
            $pidcount--;
          }
        }
      }

      // We are the parent, and we're done launching queue runners. wait() for them.
      while($pidcount > 0) {
        $io->out(__d('command', 'job.run.waiting', $pidcount));

        $status = -1;
        $pid = pcntl_waitpid(-1, $status);

        // Confirm the Job was properly finished.
        $JobTable->confirmFinished($pid);

        $io->verbose(__d('command', 'job.run.piddone', [$pid]));

        $pidcount--;
      }
    } else {
      // We have a specific job to process. Note that JobCommand can't require -j
      // since the -r usage doesn't need it, so we have to check for it manually.

      $jobPlugin = $args->getOption('job');

      if(empty($jobPlugin)) {
        throw new \InvalidArgumentException(__d('error', 'Jobs.command.plugin'));
      }

      // Run the requested job synchronously?
      $synchronous = $args->getOption('synchronous');

      // Pull current user info
      $pwent = posix_getpwuid(posix_getuid());
      
      // Parse any provided parameters
      $params = [];

      foreach($args->getArguments() as $a) {
        $p = explode('=', $a, 2);

        $params[ $p[0] ] = $p[1];
      }

      $CoIdEventListener = new CoIdEventListener((int)$args->getOption('co_id'));
      EventManager::instance()->on($CoIdEventListener);

      $job = $JobTable->register(
        coId:             (int)$args->getOption('co_id'),
        plugin:           $jobPlugin,
        parameters:       $params,
        registerSummary:  __d('result', 'Jobs.registered', [$pwent['name'], $pwent['uid']]),
        synchronous:      $synchronous
      );

      $io->out(__d('command', 'job.registered', [$job->id]));

      if($synchronous) {
        $io->out(__d('command', 'job.process', [$job->id]));

        $JobTable->process($job);
      }
    }
  }
}