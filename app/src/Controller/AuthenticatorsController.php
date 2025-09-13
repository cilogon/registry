<?php
/**
 * COmanage Registry Authenticators Controller
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
use Cake\Utility\Inflector;
use App\Lib\Enum\AuthenticatorStatusEnum;
use App\Lib\Util\StringUtilities;

class AuthenticatorsController extends StandardPluggableController {
  protected array $paginate = [
    'order' => [
      'Authenticators.description' => 'asc'
    ]
  ];

  /**
   * Lock an Authenticator.
   * 
   * @since  COmanage Registry v5.2.0
   */

  public function lock() {
    // We'll receive a URL with three query params: authenticator_id, authenticator_status_id,
    // and person_id. authenticator_status_id is just an artifact of how the standard index view
    // constructs $rowActions, and we ignore it completely. We pass the other parameters to the
    // model.

    try {
      // Perform the lock
      $this->Authenticators->lock(
        (int)$this->getRequest()->getQuery('authenticator_id'),
        (int)$this->getRequest()->getQuery('person_id')
      );

      $this->Flash->success(__d('result', 'Authenticators.locked'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->redirect([
      'controller' => 'authenticator_statuses',
      'action' => 'index',
      '?' => [
        'person_id' => $this->getRequest()->getQuery('person_id')
      ]
    ]);
  }

  /**
   * Manage an Authenticator.
   * 
   * @since  COmanage Registry v5.2.0
   */

  public function manage() {
    // Issue a redirect from authenticator_statuses/index into the appropriate Plugin.
    // This function is not intended to support more complex behavior. If it becomes necessary
    // to add additional behavior, be sure to review the permissions settings and primary link
    // handling in AuthenticatorsTable.

    // We'll receive a URL with three query params: authenticator_id, authenticator_status_id,
    // and person_id. authenticator_status_id is just an artifact of how the standard index view
    // constructs $rowActions, and we ignore it completely. We lookup authenticator_id and map
    // it to a Plugin configuration, and redirect there without further validation.

    $cfg = $this->Authenticators->get(
            (int)$this->getRequest()->getQuery('authenticator_id'),
            contain: $this->Authenticators->getPluginRelations()
           );

    // We need to know what type of Authenticator we're managing to construct the redirect.
    // This implies each Plugin can only manage one type of Authenticator (ie: we can't have a
    // "CoreAuthenticator" that implements SshKeys _and_ Passwords because we won't know which
    // one to redirect to). While we could have the plugin declare the Authenticator type, it's
    // simpler and clearer to require the plugin to use the name of the token in the plugin
    // name (ie: "SshKeyAuthenticator" and not "CoreAuthenticator"). Note this applies only to
    // the plugin _model_, not the plugin _name_, so a fully qualified plugin name of (eg)
    // "CoreAuthenticator.PasswordAuthenticators" is permitted.

    // eg: SshKeyAuthenticator, though this doesn't need to follow the pattern
    $pluginName = StringUtilities::pluginPlugin($cfg->plugin);
    // eg: SshKeyAuthenticators
    $pluginModel = StringUtilities::pluginModel($cfg->plugin);
    // eg: SshKey
    $authenticatorType = $this->Authenticators->authenticatorEntityName($pluginModel);
    // eg: ssh_key_authenticator_id
    $pluginfk = StringUtilities::classNameToForeignKey($pluginModel);
    // eg: ssh_key_authenticator
    $pluginfield = StringUtilities::pluginToEntityField($cfg->plugin);

    // The redirect depends on whether the Plugin supports multiple instantiation or not.
    $Plugin = TableRegistry::getTableLocator()->get($cfg->plugin);

    if($Plugin->multiple) {
      // For multiple instance, we redirect to the Plugin index view

      return $this->redirect([
        'plugin' => $pluginName,
        'controller' => Inflector::pluralize($authenticatorType),
        'action' => 'index',
        '?' => [
          $pluginfk => $cfg->$pluginfield->id,
          'person_id' => $this->getRequest()->getQuery('person_id')
        ]
      ]);
    } else {
      // Redirect to the manage view for the Plugin. In most cases, the Plugin will extend
      // SingleAuthenticatorController, which will implement manage(). Plugins with more
      // complex requirements can directly implement manage().

      return $this->redirect([
        'plugin' => $pluginName,
        'controller' => Inflector::pluralize($authenticatorType),
        'action' => 'manage',
        '?' => [
          $pluginfk => $cfg->$pluginfield->id,
          'person_id' => $this->getRequest()->getQuery('person_id')
        ]
      ]);
    }
  }

  /**
   * Reset an Authenticator.
   * 
   * @since  COmanage Registry v5.2.0
   */

  public function reset() {
    // We'll receive a URL with three query params: authenticator_id, authenticator_status_id,
    // and person_id. authenticator_status_id is just an artifact of how the standard index view
    // constructs $rowActions, and we ignore it completely. We pass the other parameters to the
    // model.

    try {
      // Perform the reset
      $this->Authenticators->reset(
        (int)$this->getRequest()->getQuery('authenticator_id'),
        (int)$this->getRequest()->getQuery('person_id')
      );

      $this->Flash->success(__d('result', 'Authenticators.reset'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->redirect([
      'controller' => 'authenticator_statuses',
      'action' => 'index',
      '?' => [
        'person_id' => $this->getRequest()->getQuery('person_id')
      ]
    ]);
  }

  /**
   * Unlock an Authenticator.
   * 
   * @since  COmanage Registry v5.2.0
   */

  public function unlock() {
    // We'll receive a URL with three query params: authenticator_id, authenticator_status_id,
    // and person_id. authenticator_status_id is just an artifact of how the standard index view
    // constructs $rowActions, and we ignore it completely. We pass the other parameters to the
    // model.

    try {
      // Perform the lock
      $this->Authenticators->unlock(
        (int)$this->getRequest()->getQuery('authenticator_id'),
        (int)$this->getRequest()->getQuery('person_id')
      );

      $this->Flash->success(__d('result', 'Authenticators.unlocked'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->redirect([
      'controller' => 'authenticator_statuses',
      'action' => 'index',
      '?' => [
        'person_id' => $this->getRequest()->getQuery('person_id')
      ]
    ]);
  }
}