<?php
/**
 * COmanage Registry Transmogrify Utilities
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

namespace Transmogrify\Lib\Util;

use App\Lib\Util\DBALConnection;
use Cake\Console\ConsoleIo;

/**
 * Utility class providing SQL query building and specialized queries for transmogrification
 * Contains reusable SQL template builders and specialized queries for handling COUs and roles
 */
class RawSqlQueries {
  /**
   * Builds SQL query to get maximum ID from a table
   * @param string $qualifiedTableName Fully qualified table name
   * @return string SQL query string
   * @since  COmanage Registry v5.2.0
   */
  public static function buildSelectMaxId(string $qualifiedTableName): string {
    return 'SELECT MAX(id) FROM ' . $qualifiedTableName;
  }

  /**
   * Builds SQL query to count all rows in a table
   * @param string $qualifiedTableName Fully qualified table name
   * @return string SQL query string
   * @since  COmanage Registry v5.2.0
   */
  public static function buildCountAll(string $qualifiedTableName): string {
    return 'SELECT COUNT(*) FROM ' . $qualifiedTableName;
  }

  /**
   * Build a portable COUNT(*) wrapper around an arbitrary SELECT statement.
   * Strips a trailing ORDER BY to satisfy engines that disallow ORDER BY in subqueries.
   *
   * @param string $selectSql Arbitrary SELECT SQL
   * @return string           SQL that returns a single COUNT(*)
   * @since  COmanage Registry v5.2.0
   */
  public static function buildCountFromSelect(string $selectSql): string {
    // Remove trailing ORDER BY ... (simple heuristic, works for our generated queries)
    $sql = preg_replace('/\s+ORDER\s+BY\s+[\s\S]*$/i', '', $selectSql);

    return "SELECT COUNT(*) FROM ($sql) subq";
  }


  /**
   * Builds SQL query to select all rows ordered by ID ascending
   * @param string $qualifiedTableName Fully qualified table name
   * @return string SQL query string
   * @since  COmanage Registry v5.2.0
   */
  public static function buildSelectAllOrderedById(string $qualifiedTableName): string {
    return 'SELECT * FROM ' . $qualifiedTableName . ' ORDER BY id ASC';
  }


  /**
   * Builds SQL query to select all rows, optionally filtering changelog records
   * @param string $qualifiedTableName Fully qualified table name
   * @return string SQL query string
   * @since  COmanage Registry v5.2.0
   */
  public static function buildSelectAll(string $qualifiedTableName): string {
    return "SELECT * FROM $qualifiedTableName";
  }

  /**
   * Builds SQL query to select all rows, filtering changelog records
   * @param string $qualifiedTableName Fully qualified table name
   * @param string $changelogFK Changelog Foreign Key column name
   * @return string SQL query returning all non-changelog records
   * @since  COmanage Registry v5.2.0
   */
  public static function buildSelectAllWithNoChangelong(
    string $qualifiedTableName,
    string $changelogFK
  ): string {
    return "SELECT * FROM $qualifiedTableName WHERE $changelogFK IS NULL";
  }

  /**
   * Builds SQL query to reset sequence/auto-increment value for a table
   * @param string $qualifiedTableName Complete table name including schema if applicable
   * @param int $nextId Next sequence/auto-increment value to set
   * @param bool $isMySQL True if target is MySQL, false for PostgreSQL
   * @return string SQL query string to reset sequence
   * @since  COmanage Registry v5.2.0
   */
  public static function buildSequenceReset(string $qualifiedTableName, int $nextId, bool $isMySQL): string {
    if($isMySQL) {
      return 'ALTER TABLE ' . $qualifiedTableName . ' AUTO_INCREMENT = ' . $nextId;
    }
    return  'ALTER SEQUENCE ' . $qualifiedTableName . '_id_seq RESTART WITH ' . $nextId;
  }


  /**
   * Sets the sequence/auto-increment ID for a target table based on the maximum ID from a source table.
   *
   * @param DBALConnection $inconn Connection to source database
   * @param DBALConnection $outconn Connection to target database
   * @param string $sourceTable Name of source table
   * @param string $targetTable Name of target table
   * @param CommandLinePrinter CommandLinePrinter IO object for output
   * @return bool                       True if sequence was reset successfully, false otherwise
   * @since  COmanage Registry v5.2.0
   */
  public static function setSequenceId(
    DBALConnection $inconn,
    DBALConnection $outconn,
    string $sourceTable,
    string $targetTable,
    CommandLinePrinter $cmdPrinter,
  ): bool {
    $qualifiedTableName = $inconn->qualifyTableName($sourceTable);
    $maxId = $inconn->fetchOne(self::buildSelectMaxId($qualifiedTableName));
    $maxId = ((int)($maxId ?? 0)) + 1;

    $qualifiedTableName = $outconn->qualifyTableName($targetTable);
    $cmdPrinter->info("Resetting primary key sequence for $qualifiedTableName to $maxId");

    // Strictly speaking we should use prepared statements, but we control the
    // data here, and also we're executing a maintenance operation (so query
    // optimization is less important).
    $outsql = RawSqlQueries::buildSequenceReset($qualifiedTableName, $maxId, $outconn->isMySQL());
    try {
      $outconn->executeQuery($outsql);
    } catch (\Exception $e) {
      return false;
    }

    return true;
  }

  /**
   * Return SQL used to select COUs from inbound database.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string $tableName Name of the SQL table
   * @param  bool   $isMySQL  Whether the database is MySQL
   * @return string SQL string to select rows from inbound database
   */
  public static function couSqlSelect(string $tableName, bool $isMySQL): string {
    if($isMySQL) {
      $sqlTemplate = RawSqlQueries::COU_SQL_SELECT_TEMPLATE_MYSQL;
    } else {
      $sqlTemplate = RawSqlQueries::COU_SQL_SELECT_TEMPLATE_POSTGRESQL;
    }

    return str_replace('{table}', $tableName, $sqlTemplate);
  }

  /**
   * Select history records that are "current" (no changelog link) and whose
   * org_identity_id is either NULL or refers to an included Org Identity
   * (has a current link to a non-null co_person_id).
   *
   * @param string $tableName
   * @param bool   $isMySQL Unused here; kept for consistent signature
   * @return string
   */
  public static function historyRecordsSqlSelect(string $tableName, bool $isMySQL): string {
    return str_replace('{table}', $tableName, RawSqlQueries::HISTORY_RECORDS_SQL_SELECT);
  }

  /**
   * Select job history records that are "current" (no changelog link) and whose
   * org_identity_id is either NULL or refers to an included Org Identity
   * (has a current link to a non-null co_person_id).
   *
   * @param string $tableName
   * @param bool   $isMySQL Unused here; kept for consistent signature
   * @return string
   */
  public static function jobHistoryRecordsSqlSelect(string $tableName, bool $isMySQL): string {
    return str_replace('{table}', $tableName, RawSqlQueries::HISTORY_RECORDS_SQL_SELECT);
  }

  /**
   * Return SQL used to select COUs from inbound database.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string $tableName Name of the SQL table
   * @param  bool   $isMySQL  Whether the database is MySQL
   * @return string SQL string to select rows from inbound database
   */
  public static function roleSqlSelect(string $tableName, bool $isMySQL): string {
    return RawSqlQueries::ROLE_SQL_SELECT;
  }


  /**
   * Builds SQL query to select Multiple Value Element Attributes (MVEAs) for valid org identities
   *
   * @param string $tableName Name of the database table containing MVEAs
   * @param bool $isMySQL Whether the target database is MySQL (true) or PostgreSQL (false)
   * @return string SQL query string to select MVEA rows that are linked to valid org identities
   * @since  COmanage Registry v5.2.0
   */
  public static function mveaSqlSelect(string $tableName, bool $isMySQL, array $fkColumns = []): string {
    // Defaults to co_person_id and org_identity_id
    if (empty($fkColumns)) {
      $fkColumns = ['co_person_id', 'org_identity_id'];
    }

    // XXX Unsupported FKs for now (until their models are implemented)
    //     co_department_id, co_provisioning_target_id, organization_id
    $unsupportedFks = [
      'co_department_id',
      'co_provisioning_target_id',
      'organization_id'
    ];

    // In full mode, treat all as supported; otherwise split into supported/unsupported
    if (false /* $fullMode */) {
      $supportedInUse   = array_values($fkColumns);
      $unsupportedInUse = [];
    } else {
      // Split provided FKs into supported (for OR non-null) and unsupported (must be NULL)
      $supportedInUse   = array_values(array_diff($fkColumns, $unsupportedFks));
      $unsupportedInUse = array_values(array_intersect($fkColumns, $unsupportedFks));
    }

    // Require at least one SUPPORTED FK is not NULL (unsupported FKs are excluded from this OR)
    $nonnullClauses = array_map(
      fn(string $c) => 'n.' . $c . ' IS NOT NULL',
      $supportedInUse
    );
    // Keep SQL valid even if no supported FKs present
    $nonnullAny = empty($nonnullClauses) ? '1=1' : '(' . implode(' OR ', $nonnullClauses) . ')';

    // Unsupported FKs (that are present) must be NULL (AND clause)
    $unsupportedNullClause = '';
    if (!empty($unsupportedInUse)) {
      $unsupportedNullClause = 'AND ' . implode(
          ' AND ',
          array_map(fn(string $c) => "n.$c IS NULL", $unsupportedInUse)
        );
    }

    // If org_identity_id is one of the FKs, apply the org identity validity EXISTS
    $orgIdCheck = '';
    if (in_array('org_identity_id', $fkColumns, true)) {
      // If we decide to enable the soft delete we need to take into account
      // the type of database
      // - (p.deleted IS NULL OR p.deleted = false) on PostgreSQL
      // - (p.deleted IS NULL OR p.deleted = 0) on MySQL
      $orgIdCheck = <<<SQL
        AND (
          n.org_identity_id IS NULL
          OR EXISTS (
            SELECT 1
            FROM cm_org_identities AS oi
            WHERE oi.id = n.org_identity_id
              AND EXISTS (
                SELECT 1
                FROM cm_co_org_identity_links AS coil
                JOIN cm_co_people AS p
                  ON p.id = coil.co_person_id
                WHERE coil.org_identity_id = oi.id
                  AND coil.co_org_identity_link_id IS NULL
                  AND coil.co_person_id IS NOT NULL
                -- AND (p.deleted IS NULL OR p.deleted = false) -- include if you need soft-delete exclusion
              )
          )
        )
      SQL;
    }

    $mveaSqlSelect = <<<SQL
      SELECT *
      FROM {table} AS n
      WHERE
        $nonnullAny
        $orgIdCheck
        $unsupportedNullClause
      ORDER BY n.id;
    SQL;

    return str_replace('{table}', $tableName, $mveaSqlSelect);
  }

  /**
   * Return SQL used to select Organization Identities from inbound database.
   *
   * @param string $tableName Name of the database table containing organization identities
   * @param bool $isMySQL Whether the target database is MySQL (true) or PostgreSQL (false)
   * @return string SQL string to select organization identity rows from inbound database
   * @since  COmanage Registry v5.2.0
   */
  public static function orgidentitiesSqlSelect(string $tableName, bool $isMySQL): string {
    return RawSqlQueries::ORG_IDENTITIES_SQL_SELECT;
  }

  // Any COU at any time can be made the child of another COU
  // and so during transmogrification we cannot simply select
  // the rows of the COU table by ascending id because it leads
  // to foreign key constraints errors since a parent with a larger
  // value for id may not be in the outbound table when a child COU
  // is processed.
  //
  // Instead we need to order the rows for the COU inbound table by
  // generation starting with generation 0 which has no parents.
  // To do this we use a Common Table Expression (CTE), specifically
  // WITH RECURSIVE. See https://www.postgresql.org/docs/current/queries-with.html#QUERIES-WITH-RECURSIVE
  // for PostgreSQL and https://dev.mysql.com/doc/refman/8.0/en/with.html#common-table-expressions-recursive
  // for MySQL. This is now a standard technique for sorting hierarchical or tree-structured
  // data.
  //
  // Our need is more complicated than the standard example because in addition to the
  // column parent_id our table also has the column cou_id used by ChangelogBehavior
  // as a foreign key back to id. Because of this any row may appear more than once
  // in the final intermediate table computed during recursion. We handle this by using
  // GROUP BY id in the final SELECT and then using aggregate functions for all columns except for
  // id.
  //
  // Unfortunately PostgreSQL and MySQL do not define the same aggregate functions so we need a unique
  // SQL template for each below.
  final const COU_SQL_SELECT_TEMPLATE_MYSQL = <<<SQL
    WITH RECURSIVE generation AS (
          SELECT
            id,
            co_id,
            name,
            description,
            parent_id,
            lft,
            rght,
            created,
            modified,
            cou_id,
            revision,
            deleted,
            actor_identifier,
            0 AS generation_number
          FROM {table}
          WHERE parent_id IS NULL AND cou_id IS NULL

        UNION ALL

          SELECT
            child.id,
            child.co_id,
            child.name,
            child.description,
            child.parent_id,
            child.lft,
            child.rght,
            child.created,
            child.modified,
            child.cou_id,
            child.revision,
            child.deleted,
            child.actor_identifier,
            generation_number+1 AS generation_number
          FROM {table} child
          JOIN generation g
            ON (g.id = child.parent_id) OR (g.id = child.cou_id)
    )

    SELECT
      id,
      MAX(co_id) as co_id,
      GROUP_CONCAT(DISTINCT name) as name,
      GROUP_CONCAT(DISTINCT description) as description,
      MAX(parent_id) as parent_id,
      MAX(lft) as lft,
      MAX(rght) as rght,
      MAX(cou_id) as cou_id,
      MAX(revision) as revision,
      MAX(deleted) as deleted,
      GROUP_CONCAT(DISTINCT created) as created,
      GROUP_CONCAT(DISTINCT modified) as modified,
      GROUP_CONCAT(DISTINCT actor_identifier) as actor_identifier
    FROM generation
    GROUP BY id
    ORDER BY MAX(generation_number) ASC;
    SQL;

  /**
   * PostgreSQL template for recursive CTE query to select COU records ordered by generation
   * Uses STRING_AGG for string aggregation and BOOL_AND for boolean aggregation
   */
  final const COU_SQL_SELECT_TEMPLATE_POSTGRESQL = <<<SQL
    WITH RECURSIVE generation AS (
          SELECT
            id,
            co_id,
            name,
            description,
            parent_id,
            lft,
            rght,
            created,
            modified,
            cou_id,
            revision,
            deleted,
            actor_identifier,
            0 AS generation_number
          FROM {table}
          WHERE parent_id IS NULL AND cou_id IS NULL

        UNION ALL

          SELECT
            child.id,
            child.co_id,
            child.name,
            child.description,
            child.parent_id,
            child.lft,
            child.rght,
            child.created,
            child.modified,
            child.cou_id,
            child.revision,
            child.deleted,
            child.actor_identifier,
            generation_number+1 AS generation_number
          FROM {table} child
          JOIN generation g
            ON (g.id = child.parent_id) OR (g.id = child.cou_id)
    )

    SELECT
      id,
      MAX(co_id) as co_id,
      STRING_AGG(DISTINCT name, ',') as name,
      STRING_AGG(DISTINCT description, ',') as description,
      MAX(parent_id) as parent_id,
      MAX(lft) as lft,
      MAX(rght) as rght,
      MAX(cou_id) as cou_id,
      MAX(revision) as revision,
      BOOL_AND(deleted) as deleted,
      MAX(DISTINCT created) as created,
      MAX(DISTINCT modified) as modified,
      STRING_AGG(DISTINCT actor_identifier, ',') as actor_identifier
    FROM generation
    GROUP BY id
    ORDER BY MAX(generation_number) ASC;
    SQL;


  /**
   * SQL template for selecting history records that are not changelog entries and have valid org identities
   */
  final const HISTORY_RECORDS_SQL_SELECT = <<<SQL
      SELECT *
      FROM {table} AS hr
      WHERE
        hr.org_identity_id IS NULL
        OR EXISTS (
          SELECT 1
          FROM cm_org_identities AS oi
          WHERE oi.id = hr.org_identity_id
            AND EXISTS (
              SELECT 1
              FROM cm_co_org_identity_links AS coil
              JOIN cm_co_people AS p
                ON p.id = coil.co_person_id
              WHERE coil.org_identity_id = oi.id
                AND coil.co_org_identity_link_id IS NULL
                AND coil.co_person_id IS NOT NULL
            )
        )
      ORDER BY hr.id;
    SQL;

  /**
   * SQL template for selecting organization identities that have at least one org identity link
   */
  final const ORG_IDENTITIES_SQL_SELECT = <<<SQL
    -- Org identities whose current (non-changelog) link points to an existing Person
    SELECT *
    FROM cm_org_identities oi
    WHERE EXISTS (
      SELECT 1
      FROM cm_co_org_identity_links coil
      JOIN cm_co_people p
        ON p.id = coil.co_person_id
      WHERE coil.org_identity_id = oi.id
        AND coil.co_org_identity_link_id IS NULL   -- current rows only
        AND coil.co_person_id IS NOT NULL          -- require a person reference
        -- also exclude soft-deleted people:
        -- AND (p.deleted IS NULL OR p.deleted = false)
    )
    ORDER BY oi.id;
  SQL;

  /**
   * SQL template for selecting role records with affiliation defaulting to 'member'
   * Uses recursive CTE to handle role history/changelog relationships
   */
  final const ROLE_SQL_SELECT = <<<SQL
    WITH RECURSIVE generation AS (
      SELECT
          id,
          co_person_id,
          sponsor_co_person_id,
          manager_co_person_id,
          cou_id,
          CASE
            WHEN affiliation IS NULL THEN 'member'
            WHEN affiliation = '' THEN 'member'
            ELSE affiliation
          END AS affiliation,
          title,
          o,
          ou,
          valid_from,
          valid_through,
          ordr,
          status,
          source_org_identity_id,
          created,
          modified,
          co_person_role_id,
          revision,
          deleted,
          actor_identifier,
          1 AS generation_number
      FROM cm_co_person_roles
      WHERE co_person_role_id IS NULL
    
      UNION ALL
    
      SELECT
          child.id,
          child.co_person_id,
          child.sponsor_co_person_id,
          child.manager_co_person_id,
          child.cou_id,
          CASE
            WHEN child.affiliation IS NULL THEN 'member'
            WHEN child.affiliation = '' THEN 'member'
            ELSE child.affiliation
          END AS affiliation,
          child.title,
          child.o,
          child.ou,
          child.valid_from,
          child.valid_through,
          child.ordr,
          child.status,
          child.source_org_identity_id,
          child.created,
          child.modified,
          child.co_person_role_id,
          child.revision,
          child.deleted,
          child.actor_identifier,
          g.generation_number + 1 AS generation_number
      FROM cm_co_person_roles child
      JOIN generation g
        ON g.id = child.co_person_role_id
    )
    
    SELECT
        id,
        co_person_id,
        sponsor_co_person_id,
        manager_co_person_id,
        cou_id,
        affiliation,
        title,
        o,
        ou,
        valid_from,
        valid_through,
        ordr,
        status,
        source_org_identity_id,
        created,
        modified,
        co_person_role_id,
        revision,
        deleted,
        actor_identifier
    FROM generation
    ORDER BY generation_number ASC, id ASC;
    SQL;

  /**
   * SQL template for checking health of organization identities and related data
   * Analyzes links between org identities and co_person records
   */
  final const ORGIDENTITIES_HEALTH_SQL_QUERY = <<<SQL
        -- Summary of Org Identity inclusion/exclusion based on non-historical links
        -- "Non-historical link" means a row where co_org_identity_link_id IS NULL
        
        SELECT 'A) No non-historical OrgIdentityLink rows (co_org_identity_link_id IS NULL)' AS reason,
               0 AS included_count,
               COUNT(*) AS excluded_count,
               'x' AS indicator
        FROM cm_org_identities oi
        WHERE NOT EXISTS (
          SELECT 1
          FROM cm_co_org_identity_links coil
          WHERE coil.org_identity_id = oi.id
            AND coil.co_org_identity_link_id IS NULL
        )
        UNION ALL
        SELECT 'B) Has non-historical link(s) but all co_person_id are NULL' AS reason,
               0 AS included_count,
               COUNT(*) AS excluded_count,
               'x' AS indicator
        FROM cm_org_identities oi
        WHERE EXISTS (
          SELECT 1
          FROM cm_co_org_identity_links coil
          WHERE coil.org_identity_id = oi.id
            AND coil.co_org_identity_link_id IS NULL
        )
        AND NOT EXISTS (
          SELECT 1
          FROM cm_co_org_identity_links coil
          WHERE coil.org_identity_id = oi.id
            AND coil.co_org_identity_link_id IS NULL
            AND coil.co_person_id IS NOT NULL
        )
        UNION ALL
        SELECT 'C) Has at least one non-historical link with a non-NULL co_person_id' AS reason,
               COUNT(DISTINCT oi.id) AS included_count,
               0 AS excluded_count,
               '✓' AS indicator
        FROM cm_org_identities oi
        WHERE EXISTS (
          SELECT 1
          FROM cm_co_org_identity_links coil
          WHERE coil.org_identity_id = oi.id
            AND coil.co_org_identity_link_id IS NULL
            AND coil.co_person_id IS NOT NULL
        )
        UNION ALL
        -- Totals
        SELECT 'Included (total)' AS reason,
               COUNT(DISTINCT oi.id) AS included_count,
               0 AS excluded_count,
               '✓' AS indicator
        FROM cm_org_identities oi
        WHERE EXISTS (
          SELECT 1
          FROM cm_co_org_identity_links coil
          WHERE coil.org_identity_id = oi.id
            AND coil.co_org_identity_link_id IS NULL
            AND coil.co_person_id IS NOT NULL
        )
        UNION ALL
        SELECT 'Excluded (total)' AS reason,
               0 AS included_count,
               COUNT(*) AS excluded_count,
               'x' AS indicator
        FROM cm_org_identities oi
        WHERE
          -- No non-historical link
          NOT EXISTS (
            SELECT 1
            FROM cm_co_org_identity_links coil
            WHERE coil.org_identity_id = oi.id
              AND coil.co_org_identity_link_id IS NULL
          )
          OR
          -- Has non-historical link(s) but none with a non-NULL co_person_id
          (
            EXISTS (
              SELECT 1
              FROM cm_co_org_identity_links coil
              WHERE coil.org_identity_id = oi.id
                AND coil.co_org_identity_link_id IS NULL
            )
            AND NOT EXISTS (
              SELECT 1
              FROM cm_co_org_identity_links coil
              WHERE coil.org_identity_id = oi.id
                AND coil.co_org_identity_link_id IS NULL
                AND coil.co_person_id IS NOT NULL
            )
          )
        UNION ALL
        SELECT 'Total Org Identities' AS reason,
               COUNT(*) AS included_count,
               0 AS excluded_count,
               '-' AS indicator
        FROM cm_org_identities
        ORDER BY reason;
  SQL;

  /**
   * SQL template for checking health of groups names against AR-Group-9 rule
   * - Invalid when group_type = 'S' and (name contains ":" OR name equals "CO" case-insensitively)
   * - Produces human-readable reasons with included/excluded columns and an indicator
   */
  final const STANDARD_GROUP_ARG9_SQL_QUERY = <<<SQL
      SELECT 'Invalid: Standard group name contains ":"' AS reason,
             0 AS included_count,
             COUNT(*) AS excluded_count,
             'x' AS indicator
        FROM cm_co_groups
       WHERE group_type = 'S' AND name LIKE '%:%'
      UNION ALL
      SELECT 'Invalid: Standard group name equals "CO"' AS reason,
             0 AS included_count,
             COUNT(*) AS excluded_count,
             'x' AS indicator
        FROM cm_co_groups
       WHERE group_type = 'S' AND UPPER(TRIM(name)) = 'CO'
      UNION ALL
      SELECT 'Valid: Does not violate the naming rule' AS reason,
             COUNT(*) AS included_count,
             0 AS excluded_count,
             '✓' AS indicator
        FROM cm_co_groups
       WHERE NOT (
               group_type = 'S'
               AND (name LIKE '%:%' OR UPPER(TRIM(name)) = 'CO')
             )
      UNION ALL
      SELECT 'Invalid (total)' AS reason,
             0 AS included_count,
             COUNT(*) AS excluded_count,
             'x' AS indicator
        FROM cm_co_groups
       WHERE group_type = 'S'
         AND (name LIKE '%:%' OR UPPER(TRIM(name)) = 'CO')
      UNION ALL
      SELECT 'Valid (total)' AS reason,
             COUNT(*) AS included_count,
             0 AS excluded_count,
             '✓' AS indicator
        FROM cm_co_groups
       WHERE NOT (
               group_type = 'S'
               AND (name LIKE '%:%' OR UPPER(TRIM(name)) = 'CO')
             )
      UNION ALL
      SELECT 'Total Groups' AS reason,
             COUNT(*) AS included_count,
             0 AS excluded_count,
             '-' AS indicator
        FROM cm_co_groups
      ORDER BY reason;
    SQL;
}
