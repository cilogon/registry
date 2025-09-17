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
use Cake\Event\EventInterface;

class MatchServerAttributesController extends StandardPluginController {
  use \App\Lib\Traits\BreadcrumbsTrait;

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
   * @return \Cake\Http\Response   HTTP Response
   */

  public function beforeRender(\Cake\Event\EventInterface $event)
  {
    // Build standard server breadcrumbs from *_server_id
    $customParents = $this->buildServerParamBreadcrumbs();

    if (!empty($customParents)) {
      $vv_bc_parents = (array)$this->viewBuilder()->getVar('vv_bc_parents');
      $vv_bc_parents = [...$customParents, ...$vv_bc_parents];
      $this->set('vv_bc_parents', $vv_bc_parents);
    }

    $title = __d('core_server', 'controller.MatchServerAttributes', [99]);
    if(in_array($this->request->getParam('action'), ['add', 'edit'])) {
      $title = __d('operation', strtolower($this->request->getParam('action')) . '.a', [$title]);
    }

    $this->set('vv_title', $title);

    return parent::beforeRender($event);
  }
}
