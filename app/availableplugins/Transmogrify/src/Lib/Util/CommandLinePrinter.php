<?php

namespace Transmogrify\Lib\Util;

use Cake\Console\ConsoleIo;

/**
 * Handles console output formatting including progress bars and colored messages
 *
 * @since  COmanage Registry v5.2.0
 */
class CommandLinePrinter
{

  /**
   * Console IO instance for handling input/output
   *
   * @var ConsoleIo|null
   * @since  COmanage Registry v5.2.0
   */
  private ?ConsoleIo $io;

  /**
   * Total number of steps in the progress
   *
   * @var int
   * @since  COmanage Registry v5.2.0
   */
  private int $total = 0;

  /**
   * Current step in the progress
   *
   * @var int
   * @since  COmanage Registry v5.2.0
   */
  private int $current = 0;

  /**
   * Number of message lines printed
   *
   * @var int
   * @since  COmanage Registry v5.2.0
   */
  private int $messageLines = 0;

  /**
   * Color of the progress bar
   *
   * @var string
   * @since  COmanage Registry v5.2.0
   */
  private string $barColor;

  /**
   * Width of the progress bar in characters
   *
   * @var int
   * @since  COmanage Registry v5.2.0
   */
  private int $barWidth;

  /**
   * Whether to use ANSI colors in output
   *
   * @var bool
   * @since  COmanage Registry v5.2.0
   */
  private bool $useColors;

  /**
   * Whether the progress bar is currently active
   *
   * @var bool
   * @since  COmanage Registry v5.2.0
   */
  private bool $barActive = false;

  /**
   * Constructor
   *
   * @param ConsoleIo|null $io Console IO instance for handling input/output
   * @param string $barColor Color of the progress bar ('blue' or 'green')
   * @param int $barWidth Width of the progress bar in characters
   * @param bool $useColors Whether to use ANSI colors in output
   * @since  COmanage Registry v5.2.0
   */
  public function __construct(?ConsoleIo $io = null, string $barColor = 'blue', int $barWidth = 50, bool $useColors = true)
  {
    $this->io = $io;
    $this->barColor = in_array($barColor, ['blue', 'green'], true) ? $barColor : 'blue';
    $this->barWidth = max(10, $barWidth);
    $this->useColors = $useColors;

    // Info is forced to be white
    $this->io->setStyle('info', ['text' => '0;39']);
    $this->io->setStyle('question', ['text' => '0;39']);
  }

  /**
   * Start displaying a new progress bar
   *
   * @param int $total Total number of steps
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  public function start(int $total): void
  {
    // When verbose is enabled, do not draw the progress bar at all
    if ($this->getVerboseLevel() > 1) {
      return;
    }

    $this->total = max(0, $total);
    $this->current = 0;
    $this->messageLines = 0;
    $this->barActive = true;

    // Hard reset the current line, then move to a brand new line
    // \r   -> move to column 0
    // \033[K -> clear to end of line
    // \n  -> go to next line
    $this->rawWrite("\r\033[2K");

    // Draw initial progress bar line (no leading \r, we are already at col 0)
    $this->rawWrite($this->formatBar(0));

    // Save cursor position at the end of the progress bar line
    $this->rawWrite("\033[s");

    // Move to message area (one line below the bar)
    $this->rawWrite("\033[1B\r");
  }

  /**
   * Update the progress bar to show current progress
   *
   * @param int $current Current step number
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  public function update(int $current): void
  {
    // When verbose is enabled, do not draw the progress bar at all
    if ($this->getVerboseLevel() > 1 || !$this->barActive) {
      return;
    }

    $this->current = min(max(0, $current), $this->total);

    // Restore to bar line, redraw bar, save, then go back to message area
    $this->rawWrite("\033[u");             // restore saved cursor (bar line end)
    $this->rawWrite("\r" . $this->formatBar($this->current));
    $this->rawWrite("\033[s");             // save again at end of bar
    $down = 1 + $this->messageLines;       // one below bar + existing messages
    $this->rawWrite("\033[" . $down . "B\r");
  }

  /**
   * Complete and cleanup the progress bar display
   *
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  public function finish(): void
  {
    // When verbose is enabled, do not draw the progress bar at all
    if ($this->getVerboseLevel() > 1 || !$this->barActive) {
      return;
    }

    $this->update($this->total);

    // Move the cursor to the line AFTER the whole message area,
    // so subsequent output doesn't overwrite the bar.
    $this->rawWrite("\033[u");                 // restore saved position at end of bar line
    $down = 1 + $this->messageLines;           // one below bar + all message lines
    $this->rawWrite("\033[" . $down . "B\r");  // move down
    $this->rawWrite(PHP_EOL);                  // clean newline below

    // Re-anchor saved cursor to this clean line so future restores are safe
    $this->rawWrite("\033[s");

    // Reset internal counters so next run starts fresh
    $this->messageLines = 0;
    $this->barActive = false;
    $this->total = 0;
    $this->current = 0;
  }

  /**
   * Display an out level message
   *
   * @param string $message Message to display
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  public function out(string $message): void
  {
    $this->message($message, 'out');
  }

  /**
   * Display an info level message
   *
   * @param string $message Message to display
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  public function info(string $message): void
  {
    $this->message($message, 'info');
  }

  /**
   * Display a warning level message (alias of warn())
   *
   * @param string $message Message to display
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  public function warning(string $message): void
  {
    $this->message($message, 'warn');
  }

  /**
   * Display an error level message
   *
   * @param string $message Message to display
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  public function error(string $message): void
  {
    $this->message($message, 'error');
  }

  /**
   * Display a debug level message
   *
   * @param string $message Message to display
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  public function debug(string $message): void
  {
    $this->message($message, 'debug');
  }

  /**
   * Display a verbose level message
   *
   * @param string $message Message to display
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  public function verbose(string $message): void
  {
    $this->message($message, 'verbose');
  }

  /**
   * Display a message with the specified level
   *
   * @param string $message Message to display
   * @param string $level Message level (info, warn, error, debug, verbose)
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  public function message(string $message, string $level = 'info'): void
  {
    // Suppress verbose messages unless verbose mode is enabled
    if (!$this->shouldPrintLevel($level)) {
      return;
    }

    $lines = $this->colorizeLevel($level, $message);
    // Ensure the message ends with a newline so we can count line advances
    if ($lines === '' || substr($lines, -1) !== "\n") {
      $lines .= "\n";
    }


    if ($this->barActive) {
      // Always print below the bar
      $this->rawWrite("\033[u");                     // restore to end of bar line
      $down = 1 + $this->messageLines;
      $this->rawWrite("\033[" . $down . "B");        // move to message area line
      // Clear the entire message line and reset cursor to column 0
      $this->rawWrite("\r\033[2K");
      $this->rawWrite($lines);
      $this->messageLines += substr_count($lines, "\n");

      // Return to bar line and re-save for next update
      $this->rawWrite("\033[u");
      $this->rawWrite("\033[s");
    } else {
      // No bar: clear current line completely and print from column 0
      $this->rawWrite("\r\033[2K");
      $this->rawWrite($lines);
    }
  }

  /**
   * Prompt for user input
   *
   * @param string $prompt Prompt to display
   * @param string|null $default Default value if no input provided
   * @return string|null User input or default value
   * @since  COmanage Registry v5.2.0
   */
  public function ask(string $prompt, ?string $default = null): ?string
  {
    // Ensure the message area exists (one line below the bar)
    if ($this->messageLines === 0) {
      $this->rawWrite(PHP_EOL);
    }

    $answer = null;

    if ($this->io) {
      // ConsoleIo handles rendering the prompt and reading input
      $answer = $this->io->ask($prompt, $default);
    } else {
      // Fallback to STDOUT/STDIN
      $this->rawWrite($prompt . ' ');
      $line = fgets(STDIN);
      $answer = ($line === false) ? null : rtrim($line, "\r\n");
      if ($answer === null && $default !== null) {
        $answer = $default;
      }
      // Ensure the cursor advances to the next line after the prompt
      $this->rawWrite(PHP_EOL);
    }

    // A prompt line was added to the message area
    $this->messageLines += 1;

    // Redraw progress bar and return cursor to the end of the message area
    $this->rawWrite("\033[u");                     // restore to saved bar position
    $this->rawWrite("\r" . $this->formatBar($this->current));
    $this->rawWrite("\033[s");                     // save bar position again
    $this->rawWrite("\033[" . $this->messageLines . "B\r"); // move down to message area

    return $answer;
  }

  /**
   * Pause execution until user presses enter
   *
   * @param string $prompt Prompt to display
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  public function pause(string $prompt = 'Press <enter> to continue...'): void
  {
    $this->ask($prompt, '');
  }

  /**
   * Add color formatting to a message based on its level
   *
   * @param string $level Message level
   * @param string $message Message to colorize
   * @return string Formatted message
   * @since  COmanage Registry v5.2.0
   */
  private function colorizeLevel(string $level, string $message): string
  {
    $level = strtolower($level);
    switch ($level) {
      case 'warn':
      case 'warning':
        $prefix = '[WARN] ';
        break;
      case 'error':
        $prefix = '[ERROR] ';
        break;
      case 'debug':
        $prefix = '[DEBUG] ';
        break;
      case 'verbose':
        $prefix = '[VERBOSE] ';
        break;
      case 'info':
        $prefix = '[INFO] ';
        break;
      default:
        $prefix = '';
    }

    // For INFO, render the label (up to the first colon) in white, value unchanged (or green if white info default)
    if ($level === 'info' || $level === 'out') {
      $formatted = $this->useColors ? $this->formatLabelWhite($message) : $message;
      return $prefix . $formatted;
    }

    $color = $this->defaultColorForLevel($level);
    $text = $prefix . $message;
    if ($this->useColors && $color) {
      return $this->wrapColor($text, $color);
    }
    return $text;
  }

  /**
   * Get the default color for a message level
   *
   * @param string $level Message level
   * @return string|null Color name or null if no color
   * @since  COmanage Registry v5.2.0
   */
  private function defaultColorForLevel(string $level): ?string
  {
    $level = strtolower($level);
    return match ($level) {
      'warn', 'warning' => 'yellow',
      'error' => 'red',
      'debug' => 'cyan',
      default => 'white',
    };
  }

  /**
   * Format the progress bar string
   *
   * @param int $current Current progress value
   * @return string Formatted progress bar
   * @since  COmanage Registry v5.2.0
   */
  private function formatBar(int $current): string
  {
    $total = max(1, $this->total);
    $pct = (int) floor(($current / $total) * 100);

    $width = $this->barWidth;
    $filled = (int) floor(($pct / 100) * $width);
    $remaining = max(0, $width - $filled);

    $filledStr = str_repeat('=', max(0, $filled - 1)) . ($filled > 0 ? '>' : '');
    $emptyStr = str_repeat('.', $remaining);

    $bar = sprintf('[%s%s] %3d%% (%d/%d)', $filledStr, $emptyStr, $pct, $current, $this->total);

    // Clear the line to the right to avoid remnants on shorter redraws
    $bar .= "\033[K";

    if ($this->useColors) {
      $color = $this->barColor === 'green' ? 'green' : 'blue';
      $bar = $this->wrapColor($bar, $color);
    }
    return $bar;
  }

  /**
   * Wrap text in ANSI color codes
   *
   * @param string $text Text to colorize
   * @param string $color Color name
   * @return string Color-wrapped text
   * @since  COmanage Registry v5.2.0
   */
  private function wrapColor(string $text, string $color): string
  {
    $map = [
      'red'    => '0;31',
      'green'  => '0;32',
      'yellow' => '0;33',
      'blue'   => '0;34',
      'magenta'=> '0;35',
      'cyan'   => '0;36',
      'white'   => '0;39',
    ];
    $code = $map[$color] ?? null;
    if (!$code) { return $text; }
    return "\033[{$code}m{$text}\033[0m";
  }

  /**
   * Write raw string to output
   *
   * @param string $str String to write
   * @return void
   * @since  COmanage Registry v5.2.0
   */
  private function rawWrite(string $str): void
  {
    if ($this->io) {
      // ConsoleIo::out() defaults to a trailing newline; we want raw text
      $this->io->out($str, 0, 0);
    } else {
      // Fallback to STDOUT
      echo $str;
    }
  }

  /**
   * Format info message with white label
   *
   * @param string $message Message to format
   * @return string Formatted message
   * @since  COmanage Registry v5.2.0
   */
  private function formatLabelWhite(string $message): string
  {
    $lines = explode("\n", $message);
    foreach ($lines as $i => $line) {
      if ($line === '') { continue; }
      if (preg_match('/^([^:\r\n]+:)(.*)$/', $line, $m)) {
        $second = $m[2];
        if (
          $this->useColors
          && ($this->defaultColorForLevel('info') === 'white' || $this->defaultColorForLevel('out') === 'white')
          && $second !== ''
        ) {
          $second = $this->wrapColor($second, 'green');
        }
        $lines[$i] = $this->wrapColor($m[1], 'white') . $second;
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Detect verbose level from ConsoleIO instance
   *
   * @return int Verbose level
   * @since  COmanage Registry v5.2.0
   */
  private function detectVerboseFromIo(): int
  {
    $default = 0;
    if ($this->io === null) {
      return $default;
    }
    if (method_exists($this->io, 'level')) {
      try {
        return $this->io->level();
      } catch (\Throwable $e) {
        return $default;
      }
    }
    return $default;
  }

  /**
   * Determine if a message of the given level should be printed based on verbosity setting
   *
   * @param string $level Message level to check
   * @return bool Whether the message should be printed
   * @since  COmanage Registry v5.2.0
   */
  private function shouldPrintLevel(string $level): bool
  {
    $level = strtolower($level);
    $verboseLevel = $this->detectVerboseFromIo();

    return match ($verboseLevel) {
      0 => in_array($level, ['out', 'error'], true), // only errors and out
      1 => in_array($level, ['out', 'info', 'warn', 'warning', 'error'], true), // no verbose/debug
      2 => in_array($level, ['out', 'info', 'warn', 'warning', 'error', 'debug', 'verbose'], true), // all messages
      default => true,
    };
  }

  /**
   * Get the current verbosity level
   *
   * @return int Verbosity level (0=quiet, 1=normal, 2=verbose)
   * @since  COmanage Registry v5.2.0
   */
  private function getVerboseLevel(): int
  {
    return $this->detectVerboseFromIo();
  }
}
