<?php
/**
 * COmanage Registry Standard Multiple Authenticator Controller
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

// This isn't "StandardMultipleAuthenticatorController" to avoid name length issues
class MultipleAuthenticatorController extends StandardPluginController {
  // Cached info for redirect after delete
  private $redirectInfo = [];
  
  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.2.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */

  public function beforeFilter(\Cake\Event\EventInterface $event) {
    // $modelsName = Models (eg: SshKeys)
    $modelsName = $this->name;
    // $authModelName = eg SshKeyAuthenticators
    $authModelsName = Inflector::singularize($modelsName) . "Authenticators";
    // $table = the actual table object
    $Table = $this->$modelsName;
    // $authFK = eg ssh_key_authenticator_id
    $authFK = StringUtilities::classNameToForeignKey($authModelsName);

    // We need to cache our plugin ID and the person ID to be able to issue redirects,
    // in particular for delete views. For consistency, we'll do this for all views.

    $id = $this->request->getParam('pass.0');

    if(!empty($id)) {
      $obj = $Table->get($id);

      $this->redirectInfo[$authFK] = $obj->$authFK;
      $this->redirectInfo['person_id'] = $obj->person_id;
    } else {
      $this->redirectInfo[$authFK] = $this->requestParam($authFK);
      $this->redirectInfo['person_id'] = $this->requestParam('person_id');
    }

    return parent::beforeFilter($event);
  }

  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.2.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */

  public function beforeRender(\Cake\Event\EventInterface $event) {
    $this->set('vv_person_id', $this->requestParam('person_id'));

    return parent::beforeRender($event);
  }

  /**
   * Generate a redirect for an SSH Key operation.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Entity $entity   Entity to redirect to
   * @return \Cake\Http\Response
   */
  
  public function generateRedirect($entity) {
    // $modelsName = Models (eg: SshKeys)
    $modelsName = $this->name;
    // $authModelName = eg SshKeyAuthenticators
    $authModelsName = Inflector::singularize($modelsName) . "Authenticators";
    // $table = the actual table object
    $Table = $this->$modelsName;
    // $authFK = eg ssh_key_authenticator_id
    $authFK = StringUtilities::classNameToForeignKey($authModelsName);

    // We override the default behavior because we need to construct an index URL
    // with multiple parameters. We'll ignore $entity because it isn't always provided
    // (eg: on delete) and beforeFilter has cached what we need for all actions.

    return $this->redirect([
      // We have to rely on Cake auto-injecting the plugin based on the current plugin
      // because we can't directly determine the plugin name without figuring out our
      // configuration (via widget_authenticator_id) and looking up the fully qualified
      // plugin name.
      // 'plugin' => 'SshKeyAuthenticator',
      'controller' => Inflector::dasherize($modelsName),
      'action' => 'index',
      '?' => [
        $authFK => $this->redirectInfo[$authFK],
        'person_id' => $this->redirectInfo['person_id']
      ]
    ]);
  }

  /**
   * Generate an index for a set of Authenticator Objects.
   *
   * @since  COmanage Registry v5.2.0
   */

  public function index() {
    // $this->name = Models
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;
    // $tableName = models
    $tableName = $table->getTable();
    // Construct the Query
    $query = $this->getIndexQuery();

    // We need to filter on both the primary key (widget_authenticator_id)
    // _and_ person_id. In v4 we would do that via paginationConditions, but we don't
    // appear to have an equivalent for v5 yet. So for now we just override index.

    $personId = $this->requestParam('person_id');

    if($personId) {
      $query = $query->where(['person_id' => $personId]);
    }

    // Fetch the data and paginate
    $paginationLimit = $this->getValue(\App\Lib\Enum\ApplicationStateEnum::PaginationLimit, DEF_SEARCH_LIMIT);
    $resultSet = $this->paginate($query, [
      'limit' => (int)$paginationLimit
    ]);

    // Pass vars to the View
    $this->set($tableName, $resultSet);
    $this->set('vv_permission_set', $this->RegistryAuth->calculatePermissionsForResultSet($resultSet));
    // AutoViewVarsTrait
    $this->populateAutoViewVars();

    // Default index view title is model name
    [$title, , ] = StringUtilities::entityAndActionToTitle($resultSet, $modelsName, 'index');
    $this->set('vv_title', $title);
    
    // Let the view render
    $this->render('/Standard/index');
  }

  /**
   * Indicate whether this Controller will handle some or all authnz.
   *
   * @since  COmanage Registry v5.2.0
   * @param  EventInterface   $event  Cake event, ie: from beforeFilter
   * @return string                   "no", "notauth", "open", "authz", or "yes"
   */

  public function willHandleAuth(\Cake\Event\EventInterface $event): string {
    // $modelsName = Models (eg: SshKeys)
    $modelsName = $this->name;
    // $authModelName = eg SshKeyAuthenticators
    $authModelsName = Inflector::singularize($modelsName) . "Authenticators";
    // $table = the actual table object
    $Table = $this->$modelsName;
    // $authFK = eg ssh_key_authenticator_id
    $authFK = StringUtilities::classNameToForeignKey($authModelsName);
    
    $request = $this->getRequest();
    $action = $request->getParam('action');
    $id = (int)$this->request->getParam('pass.0');

    // If the current Authenticator is locked, we need to reject all requests.
    // We need to perform this test from within the Plugin for the actions the
    // Plugin handles, such as index and add.

    $pluginAuthenticatorId = null;
    $personId = null;

    if(in_array($action, ['add', 'index', 'manage'])) {
      // Get the parameters from the request

      $pluginAuthenticatorId = $this->requestParam($authFK);
      $personId = $this->requestParam('person_id');
    } elseif(!empty($this->request->getParam('pass.0'))) {
      // Lookup the parameters from the record ID

      $obj = $Table->get($this->request->getParam('pass.0'));

      $pluginAuthenticatorId = $obj->$authFK;
      $personId = $obj->person_id;
    }

    if(!$pluginAuthenticatorId) {
      throw new \InvalidArgumentException(__d('error', 'notprov', [$authFK]));
    }

    if(!$personId) {
      throw new \InvalidArgumentException(__d('error', 'notprov', ['person_id']));
    }

    $cfg = $Table->$authModelsName->get($pluginAuthenticatorId);

    $AuthenticatorStatuses = TableRegistry::getTableLocator()->get('AuthenticatorStatuses');

    $status = $AuthenticatorStatuses->find()
                ->where([
                  'authenticator_id' => $cfg->authenticator_id,
                  'person_id'        => $personId
                ])
                ->first();
    
    if(!empty($status) && $status->locked) {
      return 'notauth';
    }

    return 'no';
  }  
}