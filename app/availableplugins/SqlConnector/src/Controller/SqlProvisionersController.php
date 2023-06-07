<?php
/**
 * COmanage Registry SQL Provisioners Controller
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

namespace SqlConnector\Controller;

use App\Controller\StandardPluginController;

class SqlProvisionersController extends StandardPluginController {
  public $paginate = [
    'order' => [
      'SqlProvisioners.id' => 'asc'
    ]
  ];

  /**
   * Reapply the target database schema.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id SqlProvisioner ID
   */

  public function reapply(string $id) {
    try {
      $this->SqlProvisioners->applySchema((int)$id);
      $this->Flash->success(__d('sql_connector', 'result.reapply.ok'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->generateRedirect((int)$id);
  }

  /**
   * Reapply all Reference Data, including Groups.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id SqlProvisioner ID
   */

  public function resync(string $id) {
    try {
      $cur_co = $this->getCO();

      $this->SqlProvisioners->syncReferenceData(id: $id);

      $this->Flash->success(__d('sql_connector', 'result.resync.ok'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->generateRedirect((int)$id);
  }
}
