<?php
/**
 * COmanage Registry Env Source Detours Controller
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
 * @package       registry-plugins
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace EnvSource\Controller;

use Cake\Event\EventManager;
use Cake\ORM\TableRegistry;
use \App\Controller\StandardDetourController;
use \App\Lib\Events\CoIdEventListener;

class EnvSourceDetoursController extends StandardDetourController {
  protected array $paginate = [
    'order' => [
      'EnvSourceDetours.id' => 'asc'
    ]
  ];

  /**
   * Handle a post login action.
   * 
   * @since  COmanage Registry v5.1.0
   */

  public function postlogin() {
    $request = $this->getRequest();
    $session = $request->getSession();

    $detourId = $request->getQuery("detour_id");

    // We need to register CoIdEventListener and pass it to refresh since
    // certain models (Addresses, etc) require CO ID context for validation
    // (and we don't require a CO for Detours).
    $CoIdEventListener = new CoIdEventListener();
    EventManager::instance()->on($CoIdEventListener);

    $this->EnvSourceDetours->refresh(
      detourId: (int)$detourId,
      sourceKey: $session->read('Auth.external.user'),
      coidListener: $CoIdEventListener
    );

    return $this->finishDetour();
  }
}
