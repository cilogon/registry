<?php
/**
 * COmanage Registry Standard API Controller
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

use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use App\Lib\Enum\SuspendableStatusEnum;

class StandardApiController extends AppController {
  /**
   * Perform Cake Controller initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
    
  public function initialize(): void {
    parent::initialize();
    
    // We want API auth, not Web Auth
    $this->RegistryAuth->setConfig('apiUser', true);
  }

  /**
   * Calculate authorization for the current request.
   * 
   * @since  COmanage Registry v5.2.0
   * @return bool     True if the current request is permitted, false otherwise
   */

  public function calculatePermission(): bool {
    $request = $this->getRequest();
    $action = $request->getParam('action');
    $authuser = null;

    // We let RegistryAuth handle the authentication
    if(!$this->RegistryAuth->isApiUser()) {
      // We don't localize exception in this function or call llog directly because
      // any exception we throw will be caught by RegistryAuthComponent and logged there
      throw new \InvalidArgumentException("RegistryAuth did not provide API User in calculatePermission");
    }
    
    $authUser = $this->RegistryAuth->getAuthenticatedUser();

    $authorized = false;

    // For authorization, we need to find the corresponding entry in Apis.
    // First we need the API User ID.

    $ApiUsers = TableRegistry::getTableLocator()->get('ApiUsers');

    // RegistryAuthComponent took care of all the validation, we just need the ID
    $apiuser = $ApiUsers->find()
                        ->where(['username' => $authUser])
                        ->firstOrFail();

    // Next we need the Plugin's Entry Point Map to tell us what Entry Point Model the
    // current controller points to.

    if(!isset($this->entryPointMap[$action])) {
      throw new \RuntimeException("Plugin did not provide Entry Point Map value for $action");
    }

    // Find the associated API configuration

    $api = $ApiUsers->Apis->find()
                          ->where([
                            'api_user_id' => $apiuser->id,
                            'plugin'      => $this->getPlugin() . "." . $this->entryPointMap[$action]
                          ])
                          ->firstOrFail();

    // We manually check status (as opposed to updating the find) to faciliate logging

    if($api->status != SuspendableStatusEnum::Active) {
      throw new \InvalidArgumentException("API " . $api->id . " is not active");
    }
    
    // If we get here the API User is authorized for the requested plugin configuration

    return true;
  }

  /**
   * Indicate whether this Controller will handle some or all authnz.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  EventInterface   $event  Cake event, ie: from beforeFilter
   * @return string                   "no", "open", "authz", or "yes"
   */

  public function willHandleAuth(\Cake\Event\EventInterface $event): string {
    // We always take over authz
    return 'authz';
  }
}