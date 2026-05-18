<?php
/**
 * COmanage Registry Transmogrify Command
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

namespace Transmogrify\Command;

use App\Command\UpgradeCommand;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Traits\LabeledLogTrait;
use App\Lib\Util\DBALConnection;
use App\Lib\Util\StringUtilities;
use App\Model\Table\MetaTable;
use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Transmogrify\Lib\Enum\TransmogrifyEnum;
use Transmogrify\Lib\Traits\ActionCodeMapperTrait;
use Transmogrify\Lib\Traits\CacheTrait;
use Transmogrify\Lib\Traits\HookRunnersTrait;
use Transmogrify\Lib\Traits\ManageDefaultsTrait;
use Transmogrify\Lib\Traits\RowTransformationTrait;
use Transmogrify\Lib\Traits\TypeMapperTrait;
use Transmogrify\Lib\Util\CommandLinePrinter;
use Transmogrify\Lib\Util\DbInfoPrinter;
use Transmogrify\Lib\Util\GroupsHealth;
use Transmogrify\Lib\Util\IndexManager;
use Transmogrify\Lib\Util\OrgIdentitiesHealth;
use Transmogrify\Lib\Util\RawSqlQueries;
use Transmogrify\Service\ConfigLoaderService;
use Transmogrify\Service\DbInfoService;

class TransmogrifyCommand extends BaseCommand {
  use CacheTrait;
  use TypeMapperTrait;
  use ActionCodeMapperTrait;
  use RowTransformationTrait;
  use ManageDefaultsTrait;
  use HookRunnersTrait;
  use LabeledLogTrait;

  // Tables configuration, loaded from JSON file and extended with schema info.
  protected array $tables = [];

  // Meta table for version tracking
  protected MetaTable $metaTable;

  // Array to track boolean fields
  protected array $booleans = [];

  // Make some objects more easily accessible
  protected ?DBALConnection $inconn = null;
  protected ?DBALConnection $outconn = null;

  // Shell arguments, for easier access
  protected ?Arguments $args = null;
  protected ?ConsoleIo $io = null;

  protected ?CommandLinePrinter $cmdPrinter = null;

  /** @var float Start time of command execution */
  private float $startTime;

  /** @var string Absolute path to plugin root directory */
  private string $pluginRoot;

  /**
   * Constructor
   *
   * @param DbInfoService $dbInfoService Database information service
   * @since COmanage Registry v5.2.0
   */
  public function __construct(
    private DbInfoService $dbInfoService,
    private ConfigLoaderService $configLoader,
    private IndexManager $indexManager,
  ) {
    $this->pluginRoot = dirname(__DIR__, 2);
    parent::__construct();
  }

  /**
   * Override run command
   *
   * @param array $argv
   * @param ConsoleIo $io
   * @return int
   * @since COmanage Registry v5.2.0
   */
  public function run(array $argv, ConsoleIo $io): int
  {
    $this->inconn = DBALConnection::factory($io, 'transmogrify');
    $this->outconn = DBALConnection::factory($io);
    return parent::run($argv, $io);
  }

  /**
   * Build an Option Parser.
   *
   * @since  COmanage Registry v5.2.0
   * @param  ConsoleOptionParser $parser ConsoleOptionParser
   * @return ConsoleOptionParser         ConsoleOptionParser
   */

  protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
  {
    // Allow overriding the tables config path
    $parser->addOption('tables-config', [
      'help' => 'Path to transmogrify tables JSON config',
      'default' => TransmogrifyEnum::TABLES_JSON_PATH
    ]);
    $parser->addOption('dump-tables-config', [
      'help' => 'Output the effective tables configuration (after schema extension) and exit',
      'boolean' => true
    ]);
    // Specify a table (or repeat option) to migrate only a subset
    $parser->addOption('table', [
      'help' => 'Migrate only the specified table. Repeat the option to migrate multiple tables',
      'multiple' => true
    ]);
    // List available target tables and exit
    $parser->addOption('list-tables', [
      'help' => 'List available target tables from the transmogrify config and exit',
      'boolean' => true
    ]);
    // Info options integrated into TransmogrifyCommand
    $parser->addOption('info', [
      'help' => 'Print source and target database configuration and exit',
      'boolean' => true
    ]);
    $parser->addOption('info-json', [
      'help' => 'Output info in JSON (use with --info)',
      'boolean' => true
    ]);
    $parser->addOption('info-ping', [
      'help' => 'Ping connections and include connectivity + server version (use with --info or --info-schema)',
      'boolean' => true
    ]);
    $parser->addOption('info-schema', [
      'help' => 'Print schema information and whether the database is empty (defaults to target). Use --info-schema-role to select source/target',
      'boolean' => true
    ]);
    $parser->addOption('info-schema-role', [
      'help' => 'When using --info-schema, which database to inspect: source or target (default: target)'
    ]);
    $parser->addOption('login-identifier-copy', [
      'help' => __d('command', 'tm.login-identifier-copy'),
      'boolean' => true
    ]);

    $parser->addOption('login-identifier-type', [
      'help' => __d('command', 'tm.login-identifier-type')
    ]);

    // Health report option (Org Identities readiness)
    $parser->addOption('orgidentities-health', [
      'help' => 'Run Org Identities health check (eligibility/exclusion breakdown) and exit',
      'boolean' => true
    ]);
    // Health report option (Groups naming rule readiness)
    $parser->addOption('groups-health', [
      'help' => 'Run Groups health check (AR-Group-9: invalid Standard names) and exit',
      'boolean' => true
    ]);
    // Optional: replace colons in Standard group names during migration (opt-in, off by default)
    $parser->addOption('groups-colon-replacement', [
      'help' => 'If set, replace ":" with this value in Standard group names during migration. WARNING: name "CO" remains invalid and is not auto-renamed.'
    ]);
    // Convenience flag for using a literal dash as replacement
    $parser->addOption('groups-colon-replacement-dash', [
      'help' => 'Use "-" as the replacement for ":" in Standard group names (shorthand when passing a lone "-" is problematic)',
      'boolean' => true
    ]);
    $parser->addOption('plugin-bootstrap', [
      'help' => 'Initialize plugin registry and activate non-required plugins (eg, passwords, ssh keys) before transmogrification',
      'boolean' => true
    ]);

    $parser->setEpilog(__d('command', 'tm.epilog'));

    return $parser;
  }

  /**
   * Execute the Transmogrify Command.
   *
   * @param Arguments $args Command Arguments
   * @param ConsoleIo $io Console IO
   * @throws Exception
   * @since  COmanage Registry v5.2.0
   */

  public function execute(Arguments $args, ConsoleIo $io): int
  {
    $this->args = $args;
    $this->io = $io;

    // Now that BaseCommand set verbosity, construct the printer so it can detect it correctly 
    $this->cmdPrinter = new CommandLinePrinter($io, 'green', 50, true);

    // Start tracking execution time
    $this->startTime = microtime(true);

    // Initialize the index manager with the live target connection and printer
    $this->indexManager->initialize($this->outconn, $this->cmdPrinter);

    // Validate "info" option combinations and handle errors
    $code = $this->validateInfoOptions($io);
    if ($code !== null) {
      return $code;
    }

    // Handle info modes early (no tables config needed unless ping)
    $code = $this->maybeHandleInfo();
    if ($code !== null) {
      return $code;
    }

    // Health report: run and exit
    if ($this->args->getOption('orgidentities-health')) {
      OrgIdentitiesHealth::run($this->inconn, $this->io);
      return BaseCommand::CODE_SUCCESS;
    }
    if ($this->args->getOption('groups-health')) {
      GroupsHealth::run($this->inconn, $this->io);
      return BaseCommand::CODE_SUCCESS;
    }

    // Load tables configuration (from JSON) and extend it with schema data
    $this->loadTablesConfig();

    // Dump tables config if requested
    $code = $this->maybeDumpTablesConfig($io);
    if ($code !== null) {
      return $code;
    }

    // List tables and exit
    $code = $this->maybeListTables($io);
    if ($code !== null) {
      return $code;
    }

    // Build list of tables to migrate from --table option and positional args
    $selected = $this->buildSelectedTables($args);

    // Validate and warn for subset selection
    $code = $this->maybeValidateSelectedTables($selected, $io);
    if ($code !== null) {
      return $code;
    }

    // Determine the actual list of tables to process.
    // If specific tables are selected, we recursively resolve dependencies and sort them
    // according to the configuration order.
    // Otherwise, we process all tables in configuration order.
    $tablesToProcess = !empty($selected)
      ? $this->resolveDependencies($selected)
      : array_keys($this->tables);

    if (!empty($selected)) {
      $this->cmdPrinter->info('Tables to process (including dependencies):');
      foreach ($tablesToProcess as $table) {
        $this->cmdPrinter->info('  - ' . $table);
      }
    }

    // Register the current version for future upgrade purposes
    $this->metaTable = TableRegistry::getTableLocator()->get('Meta');
    $this->metaTable->setUpgradeVersion();

    // Track remaining selected tables (if any) so we can exit early when done.
    // Note: $selected contains only the explicitly requested tables, not dependencies.
    $pendingSelected = [];
    if (!empty($selected)) {
      $pendingSelected = array_fill_keys($selected, true);
    }

    // Plugin bootstrap is now optional and controlled by the CLI flag
    if ($this->args->getOption('plugin-bootstrap')) {
      try {
        $this->pluginBootstrap();
        return BaseCommand::CODE_SUCCESS;
      } catch (\Throwable $e) {
        $this->cmdPrinter?->error('Plugin bootstrap failed: ' . $e->getMessage());
        return BaseCommand::CODE_ERROR;
      }
    }

    foreach ($tablesToProcess as $t) {
      // Check per-table skip configuration and optionally prompt user
      $canSkipCfg = $this->tables[$t]['canSkip'] ?? null;
      if (filter_var($canSkipCfg, FILTER_VALIDATE_BOOLEAN)) {
        $question = sprintf(
          'Table "%s" (%s) may be skipped.' . PHP_EOL . 'Skip transmogrification? yes/no [default: no]',
          Inflector::classify($t),
          $t
        );
        $reply = $this->cmdPrinter->ask($question . ' ');
        $replyBool = filter_var($reply, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($replyBool === true) {
          $this->cmdPrinter->info(sprintf('Skipping transmogrification for table %s as requested.', $t));

          // We need to skip the petiton metadata migration if the petitions table is not present
          if ($t === 'petitions') {
            unset($this->tables['historic_petition_metadata_records']);
            unset($this->tables['historic_petition_attributes']);
          }
          continue;
        }
        // Proceed for false, null, or empty responses
        $this->cmdPrinter->info(sprintf('Proceeding with transmogrification for table %s.', $t));
      }

      // Initializations per table migration
      $outboundTableEmpty = true;
      $inboundQualifiedTableName = $this->inconn->qualifyTableName($this->tables[$t]['source']);
      $outboundQualifiedTableName = $this->outconn->qualifyTableName($t);
      $Model = TableRegistry::getTableLocator()->get($t);

      $this->cmdPrinter->out(message: sprintf("Transmogrifying table: %s(%s)", Inflector::classify($t), $t));


      /*
       *  Run checks before processing the table
       **/

      // Check if source table exists and warn if not present
      if (!empty($this->tables[$t]['source'])) {
        $src = $this->tables[$t]['source'];
        if (!$this->tableExists($src)) {
          $this->cmdPrinter->warning("Source table '$src' does not exist in source database, skipping table '$t'");
          continue;
        }
      }

      // Skip a table if already contains data
      if ($Model->find()->count() > 0) {
        $outboundTableEmpty = false;
        $this->cmdPrinter->warning("Table (" . $t . ") is not empty. We will not overwrite existing data.");
      }

      // Mark the table as skipped if it is not empty since we have to process all the tables in the $tablesToProcess array
      $this->cache['skipInsert'][$outboundQualifiedTableName] = !$outboundTableEmpty;
      $this->cache['current'] = $outboundQualifiedTableName;
      /*
       * End of checks
       */


      // Configure sequence ID for the target table
      if (!RawSqlQueries::setSequenceId(
        $this->inconn,
        $this->outconn,
        $this->tables[$t]['source'],
        $t,
        $this->cmdPrinter
      )) {
        $this->cmdPrinter->warning("Skipping Transmogrification. Can not properly configure the Sequence for the primary key for the Table (\"$t\"");
        return BaseCommand::CODE_ERROR;
      }


      // Execute any pre-processing hooks for the current table
      $this->runPreTableHook($t);

      // Step 8: Build and execute query to fetch all source records
      $insql = match (true) {
        !empty($this->tables[$t]['sqlSelect']) => $this->runSqlSelectHook($t, $inboundQualifiedTableName),
        default => RawSqlQueries::buildSelectAllOrderedById($inboundQualifiedTableName)
      };

      // Verbose message to show the SQL query being executed
      $this->cmdPrinter->verbose(sprintf('[Inbound SQL] Table=%s | %s', $t, $insql));
      // Fetch the inbound data.
      $stmt = $this->inconn->executeQuery($insql);

      /*
       * PROGRESS STARTING
       **/
      // If a custom SELECT is used, count the exact result set; otherwise count the whole table
      if (!empty($this->tables[$t]['sqlSelect'])) {
        $countSql = RawSqlQueries::buildCountFromSelect($insql);
        $count = (int)$this->inconn->fetchOne($countSql);
      } else {
        $count = (int)$this->inconn->fetchOne(RawSqlQueries::buildCountAll($inboundQualifiedTableName));
      }
      $this->cmdPrinter->start($count);
      $tally = 0;
      $this->cache['error'] = 0;
      $this->cache['warns'] = 0;

      // Drop non-PK indexes before the bulk load to avoid per-row index maintenance overhead.
      // They will be recreated in a single pass after all rows are inserted.
      $indexesDisabled = false;
      if ($this->cache['skipInsert'][$outboundQualifiedTableName] === false) {
        try {
          $this->indexManager->disableIndexes($outboundQualifiedTableName);
          $indexesDisabled = $this->indexManager->hasSavedIndexes($outboundQualifiedTableName);
        } catch (\Throwable $e) {
          $this->cmdPrinter->warning(
            'Could not drop indexes on ' . $outboundQualifiedTableName . ': ' . $e->getMessage()
          );
          $this->cmdPrinter->warning('Proceeding with indexes in place (slower).');
        }
      }

      while ($row = $stmt->fetchAssociative()) {
        if (!empty($row[$this->tables[$t]['displayField']])) {
          $displayMessage = "$t " . $row[$this->tables[$t]['displayField']];
          $this->cmdPrinter->verbose($displayMessage);
        }

        try {
          // Create a copy of the original row data to preserve it for post-processing
          $origRow = $row;

          // Execute any pre-processing hooks to transform or validate the row data
          $this->runPreRowHook($t, $origRow, $row);

          // Set changelog defaults (created/modified timestamps, user IDs)
          // Must be done before boolean normalization as it adds new fields
          $this->populateChangelogDefaults(
            $t,
            $row,
            isset($this->tables[$t]['addChangelog']) && $this->tables[$t]['addChangelog']
          );

          // Convert boolean values to database-compatible format
          $this->normalizeBooleanFieldsForDb($t, $row);

          // Map old field names to new schema field names
          $this->mapLegacyFieldNames($t, $row);

          // Insert the transformed row into the target database
          if ($this->cache['skipInsert'][$outboundQualifiedTableName] === false) {
            // Check if a parent record for this row was previously rejected; if so, skip this insert
            if ($this->skipIfRejectedParent(currentTable: $t, row: $row)) {
              continue;
            }

            $this->outconn->insert($outboundQualifiedTableName, $row);
            // Execute any post-processing hooks after successful insertion
            $this->runPostRowHook($t, $origRow, $row);
          }

          // Store row data in cache for potential later use.
          // This happens even if insert is skipped (which is the goal of handling dependencies).
          $this->cacheResults($t, $row, $origRow);
        } catch (ForeignKeyConstraintViolationException $e) {
          // A foreign key associated with this record did not load, so we can't
          // load this record. This can happen, eg, because the source_field_id
          // did not load, perhaps because it was associated with an Org Identity
          // not linked to a CO Person that was not migrated.
          $this->cache['warns'] += 1;
          $rowIdLabel = $row['id'] ?? ($this->tables[$t]['displayField'] ?? 'n/a');
          if (isset($row['id'])) {
            $this->cache['rejected'][$outboundQualifiedTableName][$row['id']] = $row;
          }
          $this->cmdPrinter->warning("Skipping $t record " . (string)$rowIdLabel . " due to invalid foreign key: " . $e->getMessage());
//          $this->cmdPrinter->pause();
        } catch (\InvalidArgumentException $e) {
          // If we can't find a value for mapping we skip the record
          // (ie: mapLegacyFieldNames basically requires a successful mapping)
          $this->cache['warns'] += 1;
          $rowIdLabel = $row['id'] ?? ($this->tables[$t]['displayField'] ?? 'n/a');
          if (isset($row['id'])) {
            $this->cache['rejected'][$outboundQualifiedTableName][$row['id']] = $row;
          }
          $this->cmdPrinter->warning("Skipping $t record " . (string)$rowIdLabel . ": " . $e->getMessage());
//          $this->cmdPrinter->pause();
        } catch (\Exception $e) {
          $this->cache['error'] += 1;
          if (isset($row['id'])) {
            $this->cache['rejected'][$outboundQualifiedTableName][$row['id']] = $row;
          }
          $rowIdLabel = $row['id'] ?? ($this->tables[$t]['displayField'] ?? 'n/a');
          $this->cmdPrinter->error("$t record " . (string)$rowIdLabel . ": " . $e->getMessage());
          $this->cmdPrinter->pause();
        }

        $tally++;
        // Always delegate progress updates to the printer; it will decide what to draw
        $this->cmdPrinter->update($tally);
      }

      $this->cmdPrinter->finish();
      /**
       * FINISH PROGRESS
       */

      // Recreate indexes that were dropped before the bulk load
      if ($indexesDisabled) {
        try {
          $this->indexManager->enableIndexes($outboundQualifiedTableName);
        } catch (\Throwable $e) {
          $this->cmdPrinter->error(
            'Failed to recreate indexes on ' . $outboundQualifiedTableName . ': ' . $e->getMessage()
          );
          $this->cmdPrinter->error(
            'You may need to manually recreate indexes. Run: bin/cake database'
          );
        }
      }

      // Output final warning and error counts for the table
      $this->cmdPrinter->warning(sprintf('Warnings: %d', $this->cache['warns']));
      $this->cmdPrinter->error(sprintf('Errors: %d', $this->cache['error']));

      // Execute any post-processing hooks for the table
      if ($this->cache['skipInsert'][$outboundQualifiedTableName] === false) {
        $this->cmdPrinter->out('Running post-table hook for ' . $t);
        $this->runPostTableHook($t);
      }

      // If user selected a subset, exit as soon as all explicitly selected tables are processed
      if (!empty($pendingSelected) && isset($pendingSelected[$t])) {
        unset($pendingSelected[$t]);
        if (empty($pendingSelected)) {
          $this->cmdPrinter->out('All selected tables have been processed. Exiting.');
          return BaseCommand::CODE_SUCCESS;
        }
      }

      // Prompt for confirmation before processing table
      // Note: we use $tablesToProcess for the index lookup to find the next table correctly
      $currentIndex = array_search($t, $tablesToProcess);
      if (isset($tablesToProcess[$currentIndex + 1])) {
        $this->cmdPrinter->info("Next table to process: " . $tablesToProcess[$currentIndex + 1]);
      } else {
        $this->cmdPrinter->out(PHP_EOL . "Table import complete. Exiting.");
      }

      $this->cmdPrinter->pause();
    }

    // Assign UUIDs for all clonable models
    $this->cmdPrinter->out('Running assignUuids task via UpgradeCommand...');
    $this->executeCommand(UpgradeCommand::class, ['-D', '-X', '-t', 'assignUuids'], $this->io);

    // Display total execution time
    $executionTime = microtime(true) - $this->startTime;

    $hours = floor($executionTime / 3600);
    $minutes = floor(($executionTime % 3600) / 60);
    $seconds = $executionTime % 60;

    $formatted = sprintf(
      '%02d:%02d:%02d',
      (int)$hours,
      (int)$minutes,
      (int)$seconds
    );

    $this->cmdPrinter->out(sprintf('Total execution time: %s (HH:MM:SS)', $formatted));

    return BaseCommand::CODE_SUCCESS;
  }


  /**
   * Validate incompatible/invalid "info" related options.
   * Returns an exit code when invalid, or null to continue.
   *
   * @param ConsoleIo $io Console IO object for output
   * @return int|null Command exit code or null to continue
   * @since COmanage Registry v5.2.0
   */
  private function validateInfoOptions(ConsoleIo $io): ?int
  {
    if (
      $this->args->getOption('info-ping')
      && !$this->args->getOption('info')
      && !$this->args->getOption('info-schema')
    ) {
      $io->err('Option --info-ping must be used together with --info or --info-schema.');
      $io->err('Examples:');
      $io->err('  bin/cake transmogrify --info --info-ping');
      $io->err('  bin/cake transmogrify --info-schema --info-ping [--info-schema-role source|target]');
      return BaseCommand::CODE_ERROR;
    }

    if (
      $this->args->getOption('info-schema-role') !== null
      && !$this->args->getOption('info-schema')
    ) {
      $io->err('Option --info-schema-role must be used together with --info-schema.');
      $io->err('Examples:');
      $io->err('  bin/cake transmogrify --info-schema --info-schema-role target');
      $io->err('  bin/cake transmogrify --info-schema --info-ping --info-schema-role source');
      $io->err('  bin/cake transmogrify --info-schema --info-json --info-schema-role source');
      return BaseCommand::CODE_ERROR;
    }

    return null;
  }

  /**
   * Handle --info / --info-schema early-exit modes.
   * Returns exit code if handled, or null to continue normal execution.
   *
   * @return int|null Command exit code if handled, null to continue execution
   * @since COmanage Registry v5.2.0
   */
  private function maybeHandleInfo(): ?int
  {
    if ($this->args->getOption('info')) {
      (DbInfoPrinter::initialize($this->io, $this->dbInfoService))->print(
        (bool)$this->args->getOption('info-json'),
        (bool)$this->args->getOption('info-ping')
      );
      return BaseCommand::CODE_SUCCESS;
    }

    if ($this->args->getOption('info-schema')) {
      $role = $this->args->getOption('info-schema-role') ?: 'target';
      if (!in_array($role, ['source', 'target'], true)) { $role = 'target'; }
      (DbInfoPrinter::initialize($this->io, $this->dbInfoService))->print(
        (bool)$this->args->getOption('info-json'),
        (bool)$this->args->getOption('info-ping'),
        true,
        $role
      );
      return BaseCommand::CODE_SUCCESS;
    }

    return null;
  }

  /**
   * Load tables configuration from JSON and attach to $this->tables.
   *
   * @return void
   * @since COmanage Registry v5.2.0
   */
  private function loadTablesConfig(): void
  {
    $path = $this->args->getOption('tables-config') ?? Transmogrify::TABLES_JSON_PATH;
    if (!str_starts_with($path, $this->pluginRoot . DS)) {
      $path = $this->pluginRoot . DS . $path;
    }
    $this->tables = $this->configLoader->load($path);
  }

  /**
   * If requested, dump effective tables configuration and exit.
   *
   * @param ConsoleIo $io Console IO object for output
   * @return int|null Command exit code if dumped, null to continue
   * @since COmanage Registry v5.2.0
   */
  private function maybeDumpTablesConfig(ConsoleIo $io): ?int
  {
    if ($this->args->getOption('dump-tables-config')) {
      $io->out(json_encode($this->tables, JSON_PRETTY_PRINT));
      return BaseCommand::CODE_SUCCESS;
    }
    return null;
  }

  /**
   * If requested, list available tables and exit.
   *
   * @param ConsoleIo $io Console IO object for output
   * @return int|null Command exit code if listed, null to continue
   * @since COmanage Registry v5.2.0
   */
  private function maybeListTables(ConsoleIo $io): ?int
  {
    if ($this->args->getOption('list-tables')) {
      $io->out(implode("\n", array_keys($this->tables)));
      return BaseCommand::CODE_SUCCESS;
    }
    return null;
  }

  /**
   * Build list of tables from --table options and positional args.
   *
   * @param Arguments $args Command arguments
   * @return array<int, string> List of selected table names
   * @since COmanage Registry v5.2.0
   */
  private function buildSelectedTables(Arguments $args): array
  {
    $selected = $args->getArrayOption('table') ?? [];
    $positional = $args->getArguments();
    if (!empty($positional)) {
      $selected = array_merge($selected, $positional);
    }
    return array_values(array_unique($selected));
  }

  /**
   * Recursively resolve dependencies for the selected tables.
   *
   * @param array $selected Selected tables
   * @return array Selected tables with dependencies included
   */
  private function resolveDependencies(array $selected): array
  {
    $queue = $selected;
    // Track visited tables to prevent infinite loops and re-processing.
    // We initialize this with the selected tables so we don't add them as dependencies of themselves.
    $visited = array_flip($selected);
    $dependencies = [];

    while (!empty($queue)) {
      $table = array_shift($queue);

      if (isset($this->tables[$table]['dependencies'])) {
        foreach ($this->tables[$table]['dependencies'] as $dependency) {
          if (!isset($visited[$dependency])) {
            $visited[$dependency] = true;
            // Track this as a discovered dependency
            $dependencies[$dependency] = true;
            $queue[] = $dependency;
            $this->cmdPrinter?->verbose("Adding dependency table '$dependency' for '$table'");
          }
        }
      }
    }

    $allTables = array_keys($this->tables);

    // 1. Sort the discovered dependencies according to the configuration file order
    $orderedDependencies = array_values(array_intersect($allTables, array_keys($dependencies)));

    // 2. Sort the explicitly selected tables according to the configuration file order
    $orderedSelected = array_values(array_intersect($allTables, $selected));

    // 3. Merge: Run dependencies first, then the selected tables
    return array_merge($orderedDependencies, $orderedSelected);
  }

  /**
   * Validate selected tables against config and warn about partial migration.
   * Returns exit code on error, or null if OK.
   *
   * @param array $selected List of selected table names
   * @param ConsoleIo $io Console IO object for output
   * @return int|null Command exit code on error, null if valid
   * @since COmanage Registry v5.2.0
   */
  private function maybeValidateSelectedTables(array $selected, ConsoleIo $io): ?int
  {
    if (!empty($selected)) {
      $unknown = array_diff($selected, array_keys($this->tables));
      if (!empty($unknown)) {
        $io->err('Unknown table(s): ' . implode(', ', $unknown));
        $io->err('Use --list-tables to see available options.');
        return BaseCommand::CODE_ERROR;
      }
      $io->warning('Migrating a subset of tables may lead to foreign key or type mapping warnings if dependencies are not loaded (eg, types, people, groups).');
      $io->out('Selected tables: ' . implode(', ', $selected));
    }
    return null;
  }

  /**
   * Bootstrap plugin state for transmogrification when requested via --plugin-bootstrap:
   *  - Ensure the Plugins table is in sync with plugins on disk.
   *  - Activate any plugins that are referenced in tables.json (via the "plugin" key),
   *    if they are present but currently suspended.
   *
   * For a table entry like:
   *   "servers": { "plugin": "CoreServer", ... }
   * the corresponding model will be "CoreServer.Servers".
   *
   * @return void
   * @since COmanage Registry v5.2.0
   */
  protected function pluginBootstrap(): void
  {
    $Plugins = TableRegistry::getTableLocator()->get('Plugins');

    $this->cmdPrinter?->info(PHP_EOL . 'Initializing plugin registry for transmogrification...');

    // 1. Make sure the registry reflects what is actually available on disk.
    $Plugins->syncPluginRegistry();

    // 2. Collect plugins referenced by tables.json via the "plugin" key.
    //    We also compute the full model path "Plugin.TableClass" for logging.
    $referencedPlugins = []; // [pluginName => true]
    $pluginModels = [];      // [pluginName => [fullModelName1, fullModelName2, ...]]

    foreach ($this->tables as $tableName => $cfg) {
      if (empty($cfg['plugin']) || !is_string($cfg['plugin'])) {
        continue;
      }

      $pluginName = $cfg['plugin'];
      $referencedPlugins[$pluginName] = true;

      $tableClass = Inflector::classify($tableName); // eg "servers" -> "Servers"
      $fullModel = $pluginName . '.' . $tableClass;

      if (!isset($pluginModels[$pluginName])) {
        $pluginModels[$pluginName] = [];
      }
      $pluginModels[$pluginName][] = $fullModel;
    }

    if (empty($referencedPlugins)) {
      $this->cmdPrinter?->info('No plugins referenced in tables.json; plugin bootstrap skipped.' . PHP_EOL);
      return;
    }

    $this->cmdPrinter?->verbose('Plugins referenced in tables.json:');
    foreach ($pluginModels as $pluginName => $models) {
      $this->cmdPrinter?->verbose(sprintf(
        '  - %s (%s)',
        $pluginName,
        implode(', ', array_unique($models))
      ));
    }

    // 3. Ensure each referenced plugin exists and is active.
    foreach (array_keys($referencedPlugins) as $pluginName) {
      $plugin = $Plugins->find()
        ->where(['plugin' => $pluginName])
        ->first();

      if ($plugin === null) {
        $this->cmdPrinter?->warning(sprintf(
          'Plugin "%s" is referenced in tables.json but not registered in Plugins table.',
          $pluginName
        ));
        continue;
      }

      if ($plugin->status === SuspendableStatusEnum::Active) {
        continue;
      }

      $this->cmdPrinter?->info(sprintf(
        'Activating plugin "%s" referenced in tables.json.',
        $pluginName
      ));

      // PluginsTable::activate() will also apply the plugin schema if defined.
      $Plugins->activate((int)$plugin->id);
    }

    $this->cmdPrinter?->info('Plugin initialization complete.' . PHP_EOL);
  }


  /**
   * Check if a table exists in the source database
   *
   * @param string $tableName Name of table to check
   * @return bool True if table exists
   * @throws \Exception
   * @since COmanage Registry v5.2.0
   */
  protected function tableExists(string $tableName): bool
  {
    $dbSchemaManager = $this->inconn->createSchemaManager();
    $tableList = $dbSchemaManager->listTableNames();
    return in_array($tableName, $tableList);
  }

  /**
   * Check whether this row references a rejected record and, if so, mark it rejected and warn.
   * This preserves the original self-reference check and adds a generic parent-table check.
   *
   * Self-reference (original semantics):
   *   if (cache['rejected'][qualifiedCurrentTable][row[singular(currentTable)_id]]) then skip
   *
   * Cross-table parent:
   *   find a *_id in the row that corresponds to a known target table (eg, job_id -> jobs),
   *   then if (cache['rejected'][qualifiedParentTable][row[parent_fk]]) skip.
   *
   * @param string              $currentTable Logical target table name (eg, 'job_history_records')
   * @param array               $row          Row to insert
   * @return bool                             True if the row should be skipped, false otherwise
   * @since COmanage Registry v5.2.0
   */
  private function skipIfRejectedParent(string $currentTable, array $row): bool
  {
    if (!isset($this->cache['rejected'])) {
      return false;
    }

    // Compute qualified table names once
    $qualifiedCurrent = $this->outconn->qualifyTableName($currentTable);

    // 1) Self-reference check (preserves the original semantics)
    //    With the original code, fkOutboundQualifiedTableName was derived from the table,
    //    effectively matching "<singular(currentTable)>_id".
    $selfFk = StringUtilities::classNameToForeignKey($currentTable);
    if (
      isset($row[$selfFk]) &&
      !empty($this->cache['rejected'][$qualifiedCurrent][$row[$selfFk]])
    ) {
      $childId = $row['id'] ?? null;
      if ($childId !== null) {
        $this->cache['rejected'][$qualifiedCurrent][$childId] = $row;
      }
      $this->cmdPrinter->warning(sprintf(
        'Skipping record %d in table %s - parent %s(%d) was rejected (self-reference)',
        (int)($childId ?? 0),
        $currentTable,
        $currentTable,
        (int)$row[$selfFk]
      ));
      return true;
    }

    // 2) Cross-table parents: check ALL candidate *_id columns
    foreach ($row as $col => $val) {
      if ($val === null) { continue; }
      if (!is_string($col) || !str_ends_with($col, '_id')) { continue; }
      if ($col === 'id' || str_ends_with($col, '_type_id')) { continue; }

      $base = substr($col, 0, -3);
      $candidate = Inflector::pluralize(Inflector::underscore($base));

      // Skip self-table here (already handled)
      if ($candidate === $currentTable) { continue; }

      // Only check known target tables
      if (!isset($this->tables[$candidate])) { continue; }

      $qualifiedParent = $this->outconn->qualifyTableName($candidate);
      $parentId = $val;

      if (!empty($this->cache['rejected'][$qualifiedParent][$parentId])) {
        $childId = $row['id'] ?? null;
        if ($childId !== null) {
          $this->cache['rejected'][$qualifiedCurrent][$childId] = $row;
        }
        $this->cmdPrinter->warning(sprintf(
          'Skipping record %d in table %s - parent %s(%d) was rejected',
          (int)($childId ?? 0),
          $currentTable,
          $candidate,
          (int)$parentId
        ));
        return true;
      }
    }

    return false;
  }
}
