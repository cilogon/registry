<?php
/**
 * COmanage Registry Transmogrify Index Manager
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

declare(strict_types=1);

namespace Transmogrify\Lib\Util;

use App\Lib\Util\DBALConnection;

/**
 * Manages dropping and recreating non-primary-key indexes on target tables
 * to speed up bulk inserts during transmogrification.
 *
 * Designed for DI container registration: construct with no args, then call
 * initialize() once the DBAL connection and printer are available at runtime.
 *
 * @since COmanage Registry v5.2.0
 */
class IndexManager
{
  /**
   * Cached index definitions keyed by qualified table name.
   *
   * @var array<string, array>
   */
  protected array $savedIndexes = [];

  /** @var DBALConnection|null */
  protected ?DBALConnection $conn = null;

  /** @var CommandLinePrinter|null */
  protected ?CommandLinePrinter $printer = null;

  /**
   * Initialize the manager with a live connection and optional printer.
   * Must be called before disableIndexes()/enableIndexes().
   *
   * @param DBALConnection $conn DBAL connection to the target database
   * @param CommandLinePrinter|null $printer Optional printer for log output
   * @return self Fluent interface
   * @since COmanage Registry v5.2.0
   */
  public function initialize(DBALConnection $conn, ?CommandLinePrinter $printer = null): self
  {
    $this->conn = $conn;
    $this->printer = $printer;
    return $this;
  }

  /**
   * Drop non-primary, non-constraint indexes on a table, saving their definitions
   * so they can be recreated later with enableIndexes().
   *
   * Primary keys and indexes that back unique/foreign-key constraints are preserved
   * to maintain referential integrity during the bulk load.
   *
   * @param string $qualifiedTableName Schema-qualified table name (e.g. "public.cos")
   * @throws \RuntimeException If initialize() has not been called
   * @since COmanage Registry v5.2.0
   */
  public function disableIndexes(string $qualifiedTableName): void
  {
    $this->assertInitialized();

    if ($this->conn->isPostgreSQL()) {
      $this->disablePostgresIndexes($qualifiedTableName);
    } else {
      $this->disableMysqlIndexes($qualifiedTableName);
    }
  }

  /**
   * Recreate previously disabled indexes for a table.
   *
   * @param string $qualifiedTableName Schema-qualified table name (e.g. "public.cos")
   * @throws \RuntimeException If initialize() has not been called
   * @since COmanage Registry v5.2.0
   */
  public function enableIndexes(string $qualifiedTableName): void
  {
    $this->assertInitialized();

    if ($this->conn->isPostgreSQL()) {
      $this->enablePostgresIndexes($qualifiedTableName);
    } else {
      $this->enableMysqlIndexes($qualifiedTableName);
    }

    unset($this->savedIndexes[$qualifiedTableName]);
  }

  /**
   * Check whether any saved index definitions exist for the given table.
   *
   * @param string $qualifiedTableName Schema-qualified table name
   * @return bool
   * @since COmanage Registry v5.2.0
   */
  public function hasSavedIndexes(string $qualifiedTableName): bool
  {
    return !empty($this->savedIndexes[$qualifiedTableName]);
  }

  // ---------- PostgreSQL ----------

  /**
   * Drop non-PK, non-constraint indexes on a PostgreSQL table.
   *
   * We query pg_indexes and exclude any index name that appears in pg_constraint
   * (which covers primary keys, unique constraints, and foreign keys).
   *
   * @param string $qualifiedTableName Schema-qualified table name
   */
  protected function disablePostgresIndexes(string $qualifiedTableName): void
  {
    [$schema, $table] = $this->parseQualifiedName($qualifiedTableName, 'public');

    $sql = <<<SQL
      SELECT i.indexname, i.indexdef
      FROM pg_indexes i
      WHERE i.schemaname = :schema
        AND i.tablename  = :table
        AND i.indexname NOT IN (
            SELECT co.conname
            FROM pg_constraint co
            JOIN pg_class c ON c.oid = co.conrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE c.relname = :table
              AND n.nspname = :schema
        )
        AND i.indexdef NOT LIKE '%UNIQUE%'
      ORDER BY i.indexname
    SQL;

    $stmt = $this->conn->executeQuery($sql, ['schema' => $schema, 'table' => $table]);
    $indexes = $stmt->fetchAllAssociative();

    if (empty($indexes)) {
      $this->printer?->verbose("No droppable indexes found on $qualifiedTableName");
      return;
    }

    $this->savedIndexes[$qualifiedTableName] = $indexes;
    $this->printer?->info(sprintf(
      'Dropping %d index(es) on %s for bulk load performance',
      count($indexes),
      $qualifiedTableName
    ));

    foreach ($indexes as $idx) {
      $dropSql = 'DROP INDEX IF EXISTS ' . $schema . '."' . $idx['indexname'] . '"';
      $this->printer?->verbose('  ' . $dropSql);
      $this->conn->executeStatement($dropSql);
    }
  }

  /**
   * Recreate previously saved PostgreSQL indexes.
   *
   * @param string $qualifiedTableName Schema-qualified table name
   */
  protected function enablePostgresIndexes(string $qualifiedTableName): void
  {
    if (empty($this->savedIndexes[$qualifiedTableName])) {
      return;
    }

    $count = count($this->savedIndexes[$qualifiedTableName]);
    $this->printer?->info(sprintf(
      'Recreating %d index(es) on %s',
      $count,
      $qualifiedTableName
    ));

    foreach ($this->savedIndexes[$qualifiedTableName] as $idx) {
      $this->printer?->verbose('  ' . $idx['indexdef']);
      $this->conn->executeStatement($idx['indexdef']);
    }
  }

  // ---------- MySQL / MariaDB ----------

  /**
   * Drop non-PK, non-UNIQUE indexes on a MySQL/MariaDB table.
   *
   * UNIQUE indexes are preserved to maintain data integrity during the bulk load.
   *
   * @param string $qualifiedTableName Qualified table name (db.table)
   */
  protected function disableMysqlIndexes(string $qualifiedTableName): void
  {
    [$database, $table] = $this->parseQualifiedName($qualifiedTableName);

    $whereDb = $database !== null
      ? 'TABLE_SCHEMA = :database'
      : 'TABLE_SCHEMA = DATABASE()';

    $sql = <<<SQL
      SELECT INDEX_NAME,
             GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS idx_columns,
             NON_UNIQUE,
             INDEX_TYPE
      FROM INFORMATION_SCHEMA.STATISTICS
      WHERE $whereDb
        AND TABLE_NAME  = :table
        AND INDEX_NAME != 'PRIMARY'
        AND NON_UNIQUE = 1
      GROUP BY INDEX_NAME, NON_UNIQUE, INDEX_TYPE
      ORDER BY INDEX_NAME
    SQL;

    $params = ['table' => $table];
    if ($database !== null) {
      $params['database'] = $database;
    }

    $stmt = $this->conn->executeQuery($sql, $params);
    $indexes = $stmt->fetchAllAssociative();

    if (empty($indexes)) {
      $this->printer?->verbose("No droppable indexes found on $qualifiedTableName");
      return;
    }

    $this->savedIndexes[$qualifiedTableName] = $indexes;
    $this->printer?->info(sprintf(
      'Dropping %d index(es) on %s for bulk load performance',
      count($indexes),
      $qualifiedTableName
    ));

    $qualifiedTarget = $database !== null
      ? '`' . $database . '`.`' . $table . '`'
      : '`' . $table . '`';

    foreach ($indexes as $idx) {
      $dropSql = 'DROP INDEX `' . $idx['INDEX_NAME'] . '` ON ' . $qualifiedTarget;
      $this->printer?->verbose('  ' . $dropSql);
      $this->conn->executeStatement($dropSql);
    }
  }

  /**
   * Recreate previously saved MySQL indexes.
   *
   * @param string $qualifiedTableName Qualified table name (db.table)
   */
  protected function enableMysqlIndexes(string $qualifiedTableName): void
  {
    if (empty($this->savedIndexes[$qualifiedTableName])) {
      return;
    }

    [$database, $table] = $this->parseQualifiedName($qualifiedTableName);

    $qualifiedTarget = $database !== null
      ? '`' . $database . '`.`' . $table . '`'
      : '`' . $table . '`';

    $count = count($this->savedIndexes[$qualifiedTableName]);
    $this->printer?->info(sprintf(
      'Recreating %d index(es) on %s',
      $count,
      $qualifiedTableName
    ));

    foreach ($this->savedIndexes[$qualifiedTableName] as $idx) {
      $cols = '`' . implode('`, `', explode(',', $idx['idx_columns'])) . '`';

      $createSql = 'CREATE INDEX `' . $idx['INDEX_NAME'] . '` ON ' . $qualifiedTarget . ' (' . $cols . ')';
      $this->printer?->verbose('  ' . $createSql);
      $this->conn->executeStatement($createSql);
    }
  }

  // ---------- Helpers ----------

  /**
   * Split a possibly-qualified table name into [prefix, table].
   *
   * @param string $qualifiedTableName
   * @param string|null $default Default prefix when not qualified
   * @return array{0: string|null, 1: string}
   */
  protected function parseQualifiedName(string $qualifiedTableName, ?string $default = null): array
  {
    $parts = explode('.', $qualifiedTableName, 2);
    if (count($parts) === 2) {
      return [$parts[0], $parts[1]];
    }
    return [$default, $parts[0]];
  }

  /**
   * Guard that initialize() has been called.
   *
   * @throws \RuntimeException
   */
  protected function assertInitialized(): void
  {
    if ($this->conn === null) {
      throw new \RuntimeException('IndexManager::initialize() must be called before use.');
    }
  }
}
