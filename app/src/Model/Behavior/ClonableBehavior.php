<?php
/**
 * COmanage Registry Clonable Behavior
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

namespace App\Model\Behavior;

use Cake\Event\Event;
use Cake\Log\Log;
use Cake\ORM\Behavior;
use ArrayObject;

class ClonableBehavior extends Behavior 
{
  /**
   * If uuid is empty, assign a new one.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Event       $event   beforeMarshal event
   * @param  ArrayObject $data    Entity data
   * @param  ArrayObject $options Callback options
   */
  
  public function beforeMarshal(Event $event, ArrayObject $data, ArrayObject $options) {
    if(empty($data['uuid'])) {
      $data['uuid'] = \Cake\Utility\Text::uuid();

      // We need to track if we generated a UUID because if an admin manually sets it
      // we need to check for uniqueness
      $data['_uuidGenerated'] = true;
    }
  }
}