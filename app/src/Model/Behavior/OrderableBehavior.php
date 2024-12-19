<?php
/**
 * COmanage Registry Orderable Behavior
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

class OrderableBehavior extends Behavior 
{
  /**
   * If ordr is empty, determine the current maximum ordr value and add 1 to it.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Event       $event   beforeMarshal event
   * @param  ArrayObject $data    Entity data
   * @param  ArrayObject $options Callback options
   */
  
  public function beforeMarshal(Event $event, ArrayObject $data, ArrayObject $options) {
    if(empty($data['ordr'])) {
      // Get the current max value

      $Table = $event->getSubject();

      // We constrain our search by primary key, so ordr will be consecutive within the
      // same configuration (eg provisioning_targets within a CO). Note tables can have
      // multiple primary links (though this is more for MVEAs than configuration objects)
      // though only one should be populated.

      $primaryLink = null;

      $primaryLinks = $Table->getPrimaryLinks();

      foreach($primaryLinks as $p) {
        if(!empty($data[$p])) {
          $primaryLink = $p;
          break;
        }
      }

      if(!$primaryLink) {
        throw new \RuntimeException("No primary link found in OrderableBehavior::beforeMarshal for " . $Table->getTable());
      }

      $query = $Table->find()->where([$primaryLink => $data[$p]]);
      $query->select(['maxorder' => $query->func()->max('ordr', ['ordr'])]);
      
      $row = $query->first();

      if(!empty($row->maxorder)) {
        $data['ordr'] = $row->maxorder+1;
      } else {
        $data['ordr'] = 1;
      }
    }
  }
}