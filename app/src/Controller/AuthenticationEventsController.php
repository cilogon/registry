<?php
/**
 * COmanage Registry Authentication Events Controller
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

namespace App\Controller;

// XXX not doing anything with Log yet
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

class AuthenticationEventsController extends MVEAController {
  public $paginate = [
    'order' => [
      'AuthenticationEvents.id' => 'desc'
    ]
  ];
  
  // Cached permissions
  protected ?array $permCache = null;
  
  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */
  
  public function beforeFilter(\Cake\Event\EventInterface $event) {
    // If an identifier was passed in, use that to filter the index query.
    // (Authz is handled in the closure passed to setIndexFilter by AuthenticationEventsTable.)
    $targetIdentifier = $this->getRequest()->getQuery('authenticated_identifier');
    
    if($targetIdentifier) {
      $this->AuthenticationEvents->setIndexFilter(['authenticated_identifier' => \App\Lib\Util\StringUtilities::urlbase64decode($targetIdentifier)]);
    }
    
    return parent::beforeFilter($event);
  }
}