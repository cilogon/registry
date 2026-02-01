<?php
/**
 * COmanage Registry Standard Detour Plugin Controller
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Controller;

// XXX not doing anything with Log yet
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\EnrollmentActorEnum;
use \App\Lib\Util\StringUtilities;

class StandardDetourController extends StandardPluginController {
  /**
   * Generate the redirect for the completion of a Detour.
   * 
   * @since  COmanage Registry v5.1.0
   * @return \Cake\Http\Response          Redirect to next step
   */

  protected function finishDetour(): \Cake\Http\Response {
    return $this->redirect([
      'plugin' => null,
      'controller' => 'traffic',
      'action' => 'process-login',
      '?' => ['done' => $this->request->getQuery("detour_id")]
    ]);
  }

  /**
   * Indicate whether this Controller will handle some or all authnz.
   *
   * @since  COmanage Registry v5.1.0
   * @param  EventInterface   $event  Cake event, ie: from beforeFilter
   * @return string                   "no", "open", "authz", or "yes"
   */

  public function willHandleAuth(\Cake\Event\EventInterface $event): string {
    if($this->request->getParam('action') == 'postlogin') {
      return 'yes';
    }

    return 'no';
  }
}