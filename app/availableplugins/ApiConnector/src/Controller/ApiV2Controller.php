<?php
/**
 * COmanage Registry Api Sources API v2 Controller
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
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace ApiConnector\Controller;

use \Cake\ORM\TableRegistry;
use \App\Controller\StandardApiController;

class ApiV2Controller extends StandardApiController {

  /**
   * Calculate the CO ID associated with the request.
   * 
   * @since  COmanage Registry v5.0.0
   * @return int      CO ID, or null if no CO contextwas found
   */

  public function calculateRequestedCOID(): ?int {
    $apiSourceId = $this->request->getParam('id');

    $ApiSource = TableRegistry::getTableLocator()->get('ApiConnector.ApiSources');

    $cfg = $ApiSource->get($apiSourceId, ['contain' => 'ExternalIdentitySources']);

    return $cfg->external_identity_source->co_id ?? null;
  }

  /**
   * Calculate authorization for the current request.
   * 
   * @since  COmanage Registry v5.0.0
   * @return bool     True if the current request is permitted, false otherwise
   */

  public function calculatePermission(): bool {
    $request = $this->getRequest();
    $action = $request->getParam('action');
    $authUser = $this->RegistryAuth->getAuthenticatedUser();

    $authorized = false;

    // Our authorization is pretty straightforward, the configured API User
    // is permitted to perform all actions.

    // This should be set or the route won't match
    $apiSourceId = $this->request->getParam('id');

    $ApiSource = TableRegistry::getTableLocator()->get('ApiConnector.ApiSources');

    $cfg = $ApiSource->get($apiSourceId, ['contain' => 'ApiUsers']);

    if(!empty($cfg->api_user->username) 
       && !empty($authUser)
       && $authUser == $cfg->api_user->username) {
      $authorized = true;
    }

    return $authorized;
  }

  /**
   * Handle an SOR Person Role Deleted request.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id       ApiSource ID
   * @param  string $sorlabel System of Record Label from request URL
   * @param  string $sorid    System of Record ID from request URL
   */

  public function delete(string $id, string $sorlabel, string $sorid) {
    $ApiSource = TableRegistry::getTableLocator()->get('ApiConnector.ApiSources');

    $resultCode = 500;
    $results = [];

    try {
      $ApiSource->remove((int)$id, $sorlabel, $sorid);

      $resultCode = 200;
    }
    catch(\Cake\Datasource\Exception\RecordNotFoundException $e) {
      $resultCode = 404;
      $results['error'] = $e->getMessage();
    }
    catch(\Exception $e) {
      $this->llog('debug', $e->getMessage());
      $results['error'] = $e->getMessage();

      $resultCode = 500;
    }

    $this->response = $this->response->withStatus($resultCode);
    $this->set('vv_results', $results);
  }

  /**
   * Handle a Get SOR Person Role request.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id       ApiSource ID
   * @param  string $sorlabel System of Record Label from request URL
   * @param  string $sorid    System of Record ID from request URL
   */

  public function get(string $id, string $sorlabel, string $sorid) {
    // We basically just pull the currently cached source record and return it.

    $ApiSourceRecord = TableRegistry::getTableLocator()->get('ApiConnector.ApiSourceRecords');

    $results = [];
    $resultCode = 500;

    try {
      $record = $ApiSourceRecord->find()
                                ->where(['api_source_id' => $id, 'source_key' => $sorid])
                                ->firstOrFail();
      
      $resultCode = 200;
      $results = json_decode($record->source_record);
    }
    catch(\Cake\Datasource\Exception\RecordNotFoundException $e) {
      $resultCode = 404;
      $results['error'] = $e->getMessage();
    }
    catch(\Exception $e) {
      $results['error'] = $e->getMessage();
    }

    $this->response = $this->response->withStatus($resultCode);
    $this->set('vv_results', $results);
  }

  /**
   * Handle an SOR Person Role Added or Updated request.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id       ApiSource ID
   * @param  string $sorlabel System of Record Label from request URL
   * @param  string $sorid    System of Record ID from request URL
   */

  public function upsert(string $id, string $sorlabel, string $sorid) {
    // Pass the requested data to the Backend and return a response.
// XXX todo: add support for returnUrl back in

    $ApiSource = TableRegistry::getTableLocator()->get('ApiConnector.ApiSources');

    $resultCode = 400;
    $results = [];

    try {
      $result = $ApiSource->upsert((int)$id, $sorlabel, $sorid, $this->request->getData());

      if(isset($result['new']) && $result['new']) {
        $resultCode = 201;
      } else {
        $resultCode = 200;
      }
    }
    catch(\Exception $e) {
      $this->llog('debug', $e->getMessage());
      $results['error'] = $e->getMessage();

      $resultCode = 400;
    }

    $this->response = $this->response->withStatus($resultCode);
    $this->set('vv_results', $results);
  }

  /**
   * Indicate whether this Controller will handle some or all authnz.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface   $event  Cake event, ie: from beforeFilter
   * @return string                   "no", "open", "authz", or "yes"
   */

  public function willHandleAuth(\Cake\Event\EventInterface $event): string {
    // We always take over authz
    return 'authz';
  }
}
