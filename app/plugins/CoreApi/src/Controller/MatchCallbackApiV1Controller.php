<?php
/**
 * COmanage Registry Match Callback API v1 Controller
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreApi\Controller;

use \Cake\ORM\TableRegistry;
use \App\Controller\StandardApiController;
use \App\Lib\Enum\SyncModeEnum;

class MatchCallbackApiV1Controller extends StandardApiController {
  // Map the actions to the Entry Point Model that controls the configuration.
  
  public $entryPointMap = [
    'resolveMatch' => 'MatchCallbacks'
  ];

  /**
   * Handle a Match Resolution Callback Notification
   *
   * @since  COmanage Registry v5.2.0
   */

  public function resolveMatch() {
    $payload = $this->request->getData();

    if(empty($payload['sor'])
      || empty($payload['sorid'])
      || empty($payload['referenceId'])) {
      $this->response = $this->response->withStatus(400);
      $this->set('vv_results', ['error' => __d('core_api', 'error.MatchCallbacks.json.invalid')]);
      return;
    }

    // Find the EIS associated with this sor label. There should be exactly one.

    $EISTable = TableRegistry::getTableLocator()->get('ExternalIdentitySources');

    $eis = $EISTable->find()
                    ->where([
                      'co_id'     => $this->request->getParam('coid'),
                      'sor_label' => $payload['sor'],
                      'status <>' => SyncModeEnum::Disabled
                    ])
                    ->first();

    // We check for the EIS rather than let find throw an exception so we can return
    // a 400 (client error) instead of 500 (server error).

    if(empty($eis)) {
      $this->response = $this->response->withStatus(400);
      $this->set('vv_results', ['error' => __d('core_api', 'error.MatchCallbacks.sor.notfound')]);
      return;
    }

    // We register a Job rather than process the record directly for a few of reasons.
    // (1) A record could take "too long" to process (usually due to slow provisioning),
    //     resulting in a web server timeout for the request from Match.
    // (2) There's fairly complicated logic in SyncJob to process the record, and there's
    //     not a compelling reason to (partially) duplicate that here.
    // (3) By processing via the Job infrastructure, the artificats from processing via
    //     this callback will be available alongside where the rest of the artifacts are.
    // The primary downside is that processing isn't immediate, but this can be managed
    // by setting the queue runner to run at a reasonable frequency.

    // If an EIS sync fails because of multiple choices returned by the Match Server,
    // there will be an EIS Record, but no indication of the multiple choice status is
    // retained (and no External Identity has yet been created). This implies we don't
    // have a reliable way to validate the SORID (source key) provided in this request
    // but the Job can do that. (We could in theory record the Match Reference ID and
    // link on that, but SOR Label + Source Key is good enough.) We also don't try to
    // map the Reference ID here, since that could theoretically change before the Job
    // actually runs (but probably it won't).

    try {
      $JobTable = TableRegistry::getTableLocator()->get("Jobs");

      $JobTable->register(
        coId:             (int)$this->request->getParam('coid'),
        plugin:           'CoreJob.SyncJob',
        parameters:       [
          'external_identity_source_id' => $eis->id,
          'source_keys'                 => $payload['sorid'],
          'reference_id'                => $payload['referenceId']
        ],
        registerSummary:  __d('core_api', 'error.MatchCallbacks.match.resolved')
      );

      $this->response = $this->response->withStatus(202);
    }
    catch(\Exception $e) {
      // We catch and rethrow any errors to make sure the formatting is compatible with
      // the API
      $this->response = $this->response->withStatus(500);
      $this->set('vv_results', ['error' => $e->getMessage()]);
      return;
    }

    // Note there's nothing to attach a history record to yet, so we don't
  }
}
