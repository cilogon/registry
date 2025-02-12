<?php
/**
 * COmanage Registry Actor Event Listener
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
use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use App\Controller\Component\RegistryAuthComponent;

class ActorEventListener Implements EventListenerInterface {
  // RegistryAuth Component
  protected $RegistryAuth;
  
  /**
   * Constructor.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RegistryAuthComponent $Auth RegistryAuthComponent
   */
  
  public function __construct(RegistryAuthComponent $Auth) {
    $this->RegistryAuth = $Auth;
  }
  
  /**
   * Before marshal event listener.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  EventInterface $event    Event
   * @param  ArrayObject    $data     Object data, in array format
   * @param  ArrayObject    $options  Entity save options
   */

  public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options) {
    // Tweak the options so HistoryRecords and similar models can see who performed the save

    if($this->RegistryAuth) {
      $options['actor'] = $this->RegistryAuth->getAuthenticatedUser();
      $options['apiuser'] = $this->RegistryAuth->isApiUser();

      // We don't autocalculate the actor_person_id here because most models won't need it
    }
  }

  /**
   * Before save event listener.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Event           $event   Cake Event
   * @param  EntityInterface $entity  Entity subject of the event (ie: object to be saved)
   * @param  ArrayObject     $options Save options
   */
  
  public function beforeSave(Event $event, EntityInterface $entity, ArrayObject $options) {
    // Tweak the options so ChangelogBehavior can see who performed the save
    
    if($this->RegistryAuth) {
      $options['actor'] = $this->RegistryAuth->getAuthenticatedUser();
      $options['apiuser'] = $this->RegistryAuth->isApiUser();
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
			'Model.beforeMarshal' => [
				'callable' => 'beforeMarshal',
        // We need this beforeMarshal to run before the *HistoryRecords beforeMarshal
				'priority' => -100
			],
			'Model.beforeSave' => [
				'callable' => 'beforeSave',
        // We need this beforeSave to run before the ChangelogBehavior beforeSave
				'priority' => -100
			]
		];
	}
}
