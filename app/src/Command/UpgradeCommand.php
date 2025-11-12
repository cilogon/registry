<?php
/**
 * COmanage Registry Upgrade Command
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;
use \App\Lib\Enum\GroupTypeEnum;
use \App\Lib\Util\PaginatedSqlIterator;
use \App\Lib\Util\SearchUtilities;

class UpgradeCommand extends BaseCommand
{
  use \Cake\ORM\Locator\LocatorAwareTrait;

  protected $io = null;

  // A list of known versions, must be semantic versioning compliant. The value
  // is a "blocker" if it is a version that prevents an upgrade from happening.
  // For example, if a user attempts to upgrade from 1.0.0 to 1.2.0, and 1.1.0
  // is flagged as a blocker, then the upgrade must be performed in two steps
  // (1.0.0 -> 1.1.0, then 1.1.0 -> 1.2.0). Without the blocker, an upgrade from
  // 1.0.0 to 1.2.0 is permitted.

  // A typical scenario for blocking is when a pre### step must run after an
  // earlier version's post### step. Because we don't have the capability
  // to run database updates on a per-release basis, we run all relevant pre
  // steps, then the database update, then all relevant post update steps.
  // So if (eg) the admin is upgrading from 1.0.0 past 1.1.0 to 1.2.0 and there
  // are no blockers, the order of operations is 1.1.0-pre, 1.2.0-pre, database,
  // 1.1.0-post, 1.2.0-post.

  // Make sure to keep this list in order so we can walk the array rather than 
  // compare version strings. You must specify the 'block' parameter. If you flag
  // a version as blocking, be sure to document why.

  // As of v5, pre and post are now a list of tasks instead of a single function.

  protected $versions = [
    "5.0.0" => [
      'block' => false
    ],
    "5.1.0" => [
      'block' => false,
      'post' => ['installMostlyStaticPages']
    ],
    "5.2.0" => [
      'block' => false,
      'pre' => [
        'checkGroupNames'
      ],
      'post' => [
        'asssignUuids',
        'buildGroupTree',
        'createDefaultGroups', 
        'installMostlyStaticPages'
      ]
    ]
  ];
  
  // For descriptions of task parameters, see dispatch(). We store these separately
  // to make them easier to use regardless of context (pre/post/manual).

  protected $taskParams = [
    'assignUuids' => ['global' => true],
    'buildGroupTree' => ['global' => true],
    'checkGroupNames' => ['global' => true],
    'createDefaultGroups' => ['perCO' => true, 'perCOU' => true],
    'installMostlyStaticPages' => ['perCO' => true]
  ];

  /**
   * Register command specific options.
   *
   * @param  ConsoleOptionParser  $parser   Console Option Parser
   * @return ConsoleOptionParser            Console Option Parser
   * @since  COmanage Registry v5.1.0
   */

  public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
  {
    $parser->addOption(
      'forcecurrent',
      [
        'short'     => 'f',
        'help'      => __d('command', 'opt.upgrade.forcecurrent'),
        'required'  => false,
        'boolean'   => true
      ]
    )->addOption(
      'skipdatabase',
      [
        'short'     => 'D',
        'help'      => __d('command', 'opt.upgrade.skipdatabase'),
        'required'  => false,
        'boolean'   => true
      ]
    )->addOption(
      'skipvalidation',
      [
        'short'     => 'X',
        'help'      => __d('command', 'opt.upgrade.skipvalidation'),
        'required'  => false,
        'boolean'   => true
      ]
    )->addOption(
      'task',
      [
        'short'     => 't',
        'help'      => __d('command', 'opt.upgrade.task'),
        'required'  => false
      ]
    )->addOption(
      'version',
      [
        'help'      => __d('command', 'opt.upgrade.version'),
        'required'  => false
      ]
    );

    return $parser;
  }

  /**
   * Execute the Setup Command.
   *
   * @since  COmanage Registry v5.1.0
   * @param  Arguments  $args  Command Arguments
   * @param  ConsoleIo  $io    Console IO
   */

  public function execute(Arguments $args, ConsoleIo $io)
  {
    global $argv;

    $this->io = $io;

    // Are we being asked to run a specific task?
    $task = $args->getOption('task');

    if(!empty($task)) {
      $this->dispatch($task);
    } else {
      // We're running the standard update logic

      // Pull current (PHP code) version
      $targetVersion = null;
      
      if(!empty($args->getArgumentAt(0))) {
        // Use requested target version
        $targetVersion = $args->getArgumentAt(0);
      } else {
        // Read the current release from the VERSION file
        $targetVersion = rtrim(file_get_contents(CONFIG . DS . "VERSION"));
      }

      $MetaTable = $this->getTableLocator()->get('Meta');

      $currentVersion = $MetaTable->getUpgradeVersion();

      $this->io->out(__d('information', 'ug.version.current', [$currentVersion]));
      $this->io->out(__d('information', 'ug.version.target', [$targetVersion]));

      if(!$args->getOption('skipvalidation')) {
        // Validate the version path

        try {
          $this->validateVersions($currentVersion, $targetVersion);
        }
        catch(Exception $e) {
          $this->out($e->getMessage());
          return;
        }
      }

      // Run appropriate pre-database steps
      
      $fromFound = false;
      
      foreach($this->versions as $version => $params) {
        if($version == $currentVersion) {
          // Note we don't actually want to run the steps for $currentVersion
          $fromFound = true;
          continue;
        }
        
        if(!$fromFound) {
          // We haven't reached the from version yet
          continue;
        }
        
        if(!empty($params['pre'])) {
          $this->io->out(__d('information', 'ug.tasks.pre', [$version]));

          foreach($params['pre'] as $task) {
            $this->dispatch($task);
          }
        }
        
        if($version == $targetVersion) {
          // We're done
          break;
        }
      }
      
      if(!$args->getOption('skipdatabase')) {
        // Call database command
        $this->executeCommand(DatabaseCommand::class);
      }

      // Run appropriate post-database steps
      
      $fromFound = false;
      
      foreach($this->versions as $version => $params) {
        if($version == $currentVersion) {
          // Note we don't actually want to run the steps for $currentVersion
          $fromFound = true;
          continue;
        }
        
        if(!$fromFound) {
          // We haven't reached the from version yet
          continue;
        }
        
        if(!empty($params['post'])) {
          $this->io->out(__d('information', 'ug.tasks.post', [$version]));
          
          foreach($params['post'] as $task) {
            $this->dispatch($task);
          }
        }
        
        if($version == $targetVersion) {
          // We're done
          break;
        }
      }

      // Now that we're done, update the current version
      $MetaTable->setUpgradeVersion($targetVersion);
    }
  }

  /**
   * Dispatch a task over each CO on the platform.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string $task   Task to dispatch
   */

  protected function dispatch(string $task) {
    // We support tasks being flagged to behave in certain ways.

    //  global: The task should be run once
    //  perCO: The task should be run once per CO
    //  perCOU: The task should be run once per COU
    //   - perCOU does NOT imply perCO, though tasks will also be passed the CO ID

    $global = isset($this->taskParams[$task]['global'])
               && $this->taskParams[$task]['global'];
    $perCO = isset($this->taskParams[$task]['perCO'])
               && $this->taskParams[$task]['perCO'];
    $perCOU = isset($this->taskParams[$task]['perCOU'])
               && $this->taskParams[$task]['perCOU'];

    if(method_exists($this, $task)) {
      if($global) {
        $this->io->out(__d('information', 'ug.tasks.'.$task));
        $this->$task();
      }

      $cos = [];

      if($perCO || $perCOU) {
        // Pull the list of COs once. We'll generally apply changes to _all_ COs, 
        // even if they're Suspended or Templates.

        $CosTable = $this->getTableLocator()->get('Cos');

        $cos = $CosTable->find()->all();
      }

      if($perCO) {
        foreach($cos as $co) {
          $this->io->out(__d('information', 'ug.tasks.'.$task.'.co', [$co->id]));
          $this->$task(coId: $co->id);
        }
      }

      if($perCOU) {
        $CousTable = $this->getTableLocator()->get('Cous');

        // We iterate per CO rather than pull all COUs at once in case the task
        // wants to know the CO for each COU.

        foreach($cos as $co) {
          $cous = $CousTable->find()->where(['co_id' => $co->id])->all();

          foreach($cous as $cou) {
            $this->io->out(__d('information', 'ug.tasks.'.$task.'.cou', [$cou->id]));
            $this->$task(coId: $co->id, couId: $cou->id);
          }
        }
      }

      $this->io->out(__d('result', 'ug.task.done', [$task]));
    } else {
      $this->io->err(__d('error', 'ug.task.unknown', [$task]));
    }
  }

  /**
   * Assign UUIDs for existing duplicatable objects.
   * 
   * @since  COmanage Registry v5.2.0
   */
  
  protected function assignUuids() {
    // Because UUIDs are globally unique, we don't have to assign them on a per CO basis.
    
    foreach(SearchUtilities::getClonableModels() as $m) {
      // We basically have to walk all records of each model. We use PaginatedSqlIterator
      // for all queries for consistency, although only a small number (People, mostly)
      // will really need it. This will also guarantee all records get a UUID, even those
      // that might be created while this task is running.

      $Table = $this->getTableLocator()->get($m);

      $iterator = new PaginatedSqlIterator($Table);

      $this->io->out(__d('information', 'ug.tasks.assignUuids.count', [$iterator->count(), $m]));

      foreach($iterator as $entity) {
        if(!$entity->uuid) {
          $entity->uuid = \Cake\Utility\Text::uuid();

         try {
            $Table->saveOrFail($entity);
          }
          catch(\Exception $e) {
            $this->io->err($m . " " . $entity->id  . ": " . $e->getMessage());
          }

          // No need to provision
        }
      }
    }
  }

  /**
   * Establish tree metadata for Groups.
   * 
   * @since  COmanage Registry v5.2.0
   */

  protected function buildGroupTree() {
    // Although we partition Groups by CO, tree metadata applies to the table
    // as a whole, so we only need to run this once.

    $GroupsTable = $this->getTableLocator()->get('Groups');
    $GroupsTable->recover();
  }

  /**
   * Check that no Standard Groups have colons in their names.
   * 
   * @since  COmanage Registry v5.2.0
   */
  
  protected function checkGroupNames() {
    // Because it's not clear how to resolve conflicting Group names, we simply throw
    // an error and require the administrator to figure out what to do.

    $GroupsTable = $this->getTableLocator()->get('Groups');

    // AR-Group-9 Standard Group names may not use colons (:) and Standard Groups may not be named CO.
    $problemGroups = $GroupsTable->find()
                                 ->where([
                                  'OR' => [
                                    'name LIKE' => '%:%',
                                    'name' => 'CO'
                                  ],
                                  'group_type' => GroupTypeEnum::Standard
                                 ])
                                 ->all();
    
    if($problemGroups->count() > 0) {
      $this->io->err(__d('error', 'ug.task.checkGroupNames.invalid', [$problemGroups->count()]));

      foreach($problemGroups as $pg) {
        $this->io->err($pg->id . " = " . $pg->name);
      }

      throw new \RuntimeException(__d('error', 'ug.task.checkGroupNames.invalid', [$problemGroups->count()]));
    }
  }

  /**
   * Create Approver and MFA Exemption Groups.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int  $coId   CO ID
   * @param  int  $couId  COU ID
   */

  protected function createDefaultGroups(int $coId, ?int $couId=null) {
    $GroupsTable = $this->getTableLocator()->get('Groups');

    // Technically this will try to add all the default Groups, which is fine since
    // it will skip the ones that already exist.
    $GroupsTable->addDefaults($coId, $couId);
  }

  /**
   * Update the default set of Mostly Static Pages.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $coId   CO ID
   */

  protected function installMostlyStaticPages(int $coId) {
    $MspsTable = $this->getTableLocator()->get('MostlyStaticPages');

    $MspsTable->addDefaults($coId);
  }

  /**
   * Validate the requested from and to versions.
   *
   * @since  COmanage Registry v5.1.0
   * @param  string $from   "From" version (current database)
   * @param  string $to     "To" version (current codebase)
   * @return bool           true if the requested range is valid
   * @throws InvalidArgumentException
   */
  
  protected function validateVersions(string $from, string $to): bool {
    // First make sure these are valid versions
    
    if(!array_key_exists($from, $this->versions)) {
      throw new \InvalidArgumentException(__d('error', 'ug.version.unknown', [$from]));
    }
    
    if(!array_key_exists($to, $this->versions)) {
      throw new \InvalidArgumentException(__d('error', 'ug.version.unknown', [$to]));
    }
    
    // If $from and $to are the same, nothing to do.
    
    if($from == $to) {
      throw new \InvalidArgumentException(__d('error', 'ug.version.same'));
    }
    
    // Walk through the version array and check our version path
    
    $fromFound = false;
    
    foreach($this->versions as $version => $params) {
      $blocks = $params['block'];
      
      if($version == $from) {
        $fromFound = true;
      } elseif($version == $to) {
        if(!$fromFound) {
          // Can't downgrade ($from must preceed $to)
          throw new \InvalidArgumentException(__d('error', 'ug.version.order'));
        } else {
          // We're good to go
          break;
        }
      } else {
        if($fromFound && $blocks) {
          // We can't pass a blocker version
          throw new \InvalidArgumentException(__d('error', 'ug.version.blocked', [$version]));
        }
      }
    }
    
    return true;
  }
}