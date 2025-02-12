<?php
/**
 * COmanage Registry Timezone Behavior
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

use Cake\Event\Event;
use Cake\Log\Log;
use Cake\ORM\Behavior;
use ArrayObject;

class TimezoneBehavior extends Behavior 
{
  // Track timezone for models that need to convert to/from UTC
  protected $tz = null;
  
  // Which fields to look at?
  protected $fields = ['valid_from', 'valid_through'];
  
  /**
   * Convert timestamps to UTC for database saves prior to data marshaling.
   * The expectation is this will only be functional for the UI, where $this->tz
   * is set. (The API is expected to provide times in UTC.) For rendering,
   * FieldHelper::control() and Standard index.php will adjust back to the local
   * timezone.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Event       $event   beforeMarshal event
   * @param  ArrayObject $data    Entity data
   * @param  ArrayObject $options Callback options
   */
  
  public function beforeMarshal(Event $event, ArrayObject $data, ArrayObject $options) {
    if($this->tz && $this->tz->getName() != 'UTC') {
      // There's some Cake support for doing timezone conversion, but it's not really
      // well documented, so we use PHP calls directly.
      
      foreach($this->fields as $f) {
        if(!empty($data[$f])) {
          // This returns a DateTime object adjusting for localTZ
          $offsetDT = new \DateTime($data[$f], $this->tz);
          $data[$f] = date("Y-m-d H:i:s", $offsetDT->getTimestamp());
        }
      }
    }
  }
  
  /**
   * Set the current timezone.
   *
   * @since  COmanage Registry v5.0.0
   * @param  DateTimeZone $tz Timezone, eg as determined by AppController::beforeFilter
   */

  public function setTimeZone(\DateTimeZone $tz) {
    $this->tz = $tz;
  }
}