<?php
declare(strict_types=1);

namespace Transmogrify\Lib\Util;

use App\Lib\Util\DBALConnection;
use Cake\Console\ConsoleIo;

class OrgIdentitiesHealth
{
  /**
   * Execute the Org Identities health SQL and print a formatted report.
   *
   * @param DBALConnection $inconn Source/inbound DB connection
   * @param ConsoleIo      $io     Console IO for output
   * @return void
   */
  public static function run(DBALConnection $inconn, ConsoleIo $io): void
  {
    $io->out('Running Org Identities health check...');
    $sql = RawSqlQueries::ORGIDENTITIES_HEALTH_SQL_QUERY;

    try {
      $rows = $inconn->fetchAllAssociative($sql);
    } catch (\Throwable $e) {
      $io->err('Org Identities health check failed: ' . $e->getMessage());
      return;
    }

    if (empty($rows)) {
      $io->out('No results.');
      return;
    }

    // Detect available columns
    $first = $rows[0];
    $hasIncluded = array_key_exists('included_count', $first);
    $hasExcluded = array_key_exists('excluded_count', $first);
    $hasIndicator = array_key_exists('indicator', $first);
    $hasCount = array_key_exists('count', $first);

    // Prepare headers based on detected columns
    if ($hasIncluded || $hasExcluded) {
      $headers = ['Reason', 'Included', 'Excluded'];
      if ($hasIndicator) {
        $headers[] = 'Indicator';
      }
    } else {
      // Fallback to simple reason + count (and indicator if present)
      $headers = ['Reason', 'Count'];
      if ($hasIndicator) {
        $headers[] = 'Indicator';
      }
    }

    // Compute column widths
    $widths = array_fill(0, count($headers), 0);
    $reasonIdx = 0;
    $incIdx = array_search('Included', $headers, true);
    $excIdx = array_search('Excluded', $headers, true);
    $cntIdx = array_search('Count', $headers, true);
    $indIdx = array_search('Indicator', $headers, true);

    // Initialize with header widths
    foreach ($headers as $i => $h) {
      $widths[$i] = max($widths[$i], mb_strlen($h));
    }

    // Measure data
    foreach ($rows as $r) {
      $reasonLen = mb_strlen((string)($r['reason'] ?? ''));
      $widths[$reasonIdx] = max($widths[$reasonIdx], $reasonLen);

      if ($incIdx !== false) {
        $widths[$incIdx] = max($widths[$incIdx], mb_strlen((string)($r['included_count'] ?? '')));
      }
      if ($excIdx !== false) {
        $widths[$excIdx] = max($widths[$excIdx], mb_strlen((string)($r['excluded_count'] ?? '')));
      }
      if ($cntIdx !== false) {
        $widths[$cntIdx] = max($widths[$cntIdx], mb_strlen((string)($r['count'] ?? '')));
      }
      if ($indIdx !== false) {
        $widths[$indIdx] = max($widths[$indIdx], mb_strlen((string)($r['indicator'] ?? '')));
      }
    }

    // Helper to pad a cell
    $pad = static function (string $s, int $w): string {
      $len = mb_strlen($s);
      if ($len >= $w) {
        return $s;
      }
      return $s . str_repeat(' ', $w - $len);
    };

    // Print header
    $lineParts = [];
    foreach ($headers as $i => $h) {
      $lineParts[] = $pad($h, $widths[$i]);
    }
    $io->out(implode('  |  ', $lineParts));

    // Print separator
    $sepParts = array_map(static fn($w) => str_repeat('-', $w), $widths);
    $io->out(implode('--+--', $sepParts));

    // Print rows
    foreach ($rows as $r) {
      $rowParts = [];
      $rowParts[] = $pad((string)($r['reason'] ?? ''), $widths[$reasonIdx]);

      if ($incIdx !== false) {
        $rowParts[] = $pad((string)($r['included_count'] ?? ''), $widths[$incIdx]);
      }
      if ($excIdx !== false) {
        $rowParts[] = $pad((string)($r['excluded_count'] ?? ''), $widths[$excIdx]);
      }
      if ($cntIdx !== false) {
        $rowParts[] = $pad((string)($r['count'] ?? ''), $widths[$cntIdx]);
      }
      if ($indIdx !== false) {
        $indRaw = (string)($r['indicator'] ?? '');
        $cell   = $pad($indRaw, $widths[$indIdx]);

        // Colorize first visible char, leave padding spaces uncolored to preserve alignment
        if ($indRaw === 'x' || $indRaw === '✓') {
          $color = ($indRaw === 'x') ? "\033[31m" : "\033[32m"; // red for x, green for ✓
          $reset = "\033[0m";
          $first = mb_substr($cell, 0, 1);
          $rest  = mb_substr($cell, 1);
          $cell  = $color . $first . $reset . $rest;
        }

        $rowParts[] = $cell;
      }

      $io->out(implode('  |  ', $rowParts));
    }
  }
}
