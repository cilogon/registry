<?php
/**
 * COmanage Registry File Sources Controller
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

namespace FileConnector\Controller;

use App\Controller\StandardPluginController;
use Cake\Event\EventInterface;
use Cake\Http\Response;

class FileSourcesController extends StandardPluginController {
  protected array $paginate = [
    'order' => [
      'FileSources.id' => 'asc'
    ]
  ];

  /**
   * Callback run prior to the request render.
   *
   * @param   EventInterface  $event  Cake Event
   *
   * @return Response|void
   * @since  COmanage Registry v5.0.0
   */

  public function beforeRender(EventInterface $event) {
    $link = $this->getPrimaryLink(true);

    if(!empty($link->value)) {
      $this->set('vv_bc_parent_obj', $this->FileSources->ExternalIdentitySources->get($link->value));
      $this->set('vv_bc_parent_displayfield', $this->FileSources->ExternalIdentitySources->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->FileSources->ExternalIdentitySources->getPrimaryKey());
    }

    return parent::beforeRender($event);
  }
}
