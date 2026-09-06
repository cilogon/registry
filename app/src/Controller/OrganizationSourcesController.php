<?php
/**
 * COmanage Registry Organization Sources Controller
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Controller;

use App\Lib\Util\StringUtilities;
use Cake\ORM\TableRegistry;

class OrganizationSourcesController extends StandardPluggableController {

  protected array $paginate = [
    'order' => [
      'OrganizationSources.description' => 'asc'
    ]
  ];

  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.3.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */

  public function beforeFilter(\Cake\Event\EventInterface $event) {
    if(!$this->request->is('restful')) {
      // Provide additional hints to BreadcrumbsComponent. This needs to be here
      // and not in beforeRender because the component beforeRender will run first.
      
      if(in_array($this->request->getParam('action'), ['retrieve', 'search'])) {
        $os = $this->OrganizationSources->get($this->request->getParam('pass')[0]);

        $this->Breadcrumb->injectTitleLink($this->OrganizationSources, $os);

        if($this->request->getParam('action') == 'retrieve') {
          $this->Breadcrumb->injectTitleLink(
            table: $this->OrganizationSources, 
            entity: $os,
            action: 'search',
            label: __d('operation', 'ExternalIdentitySources.search')
          );

          $this->set('vv_os', $os);
        }
      }
    }

    parent::beforeFilter($event);
  }

  /**
   * Calculate the redirect for this request.
   *
   * @since  COmanage Registry v5.3.0
   * @param  Entity $entity   Subject entity
   * @return array            Redirect
   */
  
  public function calculateRedirectTarget($entity): array {
    // We should only be called for sync, after which we want to redirect to a URL like
    // /registry/organization-sources/retrieve/3?source_key=foo

    return [
      'controller'  => 'OrganizationSources',
      'action'      => 'retrieve',
      $this->request->getParam('pass.0'),
      '?' => [
        'source_key'  => $this->request->getQuery('source_key')
      ]
    ];
  }

  /**
   * Retrieve a record from an Organization Source backend.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  string   $id Organiation Source to search
   */
  
  public function retrieve(string $id) {
    try {
      $source_key = $this->request->getQuery('source_key');

      $this->set('vv_os_record', $this->OrganizationSources->retrieve((int)$id, $source_key));

      $organizationRecordObj = $this->OrganizationSources
                                    ->OrganizationSourceRecords
                                    ->find()
                                    ->where(['OrganizationSourceRecords.source_key' => $source_key,
                                              'OrganizationSourceRecords.organization_source_id' => $id])
                                    ->contain(['Organizations'])
                                    ->first();
      $this->set('vv_organization_record', $organizationRecordObj);

      if($organizationRecordObj === null) {
        // Create an empty entity
        $organizationRecordObj = $this->OrganizationSources
                                      ->OrganizationSourceRecords
                                      ->newEmptyEntity();
      }

      [$title, , ] = StringUtilities::entityAndActionToTitle($organizationRecordObj,
                                                             StringUtilities::entityToClassName($organizationRecordObj),
                                                             'view');
      $this->set('vv_title', $title);
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());

      return $this->generateRedirect(null);
    }
  }

  /**
   * Perform a search against an Organization Source backend.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  string   $id Organization Source to search
   */

  public function search(string $id) {
    if($this->request->is('post')) {
      try {
        // The search attributes are backend specific, pass them as an array
        $search = $this->request->getData('search');

        $matches = $this->OrganizationSources->search((int)$id, $search);

        $this->set('vv_search_results', $matches);
      }
      catch(\Exception $e) {
        $this->Flash->error($e->getMessage());
      }
    }

    // Obtain the searchable attributes and pass to the view

    $this->set('vv_search_attrs', $this->OrganizationSources->searchableAttributes((int)$id));

    [$title, , ] = StringUtilities::entityAndActionToTitle(
      null,
      null,
      $this->getName() . '.search',
    );
    $this->set('vv_title', $title);
  }

  /**
   * Perform an Organization sync.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  string $id Organization Source ID
   */

  public function sync(string $id) {
    try {
      $source_key = $this->request->getQuery('source_key');
      
      $ret = $this->OrganizationSources->sync(id: (int)$id, sourceKey: $source_key, force: true);
      
      $this->Flash->success(__d('result', 'OrganizationSources.synced'));

      // On success, redirect to the new Organization

      return $this->redirect([
        'controller'  => 'organizations',
        'action'      => 'edit',
        $ret['id']
      ]);
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }
    
    return $this->generateRedirect(null);
  }
}