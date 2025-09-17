<?php
/**
 * COmanage Registry Breadcrumbs Trait
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

namespace App\Lib\Traits;

use App\Lib\Util\StringUtilities;

trait BreadcrumbsTrait {
  /**
   * Builds breadcrumbs from a `*_server_id` query parameter.
   *
   * Supported keys include (but are not limited to):
   * - match_server_id  → CoreServer.MatchServers
   * - http_server_id   → CoreServer.HttpServers
   * - sql_server_id    → CoreServer.SqlServers
   * - smtp_server_id   → CoreServer.SmtpServers
   * - oauth2_server_id → CoreServer.Oauth2Servers
   *
   * @return array<string,array{label:string,target:array}> Breadcrumb parents to prepend
   */
  protected function buildServerParamBreadcrumbs(): array
  {
    $vv_bc_parents = (array)$this->viewBuilder()->getVar('vv_bc_parents');

    // 1) Find the first *_server_id query param and capture its key & value
    $q = (array)$this->getRequest()->getQueryParams();
    $serverFkKey = null;   // eg: match_server_id
    $serverFkVal = 0;      // eg: 42

    foreach ($q as $k => $v) {
      if (is_string($k) && preg_match('/^[a-z0-9_]+_server_id$/', $k)) {
        $serverFkKey = $k;
        $serverFkVal = (int)$v;
        break;
      }
    }

    if (!$serverFkKey || $serverFkVal <= 0) {
      // No *_server_id param present
      return [];
    }

    // 2) Infer the child table class from the foreign key, eg: match_server_id → MatchServers
    //    We’ll attempt CoreServer.PluginTable first, then fall back to the bare alias.
    $modelsName = StringUtilities::foreignKeyToClassName($serverFkKey); // eg: MatchServers
    // Get the child table class
    $ChildTable = $this->getTableLocator()->get('CoreServer.' . $modelsName);

    // 3) Fetch the child entity (eg: MatchServers/SqlServers/HttpServers/etc) to obtain server_id
    $child = null;
    $serverId = 0;
    try {
      $child    = $ChildTable->get($serverFkVal);
      $serverId = (int)($child->server_id ?? 0);
    } catch (\Throwable $e) {
      // Fail-soft: no child → no crumbs
      return [];
    }

    $parents = [];

    // 4) Servers index (for this CO)
    $coId = method_exists($this, 'getCOID') ? (int)$this->getCOID() : 0;
    $serversIndexKey = 'servers:' . $coId;
    if ($coId > 0 && empty($vv_bc_parents[$serversIndexKey])) {
      $parents[$serversIndexKey] = [
        'label'  => StringUtilities::localizeController('Servers', null, true),
        'target' => [
          'plugin'     => null,
          'controller' => 'Servers',
          'action'     => 'index',
          '?'          => ['co_id' => $coId],
        ],
      ];
    }

    // 5) Current Server → Servers/edit/{server_id}
    if ($serverId > 0) {
      try {
        $Servers   = $this->fetchTable('Servers');
        $server    = $Servers->get($serverId);
        $serverKey = 'servers:' . $serverId;

        if (empty($vv_bc_parents[$serverKey])) {
          $parents[$serverKey] = [
            'label'  => method_exists($Servers, 'generateDisplayField')
              ? $Servers->generateDisplayField($server)
              : ($server->{$Servers->getDisplayField()} ?? ('Server #' . $serverId)),
            'target' => [
              'plugin'     => null,
              'controller' => 'Servers',
              'action'     => 'edit',
              $serverId,
            ],
          ];
        }
      } catch (\Throwable $e) {
        // Skip this crumb if we can’t load the parent server
      }
    }

    return $parents;
  }

  /**
   * Build the common person-centric breadcrumb parents.
   *
   * Returns only the parents to prepend (does not overwrite existing `vv_bc_parents`).
   * Safely de-duplicates by checking currently set parents.
   *
   * @param int $personId The person ID to build crumbs for.
   * @param bool $includePeopleIndex Whether to include the People index crumb.
   * @return array<string,array{label:string,target:array}>
   */
  protected function buildPersonBreadcrumbs(int $personId, bool $includePeopleIndex = true): array
  {
    if ($personId <= 0) {
      return [];
    }

    $People = $this->fetchTable('People');
    $vv_bc_parents = (array)$this->viewBuilder()->getVar('vv_bc_parents');
    $parents = [];

    // 1) People index
    if ($includePeopleIndex && empty($vv_bc_parents['cos:' . $this->getCOID()])) {
      $parents['cos:' . $this->getCOID()] = [
        'label'  => __d('controllers', 'People', [99]),
        'target' => [
          'plugin'     => null,
          'controller' => 'people',
          'action'     => 'index',
          '?'          => [ 'co_id' => $this->getCOID() ],
        ],
      ];
    }

    // 2) Person crumb
    $person = $People->get($personId, contain: ['PrimaryName']);
    $personKey = strtolower($People->getAlias()) . ':' . $personId; // eg: people:123

    if (empty($vv_bc_parents[$personKey])) {
      $parents[$personKey] = [
        'label'  => $People->generateDisplayField($person),
        'target' => [
          'plugin'     => null,
          'controller' => 'people',
          'action'     => 'edit',
          $personId,
        ],
      ];
    }

    return $parents;
  }
}