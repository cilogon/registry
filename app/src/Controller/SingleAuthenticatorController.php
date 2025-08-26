<?php
/**
 * COmanage Registry Standard Single Authenticator Controller
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

use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use App\Lib\Util\StringUtilities;

// This isn't "StandardSingleAuthenticatorController" to avoid name length issues
class SingleAuthenticatorController extends StandardPluginController {
  /**
   * Manage an Authenticator.
   * 
   * @since  COmanage Registry v5.2.0
   */

  public function manage() {
    // $modelsName = Models (eg: Passwords)
    $modelsName = $this->name;
    // $authModelName = eg PasswordAuthenticators
    $authModelsName = Inflector::singularize($modelsName) . "Authenticators";
    // $table = the actual table object
    $Table = $this->$modelsName;
    // $authFK = eg password_authenticator_id
    $authFK = StringUtilities::classNameToForeignKey($authModelsName);

    // We will be passed person_id and foo_authenticator_id. AR-GMR-2 should ensure
    // they're in the same CO when we try to save an entity that references both.
    
    // Pull the current Authenticator status to pass to the view. For this, we need
    // the Authenticator configuration. We set the Authenticator as the top level object
    // for consistency with the other interfaces, and also because getForPerson requires
    // the same interface. However, in order to do that we need the Authenticator ID,
    // so we'll need an extra sort-of redundant query.

    $cfg = $Table->$authModelsName
                 ->get($this->getRequest()->getQuery($authFK));

    // getForPerson expects the Authenticator with the PasswordAuthenticator configuration
    // under it, so we need to flip $cfg. We do this by retrieving it a second time from
    // the database because if we try to manually manipulate the order we'll get into
    // weird loops and dereferencing issues in various contexts.

    $Authenticators = TableRegistry::getTableLocator()->get('Authenticators');

    $authcfg = $Authenticators->get($cfg->authenticator_id, ['contain' => $authModelsName]);

    $status = $Authenticators->AuthenticatorStatuses->getForPerson(
      $authcfg,
      (int)$this->getRequest()->getQuery('person_id')
    );

    if($this->request->is('post')) {
      try {
        $Table->manage($authcfg, $status->person_id, $this->request->getData());

        // Plugins are expected to record history. We'll handle provisioning here.

        $Table->People->requestProvisioning($status->person_id, ProvisioningContextEnum::Automatic);

        // Redirect to the main authenticator index for this Person
        return $this->redirect([
          'plugin' => null,
          'controller' => 'AuthenticatorStatuses',
          'action' => 'index',
          '?' => [
            'person_id' => $status->person_id
          ]
        ]);
      }
      catch(\Exception $e) {
        $this->Flash->error($e->getMessage());
      }
    }

    $this->set('vv_authenticator', $authcfg);
    $this->set('vv_status', $status);

    // Pull the Person name for use in the page title
    $Names = TableRegistry::getTableLocator()->get('Names');

    $name = $Names->primaryName($status->person_id);

    $this->set('vv_title', __d('password_authenticator', 'operation.set', [$name->full_name]));

    // Let the view render
    $this->render('/Standard/manage');
  }
}