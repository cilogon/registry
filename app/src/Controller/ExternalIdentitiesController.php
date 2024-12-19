<?php
/**
 * COmanage Registry External Identities Controller
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

use Cake\Event\EventInterface;
use Cake\Http\Response;

// Use extend MVEAController for breadcrumb rendering. ExternalIdentities is
// sort of an MVEA, so maybe it makes sense to treat it as such.
class ExternalIdentitiesController extends MVEAController {
  public $paginate = [
    'order' => [
      'Name.family' => 'asc'
    ],
    'sortableFields' => [
      'Names.given',
      'Names.family'
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
    // Pull the Person name for breadcrumb rendering

    $link = $this->getPrimaryLink(true);

    if(!empty($link->value)) {
      $this->set('vv_bc_parent_obj', $this->ExternalIdentities->People->get($link->value));
      $this->set('vv_bc_parent_displayfield', $this->ExternalIdentities->People->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->ExternalIdentities->People->getPrimaryKey());
    }

    return parent::beforeRender($event);
  }
}