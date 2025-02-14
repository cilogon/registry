<?php
/**
 * COmanage Registry Traffic Controller
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

use Cake\Controller\Controller;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\AuthenticationEventEnum;
use \App\Lib\Enum\SuspendableStatusEnum;
use \App\Lib\Util\StringUtilities;

// XXX not doing anything with Log yet
use \Cake\Log\Log;

class TrafficController extends Controller {
  /**
   * Initiate a login transaction.
   * 
   * @since  COmanage Registry v5.1.0
   */

  public function initiateLogin() {
// Update https://spaces.at.internet2.edu/display/COmanage/Registry+PE+Installation+-+Source#RegistryPEInstallationSource-IntegrateWebServerAuthentication
// when plugin support is added here

/*
We only need to pass through here once. Either we find a detour that handles login context
and redirect to it, or we don't and we redirect to the standard login handler. Any plugin
that handles login should

 (1) Set $_SESSION['Auth']['external']['user'] to the authenticated identifier
 (2) Redirect to /registry/traffic/process-login

(This is the same work done in webroot/auth/login/login.php, perhaps we should provide
a utility to facilitate)

    $TrafficDetours = TableRegistry::getTableLocator()->get('TrafficDetours');

    $detour = $TrafficDetours->calculateNextDetour(context: 'login');

    if(!empty($detour)) {
      // Redirect into the detour. We only support one plugin handling the login context,
      // and expect that plugin will redirect to process-login.
    }
*/

    // If we get here, use the default webserver login handler
    return $this->redirect("/auth/login/login.php");
  }
  
  /**
   * Prepare for a login action.
   * 
   * @since  COmanage Registry v5.1.0
   */

  public function prepareLogin() {
    // XXX Add support for calling plugins in "prelogin" context

    return $this->redirect("/traffic/initiate-login");
  }

  /**
   * Process a login action on return from auth/login/login.php.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function processLogin() {
    $request = $this->getRequest();
    $session = $request->getSession();

    $lastDetourId = (int)$this->request->getQuery("done");

    // The next detour to run, once determined
    $detour = null;

    $TrafficDetours = TableRegistry::getTableLocator()->get('TrafficDetours');
    
    if(!empty($lastDetourId)) {
      // Figure out the next Traffic Detour to run

      // What's the next detour?
      $detour = $TrafficDetours->calculateNextDetour(context: 'postlogin', lastDetourId: $lastDetourId);
    } else {
      // This is our first time through, process the login and then redirect appropriately
      
      $username = $session->read('Auth.external.user');
      
      if(!$username) {
        throw new \InvalidArgumentException('Auth.external.user not found in TrafficController');
      }
      
      // Record the login event
      $AuthenticationEvents = TableRegistry::getTableLocator()->get('AuthenticationEvents');
      
      $AuthenticationEvents->record(identifier: $username,
                                    eventType: AuthenticationEventEnum::RegistryLogin,
                                    remoteIp: $_SERVER['REMOTE_ADDR']);

      // What's the next detour?
      $detour = $TrafficDetours->calculateNextDetour(context: 'postlogin');
    }

    if($detour) {
      // Redirect into this detour

      return $this->redirect([
        'plugin' => StringUtilities::PluginPlugin($detour->plugin),
        'controller' => StringUtilities::PluginModel($detour->plugin),
        'action' => 'postlogin',
        '?' => ['detour_id' => $detour->id]
      ]);
    }

    // We're done with postlogin handling, redirect to the original target the user
    // was trying to get to
    $target = $session->read('Auth.target');
    
    if(!$target) {
      throw new \InvalidArgumentException('Auth.target not found in TrafficController');
    }
    
    return $this->redirect($target);
  }
}