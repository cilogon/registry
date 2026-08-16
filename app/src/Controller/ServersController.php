<?php
/**
 * COmanage Registry Servers Controller
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

class ServersController extends StandardPluggableController {
  use \App\Lib\Traits\LabeledLogTrait;

  protected array $paginate = [
    'order' => [
      'Servers.description' => 'asc'
    ]
  ];

  /**
   * Test the connection to a configured server by attempting to establish a connection
   * using the server's plugin-specific connection method.
   *
   * @param string $id Server identifier
   * @return \Cake\Http\Response|null Redirects to the index view with the connection test result
   * @throws \Cake\Datasource\Exception\RecordNotFoundException If server not found
   * @since COmanage Registry v5.3.0
   */
  public function test(string $id): ?\Cake\Http\Response
  {
    /** var Cake\ORM\Table $table */
    $table = $this->getCurrentTable();

    $serverId = $this->request->getParam('pass')[0];
    $serverObj = $table->findById($serverId)
      ->firstOrFail();

    $pluginTable = $this->getTableLocator()->get($serverObj->plugin);

    try {
      if (method_exists($pluginTable, 'checkConnectivity')) {
        // Set the Connection Manager config
        $connection = $pluginTable->checkConnectivity((int)$serverId, 'server' . $serverId);

        if ($connection) {
          $this->Flash->success(__d('result', 'test.ok'));
          $this->llog('debug', "Successfully connected to server " . $serverId);
        } else {
          $this->Flash->error(__d('error', 'test.connection'));
          $this->llog('error', "Failed to connect to server " . $serverId . ": Connection failed");
        }
      } else {
        $this->Flash->information(__d('result', 'test.na'));
      }
    } catch (\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->generateRedirect($serverObj);
  }

  /**
   * Calculate the redirect for this request.
   *
   * @since  COmanage Registry v5.3.0
   * @param  Entity $entity   Subject entity
   * @return array|string            Redirect
   */
  public function calculateRedirectTarget($entity): array|string
  {
    $fallbackRedirectToIndexPage = [
      'controller' => 'Servers',
      'action' => 'index',
      '?' => [
        'co_id' => $entity?->co_id
      ]
    ];

    // Redirect to the referring page, with a fallback to the Servers index
    return $this->referer($fallbackRedirectToIndexPage);
  }
}
