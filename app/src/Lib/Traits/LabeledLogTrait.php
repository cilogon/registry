<?php
/**
 * COmanage Registry Labeled Log Trait
 *
 * Labeled Log Trait automatically injects labeling into log entries
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

namespace App\Lib\Traits;

use \Cake\Log\Log;

trait LabeledLogTrait {
  /**
   * Log a message from an array, with some standard metadata.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $level Log level
   * @param  array  $msg   Log message in the form of an array (that will be converted to JSON)
   */
  
  public function alog(string $level, ?array $msg) {
    return $this->llog($level, json_encode($msg, JSON_PRETTY_PRINT));
  }
  
  /**
   * Log a message, with some standard metadata.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $level Log level
   * @param  string $msg   Log message
   */
  
  public function llog(string $level, string $msg) {
    $bt = debug_backtrace(0, 2);

    $m = getmypid() . " " . $bt[1]['class'] . "::" . $bt[1]['function'] . ": " . $msg;
    
    // We overload $level here, which Cake defines roughly the same way as
    // syslog (alert, info, debug, notice, etc). We add two more: trace and
    // rule, which we transition to scopes (defined in app.php).
    
    if(in_array($level, ['rule', 'trace'])) {
      Log::info($m, ['scope' => [$level]]);
    } else {
      Log::write($level, $m);
    }
  }
}
