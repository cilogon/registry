<?php

/**
 * COmanage Registry LDAP Provisioners Controller
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

class LdapProvisionersController extends StandardPluginController {

  protected array $paginate = [
    'order' => [
      'LdapProvisioners.id' => 'asc'
    ]
  ];

  /**
   * Perform controller initialization.
   *
   * Configures breadcrumb behavior for plugin configuration edit/view pages.
   *
   * @return void
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function initialize(): void {
    parent::initialize();

    // Don't inject an automatic "index" breadcrumb for plugin configuration edit pages.
    // We want: ... > Provisioning Targets > Configure LDAP Provisioner
    $this->Breadcrumb->skipParents([
      '/^\/ldap-connector\/ldap-provisioners\/(edit|view)\//'
    ]);
  }

  /**
   * Edit an LDAP Provisioner configuration.
   *
   * @param string $id LdapProvisioner ID
   * @return \Cake\Http\Response|void|null
   * @since  COmanage Registry v5.3.0
   */
  public function edit(string $id) {
    try {
      $LdapSchemas = $this->getTableLocator()->get('LdapConnector.LdapSchemas');
      $LdapSchemas->syncLdapSchemasIndex((int)$id);
    } catch (\Throwable $e) {
      $this->Flash->error($e->getMessage());
    }

    return parent::edit($id);
  }

  /**
   * Callback run prior to the request render.
   *
   * @param EventInterface $event Cake Event
   * @return Response|void|null
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function beforeRender(EventInterface $event) {
    $link = $this->getPrimaryLink(true);

    if(!empty($link->value)) {
      $this->set('vv_bc_parent_obj', $this->LdapProvisioners->ProvisioningTargets->get($link->value));
      $this->set('vv_bc_parent_displayfield', $this->LdapProvisioners->ProvisioningTargets->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->LdapProvisioners->ProvisioningTargets->getPrimaryKey());
    }

    return parent::beforeRender($event);
  }

  /**
   * Resync all data, including Groups.
   *
   * @param  string $id LdapProvisioner ID
   * @return void
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function resync(string $id) {
    // not yet implemented
  }
}
