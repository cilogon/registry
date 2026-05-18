<?php
/**
 * COmanage Registry Transmogrify Command / Hook Runners Trait
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

namespace Transmogrify\Lib\Traits;

use Transmogrify\Lib\Util\RawSqlQueries;

trait HookRunnersTrait {
  /**
   * Centralized hook runners for readability and validation
   *
   * @since COmanage Registry v5.2.0
   */
  private function runPreTableHook(string $table): void {
    if(empty($this->tables[$table]['preTable'])) { return; }
    $method = $this->tables[$table]['preTable'];
    if(!method_exists($this, $method)) {
      throw new \RuntimeException("Unknown preTable hook: $method");
    }

    $this->cmdPrinter->verbose('Running pre-table hook: ' . ucfirst(preg_replace('/([A-Z])/', ' $1', $method)));
    $this->{$method}();
  }

  /**
   * Run post-table hook if configured for given table
   *
   * @param string $table Table name
   * @return void
   * @throws \RuntimeException If hook method doesn't exist
   * @since COmanage Registry v5.2.0
   */
  private function runPostTableHook(string $table): void {
    if(empty($this->tables[$table]['postTable'])) { return; }
    $method = $this->tables[$table]['postTable'];
    if(!method_exists($this, $method)) {
      throw new \RuntimeException("Unknown postTable hook: $method");
    }
    $this->cmdPrinter->verbose('Running post-table hook: ' . ucfirst(preg_replace('/([A-Z])/', ' $1', $method)));
    $this->{$method}();
  }

  /**
   * Run pre-row hook if configured for given table
   *
   * @param string $table Table name
   * @param array $origRow Original row data by reference
   * @param array $row Current row data by reference
   * @return void
   * @throws \RuntimeException If hook method doesn't exist
   * @since COmanage Registry v5.2.0
   */
  private function runPreRowHook(string $table, array &$origRow, array &$row): void {
    if(empty($this->tables[$table]['preRow'])) { return; }
    $method = $this->tables[$table]['preRow'];
    if(!method_exists($this, $method)) {
      throw new \RuntimeException("Unknown preRow hook: $method");
    }
    $this->cmdPrinter->verbose('Running pre-row hook: ' . ucfirst(preg_replace('/([A-Z])/', ' $1', $method)));
    $this->{$method}($origRow, $row);
  }

  /**
   * Run post-row hook if configured for given table
   *
   * @param string $table Table name
   * @param array $origRow Original row data by reference
   * @param array $row Current row data by reference
   * @return void
   * @throws \RuntimeException If hook method doesn't exist
   * @since COmanage Registry v5.2.0
   */
  private function runPostRowHook(string $table, array &$origRow, array &$row): void {
    if(empty($this->tables[$table]['postRow'])) { return; }
    $method = $this->tables[$table]['postRow'];
    if(!method_exists($this, $method)) {
      throw new \RuntimeException("Unknown postRow hook: $method");
    }
    $this->cmdPrinter->verbose('Running post-row hook: ' . ucfirst(preg_replace('/([A-Z])/', ' $1', $method)));
    $this->{$method}($origRow, $row);
  }

  /**
   * Run SQL select hook if configured for given table
   *
   * @param string $table Table name
   * @param string $qualifiedTableName Fully qualified table name
   * @return string SQL select statement
   * @throws \RuntimeException If hook method doesn't exist
   * @since COmanage Registry v5.2.0
   */
  private function runSqlSelectHook(string $table, string $qualifiedTableName): string {
    $method = $this->tables[$table]['sqlSelect'] ?? '';
    if($method === '') {
      return RawSqlQueries::buildSelectAllOrderedById($qualifiedTableName);
    }
    if(!method_exists(RawSqlQueries::class, $method)) {
      throw new \RuntimeException("Unknown sqlSelect hook: $method");
    }
    $this->cmdPrinter->verbose('Running SQL select hook: ' . ucfirst(preg_replace('/([A-Z])/', ' $1', $method)));

    // Special handling for MVEA-style selects: derive FK columns from fieldMap keys dynamically
    if ($method === 'mveaSqlSelect') {
      // Known FK candidates we care about for MVEA presence checks
      $fkCandidates = [
        'co_person_id',
        'co_person_role_id',
        'org_identity_id',
        'co_group_id',
        'co_department_id',
        'co_provisioning_target_id',
        'organization_id'
      ];
      $fieldMap = $this->tables[$table]['fieldMap'] ?? [];
      $presentFks = array_values(array_intersect($fkCandidates, array_keys($fieldMap)));

      // Fallback to names-like FKs if nothing matched (defensive)
      if (empty($presentFks)) {
        $presentFks = ['co_person_id', 'org_identity_id'];
      }

      return RawSqlQueries::mveaSqlSelect(
        $qualifiedTableName,
        $this->inconn->isMySQL(),
        $presentFks
      );
    }

    return RawSqlQueries::{$method}($qualifiedTableName, $this->inconn->isMySQL());
  }
}