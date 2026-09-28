<?php
/**
 * COmanage Registry LDAP Schemas Controller
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace LdapConnector\Controller;

use App\Controller\StandardPluginController;
use Cake\Event\EventInterface;
use Cake\Http\Response;

class LdapSchemasController extends StandardPluginController {
  use \App\Lib\Traits\PluggableControllerTrait;

  protected array $paginate = [
    'order' => [
      'LdapSchemas.description' => 'asc'
    ]
  ];

  /**
   * Perform controller initialization.
   *
   * Configures breadcrumb behavior for query-context primary links.
   *
   * @return void
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function initialize(): void
  {
    parent::initialize();

    // Build breadcrumb chain from query context: ?ldap_provisioner_id=...
    $this->Breadcrumb->configureQueryPrimaryLinks([
      'index' => ['ldap_provisioner_id']
    ]);
  }

  /**
   * Generate an index for the available set of LDAP Schemas.
   *
   * @return \Cake\Http\Response|void|null
   * @since  COmanage Registry v5.3.0
   */
  public function index() {
    $link = $this->getPrimaryLink(true);
    $provisionerId = $link->value ?? $this->request->getQuery('ldap_provisioner_id');

    if (!empty($provisionerId)) {
      try {
        $this->LdapSchemas->syncLdapSchemasIndex((int)$provisionerId);
      } catch (\Throwable $e) {
        $this->Flash->error($e->getMessage());
      }
    }

    return parent::index();
  }

  /**
   * Callback run prior to the request render.
   *
   * Sets breadcrumb parent to the owning LdapProvisioner (via primary link ldap_provisioner_id).
   *
   * @param EventInterface $event Cake Event
   * @return Response|void|null
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function beforeRender(EventInterface $event) {
    $link = $this->getPrimaryLink(true);

    if(!empty($link->value)) {
      $this->set('vv_bc_parent_obj', $this->LdapSchemas->LdapProvisioners->get($link->value, contain: ['ProvisioningTargets']));
      $this->set('vv_bc_parent_displayfield', $this->LdapSchemas->LdapProvisioners->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->LdapSchemas->LdapProvisioners->getPrimaryKey());
    }

    return parent::beforeRender($event);
  }
}
