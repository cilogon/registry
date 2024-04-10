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
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Lib\Util;

class TransmogrifyUtilities {
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
}