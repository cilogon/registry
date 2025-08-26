<?php
/**
 * COmanage Registry Authenticator Statuses Controller
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

namespace App\Controller;

// XXX not doing anything with Log yet
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use App\Lib\Enum\AuthenticatorStatusEnum;

class AuthenticatorStatusesController extends StandardController {
  public $paginate = [
    'order' => [
      'AuthenticatorStatuses.id' => 'asc'
    ]
  ];

  /**
   * Generate an index of available Authenticator Statuses for the requested Person.
   * 
   * @since  COmanage Registry v5.2.0
   */

  public function index() {
    // Because we're not using the standard controller we need to set our own page title
    $this->set('vv_title', __d('controller', 'AuthenticatorStatuses', [99]));

    // We want to always provide one row for each configured Authenticator.

    $statuses = $this->AuthenticatorStatuses->getAllForPerson((int)$this->getRequest()->getQuery('person_id'));

    // We don't have a typical Result Set for an index view, so we just build a permission set
    // that will allow $rowActions to render. For that, we need to inject an entity ID. Note
    // some status entities will have an $id (ie: those that are locked), but for consistency
    // we'll overwrite those.

    $i = 0;
    $vv_permission_set = [];

    foreach($statuses as $status) {
      // Single-instance authenticators get view and reset options, if the Authenticator is set
      $singleActions = false;

      if($status->status == AuthenticatorStatusEnum::Active
         || $status->status == AuthenticatorStatusEnum::Expired) {
        // Query the Plugin to see if multiple instances are supported

        $Plugin = TableRegistry::getTableLocator()->get($status->plugin);

        $singleActions = !$Plugin->multiple;
      }

      $status->id = ++$i;
      $vv_permission_set[$i]['Authenticators'] = [
        'lock'   => true,
        'manage' => true,
        'reset'  => $singleActions,
        'unlock' => true,
        // 'view'   => $singleActions
      ];
    }

    $this->set('authenticator_statuses', $statuses);
    $this->set('vv_permission_set', $vv_permission_set);
   
    // Use the standard view
    $this->render('/Standard/index');
  }
}