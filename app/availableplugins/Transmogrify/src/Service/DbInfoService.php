<?php
declare(strict_types=1);

namespace Transmogrify\Service;

use App\Lib\Util\DBALConnection;
use Cake\Datasource\ConnectionManager;
use function App\Service\sort;

/**
 * Service to provide reusable DB info logic for console and elsewhere.
 */
final class DbInfoService
{
  /** @var int Maximum number of sample tables to display */
  private const TABLE_SAMPLE_LIMIT = 5;

  public function __construct() {}

  /**
   * Get connection configuration info for a role or alias.
   * @param string $roleOrAlias 'source'|'target' or a Cake alias such as 'default'|'transmogrify'
   * @return array
   */
  public function getConnectionInfo(string $roleOrAlias): array
  {
    // Map role to alias
    $map = [ 'source' => 'transmogrify', 'target' => 'default' ];
    $role = $roleOrAlias;
    $alias = $map[$roleOrAlias] ?? $roleOrAlias;

    $cfg = null;
    try {
      $cfg = ConnectionManager::getConfig($alias);
    } catch (\Throwable $e) {
      $cfg = null;
    }

    return [
      'alias' => $alias,
      'role' => ($alias === ($map['source'] ?? 'transmogrify')) ? 'source' : (($alias === ($map['target'] ?? 'default')) ? 'target' : $role),
      'configured' => $cfg !== null,
      'driver' => $cfg['driver'] ?? null,
      'host' => $cfg['host'] ?? ($cfg['hostname'] ?? null),
      'port' => $cfg['port'] ?? null,
      'database' => $cfg['database'] ?? ($cfg['dbname'] ?? null),
      'username' => $cfg['username'] ?? ($cfg['user'] ?? null),
      'password' => null,
      'dsn' => $cfg['url'] ?? null,
    ];
  }

  /**
   * Ping a connection alias and return status info.
   * @param string $alias Cake connection alias
   * @return array{ok:bool,server:?(string),error:?(string)}
   */
  public function ping(string $alias): array
  {
    $status = [ 'ok' => false, 'error' => null, 'server' => null ];
    try {
      $conn = DBALConnection::factory(connection: $alias);
      if ($conn->isMySQL()) {
        $ver = $conn->fetchOne('SELECT VERSION()');
      } else {
        $ver = $conn->fetchOne('SHOW server_version');
        if (!$ver) { $ver = $conn->fetchOne('SELECT version()'); }
      }
      $status['ok'] = true;
      $status['server'] = $ver;
    } catch (\Throwable $e) {
      $status['ok'] = false;
      $status['error'] = $e->getMessage();
    }
    return $status;
  }

  /**
   * Load schema information for a given alias.
   * @param string $alias Cake connection alias
   * @param string|null $tablesJsonPath Path to the tables.json file
   * @return array{table_count:int,empty:bool,sample_tables:array<string>,tables_compare:array,loaded_schema:?(string)}
   */
  public function loadSchemaInfo(string $alias, ?string $tablesJsonPath = null): array
  {
    if ($tablesJsonPath === null) {
      return [];
    }
    $conn = DBALConnection::factory(connection: $alias);
    return $this->loadSchemaInfoFromConnection($conn, $tablesJsonPath);
  }

  /**
   * Exposed for reuse when a DBALConnection already exists.
   * @param DBALConnection $conn The database connection
   * @param string $tablesJsonPath Path to the tables.json file
   * @return array Schema information array
   *
   * todo: The new version should render all the tables. And if i pass a the parameter transmogrify then
   * it should render the ones that have been transmogrified.
   */
  public function loadSchemaInfoFromConnection(DBALConnection $conn, string $tablesJsonPath): array
  {
    // Load declared tables from tables.json
    $declared = [];
    $loadedSchemaName = basename($tablesJsonPath);
    try {

      if (is_readable($tablesJsonPath)) {
        $json = file_get_contents($tablesJsonPath);
        $cfg = json_decode($json, true);
        if (is_array($cfg)) {
          // Filter out documentation keys (eg, keys starting with "__") to avoid printing template entries
          $cfg = array_filter($cfg, static function ($value, $key) {
            return !(is_string($key) && str_starts_with($key, '__'));
          }, ARRAY_FILTER_USE_BOTH);
          $declared = array_keys($cfg);
        }
      }
    } catch (\Throwable $e) {
      // ignore; we'll fall back to empty list
    }

    // Gather list of non-system tables
    $tables = [];
    if ($conn->isMySQL()) {
      $db = $conn->fetchOne('SELECT DATABASE()');
      $rows = $conn->fetchAllAssociative('SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = ? ORDER BY table_name ASC', [$db, 'BASE TABLE']);
      $tables = array_map(fn($r) => $r['table_name'], $rows);
    } else {
      // PostgreSQL
      $rows = $conn->fetchAllAssociative("SELECT schemaname, tablename FROM pg_catalog.pg_tables WHERE schemaname NOT IN ('pg_catalog','information_schema') ORDER BY schemaname, tablename");
      foreach ($rows as $r) { $tables[] = ($r['schemaname'] . '.' . $r['tablename']); }
    }

    // Check if DB is empty (no tables) or all tables empty (zero rows)
    $info = [
      'table_count' => count($tables),
      'sample_tables' => array_slice($tables, 0, self::TABLE_SAMPLE_LIMIT),
      'empty' => false,
      'loaded_schema' => $loadedSchemaName,
    ];

    $info['empty'] = (count($tables) === 0);
    if (!$info['empty']) {
      // If there are tables, check if any table has rows; if none, still empty of data
      $hasData = false;
      foreach ($info['sample_tables'] as $t) {
        try {
          // For qualified names, don't double-quote
          $count = (int)$conn->fetchOne('SELECT COUNT(*) FROM ' . $t);
          if ($count > 0) { $hasData = true; break; }
        } catch (\Throwable $e) {
          // ignore per-table errors
        }
      }
      $info['empty'] = !$hasData;
    }

    // Build comparison lists between tables.json and actual DB tables, ignoring schema names
    $normalize = function(string $t): string {
      // Strip any schema prefix such as public.table or mysch.table
      $p = strrpos($t, '.');
      return $p === false ? $t : substr($t, $p + 1);
    };
    $actualBare = array_map($normalize, $tables);
    $declaredBare = array_map($normalize, $declared);
    // Now compute sets on bare names
    $actual = $actualBare;
    $declared = $declaredBare;
    sort($actual);
    sort($declared);
    $both = array_values(array_unique(array_intersect($declared, $actual)));
    $onlyJson = array_values(array_unique(array_diff($declared, $actual)));
    $onlyDb = array_values(array_unique(array_diff($actual, $declared)));

    $info['tables_compare'] = [
      'both' => $both,
      'only_in_json' => $onlyJson,
      'only_in_db' => $onlyDb,
    ];

    return $info;
  }
}
