<?php
/**
 * COmanage Registry Match Server Attributes Controller
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

namespace CoreServer\Controller;

use App\Controller\StandardPluginController;
use App\Lib\Util\StringUtilities;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;

class MatchServerAttributesController extends StandardPluginController {
  protected array $paginate = [
    'order' => [
      'MatchServerAttributes.attribute' => 'asc'
    ]
  ];

  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.2.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response|void|null
   */

  public function beforeRender(\Cake\Event\EventInterface $event)
  {
    $link = $this->getPrimaryLink(true);
    $matchServer = null;

    if(!empty($link->value)) {
      $matchServer = $this->MatchServerAttributes->MatchServers->get($link->value, contain: ['Servers']);
      $this->set('vv_bc_parent_obj', $matchServer);
      $this->set('vv_bc_parent_displayfield', $this->MatchServerAttributes->MatchServers->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->MatchServerAttributes->MatchServers->getPrimaryKey());
    }

    $customParents = $this->buildServerBreadcrumbs($matchServer);
    if (!empty($customParents)) {
      $vv_bc_parents = (array)$this->viewBuilder()->getVar('vv_bc_parents');
      $this->set('vv_bc_parents', [...$customParents, ...$vv_bc_parents]);
    }

    $title = __d('core_server', 'controller.MatchServerAttributes', [99]);
    if(in_array($this->request->getParam('action'), ['add', 'edit'])) {
      $title = __d('operation', strtolower($this->request->getParam('action')) . '.a', [$title]);
    }

    $this->set('vv_title', $title);

    return parent::beforeRender($event);
  }

  /**
   * Build breadcrumb parents for match server attribute views.
   *
   * @param EntityInterface|null $matchServer MatchServer entity with contained Server
   * @return array<string,array{label:string,target:array}> Breadcrumb parents
   * @since  COmanage Registry v5.3.0
   */
  protected function buildServerBreadcrumbs(?EntityInterface $matchServer = null): array
  {
    if (!$matchServer || empty($matchServer->server)) {
      return [];
    }

    $server = $matchServer->server;
    [$configureTitle] = StringUtilities::entityAndActionToTitle(
      $matchServer,
      'CoreServer.MatchServers',
      'configure'
    );

    return [
      'servers:index' => [
        'label'  => StringUtilities::localizeController('Servers', null, true),
        'target' => [
          'plugin'     => null,
          'controller' => 'Servers',
          'action'     => 'index',
          '?'          => ['co_id' => $server->co_id ?? $this->getCOID()],
        ],
      ],
      'servers:' . $server->id => [
        'label' => !empty($server->description)  ? $server->description : __d('core_server', 'controller.MatchServers', [1]) . " #{$server->id}",
        'target' => [
          'plugin'     => null,
          'controller' => 'Servers',
          'action'     => 'edit',
          $server->id,
        ],
      ],
      'match_servers:' . $matchServer->id => [
        'label'  => $configureTitle,
        'target' => [
          'plugin'     => 'CoreServer',
          'controller' => 'MatchServers',
          'action'     => 'edit',
          $matchServer->id,
        ],
      ],
    ];
  }
}
