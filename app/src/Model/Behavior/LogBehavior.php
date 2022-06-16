<?php
/**
 * COmanage Registry Log Behavior
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

namespace App\Model\Behavior;

use Cake\Log\Log;
use Cake\ORM\Behavior;

class LogBehavior extends Behavior 
{
  /**
   * Automatic logging before a find.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Event       $event   The beforeFind event that was fired.
   * @param  Query       $query   Query
   * @param  ArrayObject $options The options for the query
   * @param  boolean     $primary Whether or not this is the root query (vs an associated query)
   */
  
// XXX most likely we can't use Cake's log level to suppress trace logging, since it
// isn't a log level. Maybe define a global config option like "trace=true"?
  public function beforeFind(\Cake\Event\Event $event, \Cake\ORM\Query $query, \ArrayObject $options, bool $primary) {
    $subject = $event->getSubject();
    $label = getmypid() . "/" . $subject->getAlias() . ": ";
// XXX can we inject IP address of requester (where available)?

    Log::info($label . 'beforeFind: ' . $query->sql(), ['scope' => ['trace']]);
  }
  
// XXX don't define log() since it will collide with LogTrait?
  
  public function trace(string $msg) {
    
  }
  
  public static function strace(string $name, string $msg) {
    $label = getmypid() . "/" . $name . ": ";
    
    Log::info($label . $msg, ['scope' => ['trace']]);
  }
}