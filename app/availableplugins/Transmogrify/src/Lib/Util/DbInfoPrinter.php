<?php
/**
 * COmanage Registry DB Info Printer
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

use Cake\Console\ConsoleIo;
use Transmogrify\Service\DbInfoService;

/**
 * Prints information about database connections and schema
 */
final class DbInfoPrinter
{
  /** @var string ANSI escape code for green text */
  private const COLOR_GREEN = "\033[32m";
  /** @var string ANSI escape code for red text */
  private const COLOR_RED = "\033[31m";
  /** @var string ANSI escape code to reset text color */
  private const COLOR_RESET = "\033[0m";
  /** @var string ANSI escape code for bold text */
  private const STYLE_BOLD = "\033[1m";

  /** @var self|null Singleton instance */
  private static ?self $instance = null;

  /** @var ConsoleIo Console IO instance */
  private ConsoleIo $io;

  /** @var DbInfoService Database info service */
  private DbInfoService $service;

  /** @var string Absolute path to plugin root directory */
  private string $pluginRoot;

  /**
   * Private constructor for singleton pattern
   * @param ConsoleIo $io Console IO instance for output
   * @param DbInfoService $service Database info service (autowired)
   */
  private function __construct(
    ConsoleIo     $io,
    DbInfoService $service)
  {
    $this->io = $io;
    $this->service = $service;
    $this->pluginRoot = dirname(__DIR__, 3);
  }

  /**
   * Initializes the singleton instance
   * @param ConsoleIo $io Console IO instance for output
   * @param DbInfoService $service Database info service
   * @return self The singleton instance
   */
  public static function initialize(ConsoleIo $io, DbInfoService $service): self
  {
    if (self::$instance === null) {
      self::$instance = new self($io, $service);
    }
    return self::$instance;
  }


  /**
   * Prints database connection and schema information
   * @param bool $asJson Whether to output as JSON
   * @param bool $withPing Whether to test database connectivity
   * @param bool $withSchema Whether to include schema information
   * @param string|null $schemaRole Limit schema info to specific role
   */
  public function print(bool $asJson, bool $withPing, bool $withSchema = false, ?string $schemaRole = null): void
  {
    $aliases = [ 'source' => 'transmogrify', 'target' => 'default' ];
    $data = [];

    foreach ($aliases as $role => $alias) {
      // Base connection info
      $info = $this->service->getConnectionInfo($role);

      if ($withPing) {
        $info['status'] = $this->service->ping($alias);
      }

      if ($withSchema && ($schemaRole === null || $schemaRole === $role)) {
        try {

          $info['schema'] = $this->service->loadSchemaInfo(
            $alias,
            $this->pluginRoot . DS . Transmogrify::TABLES_JSON_PATH);
        } catch (\Throwable $e) {
          $info['schema'] = [ 'error' => $e->getMessage() ];
        }
      }

      $data[$role] = $info;
    }

    if ($asJson) {
      $this->io->out(json_encode($data, JSON_PRETTY_PRINT));
      return;
    }

    foreach (['source', 'target'] as $role) {
      $i = $data[$role] ?? [];
      $header = ucfirst($role) . ':';
      $this->io->out(self::STYLE_BOLD . $header . self::COLOR_RESET);
      $this->io->out(str_repeat('-', strlen($header)));
      $this->io->out('  alias:     ' . ($i['alias'] ?? ''));
      $this->io->out('  configured:' . ((($i['configured'] ?? false)) ? ' yes' : ' no'));
      if (!empty($i['driver'])) $this->io->out('  driver:    ' . $i['driver']);
      if (!empty($i['host'])) $this->io->out('  host:      ' . $i['host']);
      if (!empty($i['port'])) $this->io->out('  port:      ' . $i['port']);
      if (!empty($i['database'])) $this->io->out('  database:  ' . $i['database']);
      if (!empty($i['username'])) $this->io->out('  username:  ' . $i['username']);
      if (!empty($i['dsn'])) $this->io->out('  dsn:       ' . $i['dsn']);
      if (!empty($i['schema'])) {
        // Determine a schema name (for PostgreSQL) and strip schema prefixes for sample tables display
        $schemaName = null;
        $bareSample = [];
        $s = $i['schema'];
        if (!empty($s['sample_tables'])) {
          foreach ($s['sample_tables'] as $t) {
            $dot = strrpos($t, '.');
            if ($dot !== false) {
              $schemaName = $schemaName ?? substr($t, 0, $dot);
              $bareSample[] = substr($t, $dot + 1);
            } else {
              $bareSample[] = $t;
            }
          }
        }
        $s = $i['schema'];
        $schemaHeader = 'Schema:';
        $this->io->out('  ' . self::STYLE_BOLD . $schemaHeader . self::COLOR_RESET);
        $this->io->out('  ' . str_repeat('-', strlen($schemaHeader)));
        if (!empty($schemaName)) {
          $this->io->out('    name:      ' . $schemaName);
        }
        $this->io->out('    empty:     ' . (($s['empty'] ?? false) ? 'yes' : 'no'));
        $this->io->out('    tables:    ' . ($s['table_count'] ?? 0));
        if (!empty($s['sample_tables'])) {
          $sampleHeader = 'Sample tables:';
          $this->io->out('    ' . self::STYLE_BOLD . $sampleHeader . self::COLOR_RESET);
          $this->io->out('    ' . str_repeat('-', strlen($sampleHeader)));
          $list = !empty($bareSample) ? $bareSample : $s['sample_tables'];
          foreach ($list as $t) { $this->io->out('      - ' . $t); }
        }
        if (!empty($s['tables_compare'])) {
          $cmp = $s['tables_compare'];
          // Add a blank line then print comparison headers at top level (aligned with SOURCE/TARGET)
          $this->io->out('');
          $header = 'Tables present (json ∧ db):';
          $this->io->out(self::STYLE_BOLD . $header . self::COLOR_RESET);
          $this->io->out(str_repeat('-', strlen($header)));
          // Tables present in both JSON and DB
          foreach (($cmp['both'] ?? []) as $t) {
            $this->io->out('  ' . self::COLOR_GREEN . '✔' . self::COLOR_RESET . ' ' . $t);
          }
          // Only declared in tables.json
          if (!empty($cmp['only_in_json'])) {
            $onlyJsonHeader = 'Only in tables.json:';
            $this->io->out(self::STYLE_BOLD . $onlyJsonHeader . self::COLOR_RESET);
            $this->io->out(str_repeat('-', strlen($onlyJsonHeader)));
            foreach ($cmp['only_in_json'] as $t) { $this->io->out('  - ' . $t); }
          }
          // Present only in the database (render in 3 columns)
          if (!empty($cmp['only_in_db'])) {
            $onlyDbHeader = 'Only in database:';
            $this->io->out(self::STYLE_BOLD . $onlyDbHeader . self::COLOR_RESET);
            $this->io->out(str_repeat('-', strlen($onlyDbHeader)));
            $list = array_values($cmp['only_in_db']);
            $cols = 3;
            $rows = (int)ceil(count($list) / $cols);
            // Determine max width per column
            $widths = array_fill(0, $cols, 0);
            for ($c = 0; $c < $cols; $c++) {
              for ($r = 0; $r < $rows; $r++) {
                $idx = $r + $rows * $c;
                if ($idx < count($list)) {
                  $len = strlen((string)$list[$idx]);
                  if ($len > $widths[$c]) { $widths[$c] = $len; }
                }
              }
            }
            // Print rows
            for ($r = 0; $r < $rows; $r++) {
              $line = '  ';
              for ($c = 0; $c < $cols; $c++) {
                $idx = $r + $rows * $c;
                if ($idx < count($list)) {
                  $cell = (string)$list[$idx];
                  // No padding after last printed column
                  if ($c === $cols - 1 || ($r + $rows * ($c + 1)) >= count($list)) {
                    $line .= $cell;
                  } else {
                    $line .= str_pad($cell, $widths[$c]) . '  ';
                  }
                }
              }
              $this->io->out($line);
            }
          }
        }
      }

      if (isset($i['status'])) {
        $st = $i['status'];

        $this->io->out('  connectivity: ' . ($st['ok']
            ? self::COLOR_GREEN . '✔ OK' . self::COLOR_RESET
            : self::COLOR_RED . '✘ ERROR' . self::COLOR_RESET));
        if (!empty($st['server'])) $this->io->out('  server:       ' . $st['server']);
        if (!$st['ok'] && !empty($st['error'])) $this->io->out('  error:        ' . $st['error']);
      }
      $this->io->out('');
    }
  }

}
