<?php
declare(strict_types=1);

namespace Transmogrify\Command;

use App\Lib\Util\DBALConnection;
use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Transmogrify\Service\ConfigLoaderService;

final class TransmogrifySourceToTargetCommand extends BaseCommand
{
  /**
   * Prefix provided via -p/--source-prefix to replace any hardcoded cm_ assumptions
   *
   * @var string
   */
  private string $currentPrefix = '';


  /**
   * Constructor
   *
   * @param ConfigLoaderService $configLoaderService File configuration loader(supports json and xml)
   * @since COmanage Registry v5.2.0
   */
  public function __construct(
    private ConfigLoaderService $configLoaderService,
  ) {
    parent::__construct();
  }

  /**
   * Build command options parser
   *
   * @param ConsoleOptionParser $parser Parser to configure
   * @return ConsoleOptionParser Configured parser
   */
  protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
  {
    $parser->setDescription('Print a JSON template shaped like __EXAMPLE_TABLE_TEMPLATE__ for mapping a source table to a target table. Target config is used only to read the example template; no values are copied. Paths must be absolute.');

    // Switch from positional arguments to named options (long and short)
    $parser->addOption('source-path', [
      'short' => 's',
      'help' => 'Absolute path to the SOURCE configuration file (JSON or XML).',
    ]);
    $parser->addOption('target-path', [
      'short' => 't',
      'help' => 'Absolute path to the TARGET configuration file (JSON or XML).',
    ]);
    $parser->addOption('source-table', [
      'short' => 'S',
      'help' => 'Table key/name in the source configuration to map from.',
    ]);
    $parser->addOption('target-table', [
      'short' => 'T',
      'help' => 'Table key/name in the target configuration to map to.',
    ]);
    $parser->addOption('source-prefix', [
      'short' => 'p',
      'help' => 'Prefix to prepend to the source table in the output (eg, "cm_"). Default: empty string.',
    ]);

    // Association tree printer option
    $parser->addOption('assoc-tree', [
      'short' => 'A',
      'help' => 'Print an ASCII tree of associations starting from the given --source-table based on the source XML. Use with -s and -S. Ignores other options and exits.',
      'boolean' => true,
    ]);

    $epilog = [];
    $epilog[] = 'Examples:';
    $epilog[] = '  bin/cake transmogrify_source_to_target \\\n  --source-path /path/to/registry/app/Config/Schema/schema.xml \\\n  --target-path /path/to/registry/app/config/transmogrifytables.json \\\n  --source-table cm_co_people \\\n  --target-table people';
    $epilog[] = '';
    $epilog[] = '  bin/cake transmogrify_source_to_target -s /abs/source.xml -t /abs/target.json -S cousins -T cous -p cm_';
    $epilog[] = '';
    $epilog[] = 'Notes:';
    $epilog[] = '  - Paths must be absolute.';
    $epilog[] = '  - Files may be JSON (.json) or XML (.xml).';
    $epilog[] = '  - The output is a single JSON object similar to __EXAMPLE_TABLE_TEMPLATE__ in tables.json.';
    $epilog[] = '  - Use --source-prefix/-p to control any prefix (like cm_) applied to the source table name in the output.';
    $parser->setEpilog(implode(PHP_EOL, $epilog));

    return parent::buildOptionParser($parser);
  }

  /**
   * Execute the command
   *
   * @param Arguments $args Command arguments
   * @param ConsoleIo $io Console IO instance
   * @return int Exit code
   */
  public function execute(Arguments $args, ConsoleIo $io): int
  {
    // capture prefix for helper methods
    $this->currentPrefix = (string)($args->getOption('source-prefix') ?? '');
    $sourcePath = (string)($args->getOption('source-path') ?? '');
    $targetPath = (string)($args->getOption('target-path') ?? '');
    $sourceTable = (string)($args->getOption('source-table') ?? '');
    $targetTable = (string)($args->getOption('target-table') ?? '');
    $sourcePrefix = (string)($args->getOption('source-prefix') ?? '');
    $assocTree = (bool)($args->getOption('assoc-tree') ?? false);

    // If only association tree requested, we only need source path and source table
    if ($assocTree) {
      $missing = [];
      if ($sourcePath === '') { $missing[] = '--source-path (-s)'; }
      if ($sourceTable === '') { $missing[] = '--source-table (-S)'; }
      if (!empty($missing)) {
        $io->err('Missing required option(s) for --assoc-tree: ' . implode(', ', $missing));
        $io->err('Run with --help to see usage.');
        return BaseCommand::CODE_ERROR;
      }
      if ($sourcePath === '' || !str_starts_with($sourcePath, DIRECTORY_SEPARATOR)) {
        $io->err('SOURCE path must be absolute: ' . $sourcePath);
        return BaseCommand::CODE_ERROR;
      }
      if (!is_readable($sourcePath)) {
        $io->err('SOURCE file not readable: ' . $sourcePath);
        return BaseCommand::CODE_ERROR;
      }

      try {
        $srcCfgRaw = $this->configLoaderService->loadGeneric($sourcePath);
      } catch (\Throwable $e) {
        $io->err('Failed to load source configuration: ' . $e->getMessage());
        return BaseCommand::CODE_ERROR;
      }
      $this->printAssociationTreeFromSchema($srcCfgRaw, $sourceTable, $io);
      return BaseCommand::CODE_SUCCESS;
    }

    // Validate required named options are present
    $missing = [];
    if ($sourcePath === '') { $missing[] = '--source-path (-s)'; }
    if ($targetPath === '') { $missing[] = '--target-path (-t)'; }
    if ($sourceTable === '') { $missing[] = '--source-table (-S)'; }
    if ($targetTable === '') { $missing[] = '--target-table (-T)'; }
    if (!empty($missing)) {
      $io->err('Missing required option(s): ' . implode(', ', $missing));
      $io->err('Run with --help to see usage.');
      return BaseCommand::CODE_ERROR;
    }

    // Validate absolute paths
    foreach (['source' => $sourcePath, 'target' => $targetPath] as $label => $path) {
      if ($path === '' || !str_starts_with($path, DIRECTORY_SEPARATOR)) {
        $io->err(strtoupper($label) . ' path must be absolute: ' . $path);
        return BaseCommand::CODE_ERROR;
      }
      if (!is_readable($path)) {
        $io->err(strtoupper($label) . ' file not readable: ' . $path);
        return BaseCommand::CODE_ERROR;
      }
    }

    try {
      // Load target config to read __EXAMPLE_TABLE_TEMPLATE__ shape
      $tgtCfg = $this->configLoaderService->loadGeneric($targetPath);
      // Load source config to extract old schema (eg, from XML)
      $srcCfgRaw = $this->configLoaderService->loadGeneric($sourcePath);
    } catch (\Throwable $e) {
      $io->err('Failed to load configuration: ' . $e->getMessage());
      return BaseCommand::CODE_ERROR;
    }

    // Load the migration config (tables.json) to derive generic co_* FK renames
    $tablesJsonPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'schema' . DIRECTORY_SEPARATOR . 'tables.json';
    $tablesCfg = [];
    if (is_readable($tablesJsonPath)) {
      try {
        $tablesCfg = $this->configLoaderService->load($tablesJsonPath);
      } catch (\Throwable $e) {
        $io->warning('Could not load migration config tables.json: ' . $e->getMessage());
      }
    }

    // Normalize possible schema.xml structures into transmogrify-like configs for source
    $srcCfg = $this->normalizeConfig(is_array($srcCfgRaw) ? $srcCfgRaw : []);

    // Extract example template (shape) from target config without filtering out __ keys first
    $example = is_array($tgtCfg) && array_key_exists('__EXAMPLE_TABLE_TEMPLATE__', $tgtCfg)
      ? (array)$tgtCfg['__EXAMPLE_TABLE_TEMPLATE__']
      : [];

    // Determine the output keys based on the example (ignoring any __* documentation keys)
    $exampleKeys = array_filter(array_keys($example), static function($k) {
      return !is_string($k) || !str_starts_with($k, '__');
    });

    // Determine source fields from old schema configuration (if available)
    $sourceFields = [];
    [$srcKey, $srcTableCfg] = $this->findTableConfig($srcCfg, $sourceTable);
    if (is_array($srcTableCfg)) {
      if (!empty($srcTableCfg['fieldMap']) && is_array($srcTableCfg['fieldMap'])) {
        $sourceFields = array_keys($srcTableCfg['fieldMap']);
      } elseif (!empty($srcTableCfg['fields']) && is_array($srcTableCfg['fields'])) {
        $sourceFields = array_values($srcTableCfg['fields']);
      }
    }
    $sourceFieldSet = array_flip($sourceFields);

    // Build legacy FK pattern map (eg, *_co_person_id -> *_person_id, *_co_message_template_id -> *_message_template_id)
    $legacyFkPatterns = $this->buildLegacyFkPatterns($tablesCfg, (string)$sourcePrefix);

    // Inspect default database to find target table columns
    try {
      $conn = DBALConnection::factory($io);
      $columns = [];
      $candidate = $targetTable;
      $alts = [ $candidate ];
      // toggle provided prefix to try alternative table naming
      $prefix = (string)($sourcePrefix ?? '');
      if ($prefix !== '') {
        $alts[] = str_starts_with($candidate, $prefix) ? substr($candidate, strlen($prefix)) : ($prefix . $candidate);
      }
      $foundTable = null;
      foreach (array_unique($alts) as $tbl) {
        if ($conn->isMySQL()) {
          $db = $conn->fetchOne('SELECT DATABASE()');
          $cols = $conn->fetchAllAssociative('SELECT column_name, data_type, column_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position', [$db, $tbl]);
          if (!empty($cols)) { $columns = $cols; $foundTable = $tbl; break; }
        } else {
          // PostgreSQL: search in current schema(s)
          $cols = $conn->fetchAllAssociative("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position", [$tbl]);
          if (!empty($cols)) { $columns = $cols; $foundTable = $tbl; break; }
        }
      }
      if ($foundTable === null) {
        $io->err('Target table not found in default database: ' . $targetTable);
        return BaseCommand::CODE_ERROR;
      }

      // Build target column name lists
      $targetCols = array_map(static fn($c) => (string)$c['column_name'], $columns);
      $targetColSetAll = array_flip($targetCols);

      $singular = \Cake\Utility\Inflector::singularize($targetTable);
      $metaCols = [
        $singular . '_id',
        'revision',
        'deleted',
        'actor_identifier',
        'created',
        'modified',
      ];

      $targetColSet = $targetColSetAll;
      foreach ($metaCols as $mc) {
        unset($targetColSet[$mc]);
      }

      // Determine if target natively has changelog fields (controls addChangelog)
      $targetHasChangelog = (
        array_key_exists($singular . '_id', $targetColSetAll)
        && array_key_exists('revision', $targetColSetAll)
        && array_key_exists('deleted', $targetColSetAll)
        && array_key_exists('actor_identifier', $targetColSetAll)
      );

      // Determine if source has changelog (v4 pattern: revision, deleted, actor_identifier, and legacy self-FK)
      $legacySelfFkExact  = 'co_' . $singular . '_id';
      $legacySelfFkSuffix = '_co_' . $singular . '_id';
      $fieldsLower = array_map('strtolower', $sourceFields);
      $sourceFieldSet = array_flip($fieldsLower);
      $sourceHasChangelog = (
        isset($sourceFieldSet['revision'])
        && isset($sourceFieldSet['deleted'])
        && isset($sourceFieldSet['actor_identifier'])
        && (
          in_array($legacySelfFkExact, $fieldsLower, true)
          || (bool)array_filter($fieldsLower, fn($s) => str_ends_with($s, $legacySelfFkSuffix))
        )
      );

      // Detect booleans by database type (for non-metadata only)
      $booleanCols = [];
      foreach ($columns as $col) {
        $colName = (string)$col['column_name'];
        if (in_array($colName, $metaCols, true)) { continue; }
        if ($conn->isMySQL()) {
          $dt = strtolower((string)($col['data_type'] ?? ''));
          $ct = strtolower((string)($col['column_type'] ?? ''));
          $isBool = ($dt === 'tinyint' && str_starts_with($ct, 'tinyint(1)')) || ($dt === 'bit' && str_starts_with($ct, 'bit(1)')) || ($dt === 'boolean');
          if ($isBool) { $booleanCols[] = $colName; }
        } else {
          $dt = strtolower((string)($col['data_type'] ?? ''));
          if ($dt === 'boolean' || $dt === 'bool') { $booleanCols[] = $colName; }
        }
      }

      // Build fieldMap as source-left => target-right (include null when unknown)
      $fieldMap = [];
      $mappedTargets = [];
      $sourceMeta = ['revision','deleted','actor_identifier','created','modified'];
      foreach ($sourceFields as $scol) {
        if (in_array($scol, $sourceMeta, true)) { continue; }

        // 1) exact same-name target (non-metadata set)
        $tcol = array_key_exists($scol, $targetColSet) ? $scol : null;

        // 2) normalization-based mapping (generic co_* fk rename via patterns + specific one-offs)
        if ($tcol === null) {
          $candidate = $this->normalizeTargetNameForSourceColumn($scol, $legacyFkPatterns);
          if ($candidate !== null) {
            $isMetaSelfFk = ($candidate === $singular . '_id');
            // accept candidate if it's a normal column, or it's the allowed self-FK
            if (array_key_exists($candidate, $targetColSet)
              || ($isMetaSelfFk && array_key_exists($candidate, $targetColSetAll))) {
              $tcol = $candidate;
            }
          }
        }

        $fieldMap[$scol] = $tcol;
        if ($tcol !== null) { $mappedTargets[$tcol] = true; }
      }

      // Collect target-only columns (user can decide later).
      $targetUnmapped = [];
      foreach (array_keys($targetColSetAll) as $tcol) {
        // skip global metadata from the hint list
        if (in_array($tcol, $metaCols, true)) { continue; }
        if (!isset($mappedTargets[$tcol])) {
          $targetUnmapped[] = $tcol;
        }
      }
    } catch (\Throwable $e) {
      $io->err('Failed to inspect database schema: ' . $e->getMessage());
      return BaseCommand::CODE_ERROR;
    }


    // Build a template strictly using the example shape. The target config is only used for the example; no values are copied.
    $defaults = [
      'source' => $sourcePrefix . $sourceTable,
      'displayField' => $this->determineDisplayField($sourceFields),
      // Note: we will attach/omit addChangelog below based on source/target
      //'addChangelog' => '??',
      'booleans' => $booleanCols,
      'cache' => [],
      'sqlSelect' => null,
      'preTable' => null,
      'postTable' => null,
      'preRow' => null,
      'postRow' => null,
      'fieldMap' => $fieldMap ?: new \stdClass(),
    ];

    // If example keys were found, limit output to those keys (plus ensure 'source' exists).
    if (!empty($exampleKeys)) {
      $template = [];
      foreach ($exampleKeys as $k) {
        if ($k === 'source') {
          $template[$k] = $sourcePrefix . $sourceTable;
          continue;
        }
        $template[$k] = $defaults[$k] ?? null;
      }
      foreach (['source','fieldMap'] as $must) {
        if (!array_key_exists($must, $template)) {
          $template[$must] = $defaults[$must];
        }
      }
    } else {
      $template = $defaults;
    }

    // Decide addChangelog per rules:
    // - both have changelog: omit configuration
    // - target has changelog (source doesn't): true
    // - target doesn't have changelog (source does): false
    $addChangelogOpt = null;
    if ($targetHasChangelog && $sourceHasChangelog) {
      $addChangelogOpt = null; // omit
    } elseif ($targetHasChangelog && !$sourceHasChangelog) {
      $addChangelogOpt = true;
    } elseif (!$targetHasChangelog && $sourceHasChangelog) {
      $addChangelogOpt = false;
    } else {
      $addChangelogOpt = null; // neither has: omit
    }

    // Apply addChangelog decision
    if (array_key_exists('addChangelog', $template)) {
      unset($template['addChangelog']);
    }
    if ($addChangelogOpt !== null) {
      $template['addChangelog'] = $addChangelogOpt;
    }


    // Attach target-only hints for the user
    if (!empty($targetUnmapped)) {
      $template['targetUnmapped'] = $targetUnmapped;
    }

    // Wrap result under the target-table key for direct insertion into tables.json
    $wrapped = [ $targetTable => $template ];

    // Ensure JSON encoding preserves empty objects properly for fieldMap
    $json = json_encode($wrapped, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
      $io->err('Failed to encode output JSON');
      return BaseCommand::CODE_ERROR;
    }

    $io->out($json);
    return BaseCommand::CODE_SUCCESS;
  }

  /**
   * Filter out documentation keys (starting with __) from configuration array
   *
   * @param array $cfg Configuration array to filter
   * @return array Filtered configuration without documentation keys
   */
  private function filterDocKeys(array $cfg): array
  {
    return array_filter($cfg, static function ($value, $key) {
      return !(is_string($key) && str_starts_with($key, '__'));
    }, ARRAY_FILTER_USE_BOTH);
  }

  /**
   * Decide if addChangelog should be true based on source configuration fields.
   * Requirements: revision, deleted, actor_identifier, and self-referencing FK (eg, cous -> cou_id).
   *
   * @param string $tableName Name/key of the source table (may be prefixed like cm_)
   * @param array $sourceFields List of source column names
   */
  private function shouldAddChangelog(string $tableName, array $sourceFields): bool
  {
    if (empty($sourceFields)) {
      return false;
    }
    $fields = array_map('strtolower', $sourceFields);
    $fieldSet = array_flip($fields);

    // Required fixed columns
    foreach (['revision','deleted','actor_identifier'] as $req) {
      if (!array_key_exists($req, $fieldSet)) {
        return false;
      }
    }
    // Determine expected self FK
    $name = strtolower($tableName);
    $prefix = (string)($this->currentPrefix ?? '');
    if ($prefix !== '' && str_starts_with($name, $prefix)) {
      $name = substr($name, strlen($prefix));
    }
    // If table name is schema-qualified (eg, public.table), take part after dot
    $dot = strrpos($name, '.');
    if ($dot !== false) {
      $name = substr($name, $dot + 1);
    }
    $base = $name;
    if (str_ends_with($base, 's')) {
      $base = substr($base, 0, -1);
    }
    $selfFk = $base . '_id';

    return array_key_exists($selfFk, $fieldSet);
  }

  /**
   * Normalize a loaded configuration array.
   * - If it's a schema-like array (has 'table' nodes), convert to a transmogrify-like map
   *   keyed by table name with at least 'source' and 'fieldMap'.
   */
  private function normalizeConfig(array $cfg): array
  {
    // Detect schema root
    $tablesNode = null;
    if (array_key_exists('table', $cfg)) {
      $tablesNode = $cfg['table'];
    } elseif (isset($cfg['schema']) && is_array($cfg['schema']) && array_key_exists('table', $cfg['schema'])) {
      $tablesNode = $cfg['schema']['table'];
    }
    if ($tablesNode === null) {
      // Already config-like
      return $cfg;
    }
    // Ensure list
    if (!is_array($tablesNode) || (is_array($tablesNode) && array_keys($tablesNode) !== range(0, count($tablesNode)-1))) {
      // Single table object -> wrap
      $tables = [$tablesNode];
    } else {
      $tables = $tablesNode;
    }
    $out = [];
    foreach ($tables as $t) {
      if (!is_array($t)) { continue; }
      // Name is typically under '@attributes' => ['name' => ...]
      $name = null;
      if (isset($t['@attributes']['name'])) {
        $name = (string)$t['@attributes']['name'];
      } elseif (isset($t['name'])) {
        // Fallback if converter placed it differently
        $name = is_array($t['name']) && isset($t['name']['@attributes']) ? (string)($t['name']['@attributes']['value'] ?? '') : (string)$t['name'];
      }
      if ($name === null || $name === '') { continue; }

      // Build simple 1:1 field map using <field name="..."> elements, if present
      $fieldMap = new \stdClass();
      if (isset($t['field'])) {
        $fieldsNode = $t['field'];
        // Normalize to list
        if (!is_array($fieldsNode) || (is_array($fieldsNode) && array_keys($fieldsNode) !== range(0, count($fieldsNode)-1))) {
          $fields = [$fieldsNode];
        } else {
          $fields = $fieldsNode;
        }
        $map = [];
        foreach ($fields as $f) {
          if (!is_array($f)) { continue; }
          $fname = $f['@attributes']['name'] ?? null;
          if ($fname) {
            $map[$fname] = $fname;
          }
        }
        if (!empty($map)) {
          $fieldMap = $map;
        }
      }

      $out[$name] = [
        'source' => $name,
        'displayField' => $this->determineDisplayField(is_array($fieldMap) ? array_keys($fieldMap) : []),
        'fieldMap' => $fieldMap
      ];
    }
    return $out;
  }

  /**
   * Locate a table configuration by key or by matching the 'source' attribute.
   * Also tries matching names with/without a leading provided prefix.
   *
   * @param array $cfg
   * @param string $nameOrSource Either the config key or the source table name
   * @return array{0:string|null,1:array|null} [key, config]
   */
  private function findTableConfig(array $cfg, string $nameOrSource): array
  {
    if (array_key_exists($nameOrSource, $cfg) && is_array($cfg[$nameOrSource])) {
      return [$nameOrSource, $cfg[$nameOrSource]];
    }
    $prefix = (string)($this->currentPrefix ?? '');
    $alt = $nameOrSource;
    if ($prefix !== '') {
      $alt = str_starts_with($nameOrSource, $prefix) ? substr($nameOrSource, strlen($prefix)) : ($prefix . $nameOrSource);
    }
    if (array_key_exists($alt, $cfg) && is_array($cfg[$alt])) {
      return [$alt, $cfg[$alt]];
    }
    foreach ($cfg as $key => $val) {
      if (!is_array($val)) { continue; }
      $src = $val['source'] ?? null;
      if ($src === $nameOrSource || $src === $alt) {
        return [$key, $val];
      }
      // Also allow key matching to bare table name
      if ($key === $nameOrSource || $key === $alt) {
        return [$key, $val];
      }
    }
    return [null, null];
  }

  /**
   * Determine displayField from source table fields using provided order:
   * name, display_name, username, authenticated_identifier, description, id.
   * Returns null if none are present.
   */
  private function determineDisplayField(array $sourceFields): ?string
  {
    if (empty($sourceFields)) {
      return null;
    }
    $fields = array_map('strtolower', $sourceFields);
    $set = array_flip($fields);
    foreach (['name','display_name','username','authenticated_identifier','description','id'] as $candidate) {
      if (array_key_exists($candidate, $set)) {
        return $candidate;
      }
    }
    return null;
  }

  /**
   * Print an ASCII association tree for a given starting table using the parsed XML schema array.
   * Supports common schema.xml structures with <foreign-key> elements containing @attributes["foreignTable"]
   * and nested <reference @attributes[local,foreign]> elements.
   */
  private function printAssociationTreeFromSchema(array $schemaArr, string $startTable, ConsoleIo $io): void
  {
    // Build associative array tree from schema.xml-like array
    // We expect tables under ['schema']['table'] or directly under ['table']
    $tablesNode = $schemaArr['schema']['table'] ?? ($schemaArr['table'] ?? []);
    // Normalize to list of tables
    if (!is_array($tablesNode) || (is_array($tablesNode) && array_keys($tablesNode) !== range(0, count($tablesNode)-1))) {
      $tables = [$tablesNode];
    } else {
      $tables = $tablesNode;
    }
    // Build map: tableName => [ children tables ... ] based on column @attributes['constraint'] value 'REFERENCE table(column)'
    $children = [];
    foreach ($tables as $t) {
      if (!is_array($t)) { continue; }
      $tbl = $t['@attributes']['name'] ?? ($t['name'] ?? null);
      if ($tbl === null) { continue; }
      $tbl = (string)$tbl;
      if (!isset($children[$tbl])) { $children[$tbl] = []; }
      // inspect columns/field definitions; schema.xml may use <column> or <field>
      $colsNode = $t['column'] ?? ($t['field'] ?? []);
      $cols = [];
      if ($colsNode !== [] && $colsNode !== null) {
        if (!is_array($colsNode) || (is_array($colsNode) && array_keys($colsNode) !== range(0, count($colsNode)-1))) {
          $cols = [$colsNode];
        } else {
          $cols = $colsNode;
        }
      }
      foreach ($cols as $idx => $col) {
        if (!is_array($col)) { continue; }
        $constraint = $col['constraint'] ?? null;
        if (!is_string($constraint)) { continue; }
        // Expect format: 'REFERENCE table(column)'
        if (preg_match('/^REFERENCES\s+([A-Za-z0-9_\.]+)\s*\(([^)]+)\)/', $constraint, $m)) {
          $refTable = $m[1];
          // Remove prefix if it was provided and matches
          if ($this->currentPrefix !== '' && str_starts_with($refTable, $this->currentPrefix)) {
            $refTable = substr($refTable, strlen($this->currentPrefix));
          }

          // current table depends on refTable -> edge from current to parent
          // For tree of ancestors starting from $startTable, we build parent map
          // but for associative array tree representation, we'll build nested children where parent has child
          // i.e., parent -> [ child1, child2 ] based on references found in child
          if (!isset($children[$tbl])) { $children[$tbl] = []; }
          if (!in_array($refTable, $children[$tbl], true)) {
            $children[$tbl][] = $refTable;
          }
        }
      }
    }
    // Build recursive associative array tree starting at $startTable
    $visited = [];
    $buildTree = function(string $node) use (&$buildTree, &$children, &$visited) {
      if (isset($visited[$node])) {
        // cycle: denote specially
        return ['__cycle__' => $node];
      }
      $visited[$node] = true;
      $kids = $children[$node] ?? [];
      $tree = [];
      foreach ($kids as $k) {
        $tree[$k] = $buildTree($k);
      }
      return $tree;
    };
    $tree = [ $startTable => $buildTree($startTable) ];
    // Output the array via Console IO as JSON for readability
    $io->out(json_encode($tree, JSON_PRETTY_PRINT));
  }

  /**
   * Build legacy co_* foreign key rename patterns based on migration config.
   * Returns an array of [suffixFrom => suffixTo], eg:
   *   '_co_person_id' => '_person_id'
   *   '_co_group_id' => '_group_id'
   *   '_co_message_template_id' => '_message_template_id'
   *
   * The patterns are applied as “ends-with” replacements to source column names.
   */
  private function buildLegacyFkPatterns(array $tablesCfg, string $sourcePrefix): array
  {
    if (!is_array($tablesCfg) || empty($tablesCfg)) {
      return [
        '_co_person_id' => '_person_id',
        '_co_group_id'  => '_group_id',
        // Also support exact-form defaults for standalone columns
        'co_person_id'  => 'person_id',
        'co_group_id'   => 'group_id',
      ];
    }

    // Filter out documentation keys and reduce to table entries that have a 'source'
    $entries = [];
    foreach ($tablesCfg as $k => $v) {
      if (!is_array($v)) { continue; }
      if (is_string($k) && str_starts_with($k, '__')) { continue; }
      if (!isset($v['source']) || !is_string($v['source'])) { continue; }
      $entries[$k] = $v['source'];
    }

    $patterns = [
      // suffix form (covers prefix + co_* cases)
      '_co_person_id' => '_person_id',
      '_co_group_id'  => '_group_id',
      // exact form (covers exact co_* columns with no left prefix)
      'co_person_id'  => 'person_id',
      'co_group_id'   => 'group_id',
    ];

    foreach ($entries as $targetKey => $src) {
      $bareSource = $src;
      if ($sourcePrefix !== '' && str_starts_with($bareSource, $sourcePrefix)) {
        $bareSource = substr($bareSource, strlen($sourcePrefix));
      }

      // If bare source starts with "co_", derive both forms for this target
      // targetKey 'message_templates' -> singular 'message_template'
      if (str_starts_with($bareSource, 'co_')) {
        $singular = \Cake\Utility\Inflector::singularize($targetKey);

        // suffix form: ..._co_<singular>_id -> ..._<singular>_id
        $fromSuffix = '_co_' . $singular . '_id';
        $toSuffix   = '_' . $singular . '_id';
        $patterns[$fromSuffix] = $toSuffix;

        // exact form: co_<singular>_id -> <singular>_id
        $fromExact = 'co_' . $singular . '_id';
        $toExact   = $singular . '_id';
        $patterns[$fromExact] = $toExact;
      }
    }

    return $patterns;
  }


  /**
   * Normalize a v4 source column name to a likely v5 target column name based on:
   *  - generic co_* foreign key patterns derived from migration config
   *  - specific one-offs
   */
  private function normalizeTargetNameForSourceColumn(string $sourceCol, array $legacyFkPatterns): ?string
  {
    // Try exact-form first (eg, 'co_message_template_id' -> 'message_template_id')
    if (isset($legacyFkPatterns[$sourceCol])) {
      return $legacyFkPatterns[$sourceCol];
    }

    // Then apply suffix-based rewrites (eg, 'approver_co_group_id' -> 'approver_group_id')
    foreach ($legacyFkPatterns as $from => $to) {
      if ($from === $sourceCol) { continue; } // already handled exact
      // only treat entries starting with '_' as suffix rules
      if (str_starts_with($from, '_') && str_ends_with($sourceCol, $from)) {
        $prefix = substr($sourceCol, 0, -strlen($from));
        return $prefix . $to;
      }
    }

    // Specific one-offs
    if ($sourceCol === 'source_url') {
      return 'source';
    }
    if ($sourceCol === 'email_body') {
      return 'email_body_text';
    }

    return null;
  }
}
