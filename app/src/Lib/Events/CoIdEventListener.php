<?php
/**
 * COmanage Registry CoId Event Listener
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

namespace App\Lib\Events;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;

class CoIdEventListener Implements EventListenerInterface {
  // Current CO ID
  protected $coId;
  
  /**
   * Constructor.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int $coId  CO ID
   */
  
  public function __construct(?int $coId=null) {
    $this->coId = $coId;
  }
  
  /**
   * Before save event listener.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Event           $event   Cake Event
   * @param  EntityInterface $entity  Entity subject of the event (ie: object to be saved)
   * @param  ArrayObject     $options Save options
   */
  
  public function atInitialize(Event $event) {
    $table = $event->getSubject();

    if(method_exists($table, "acceptsCoId")
       && $table->acceptsCoId()) {
      $table->setCurCoId($this->coId);
    }
	}
  
  /**
   * Define the list of implemented events.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of implemented events and associated configuration.
   */
  
  public function implementedEvents(): array {
		return [
			'Model.initialize' => [
				'callable' => 'atInitialize'
			]
		];
	}

  /**
   * Update the CO ID. This call is intended for contests where records from multiple COs
   * are being processed within a single task.
   * 
   * @since  COmange Registry v5.1.0
   * @param  int $coId  CO ID
   */

  public function updateCoId(int $coId) {
    $this->coId = $coId;
  }
}
