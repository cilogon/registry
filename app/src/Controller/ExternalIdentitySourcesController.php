<?php
/**
 * COmanage Registry External Identity Sources Controller
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

// XXX not doing anything with Log yet
use App\Lib\Util\StringUtilities;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

class ExternalIdentitySourcesController extends StandardPluggableController {

  protected array $paginate = [
    'order' => [
      'ExternalIdentitySources.description' => 'asc'
    ]
  ];

  /**
   * Annul an External Identity adoption.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string $id External Identity Source ID
   */

  public function annul(string $id) {
    try {
      $source_key = $this->request->getQuery('source_key');

      $this->ExternalIdentitySources->annul((int)$id, $source_key);

      $this->Flash->success(__d('result', 'ExternalIdentitySources.synced'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }
    
    return $this->generateRedirect(null);
  }

  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */

  public function beforeFilter(\Cake\Event\EventInterface $event) {
    if(!$this->request->is('restful')) {
      // Provide additional hints to BreadcrumbsComponent. This needs to be here
      // and not in beforeRender because the component beforeRender will run first.
      
      if(in_array($this->request->getParam('action'), ['retrieve', 'search'])) {
        $eis = $this->ExternalIdentitySources->get($this->request->getParam('pass')[0]);

        $this->Breadcrumb->injectTitleLink($this->ExternalIdentitySources, $eis);

        if($this->request->getParam('action') == 'retrieve') {
          $this->Breadcrumb->injectTitleLink(
            table: $this->ExternalIdentitySources, 
            entity: $eis,
            action: 'search',
            label: __d('operation', 'ExternalIdentitySources.search')
          );

          $this->set('vv_eis', $eis);
        }
      }
    }

    parent::beforeFilter($event);
  }

  /**
   * Calculate the redirect for this request.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity   Subject entity
   * @return array            Redirect
   */
  
  public function calculateRedirectTarget($entity): array {
    // We should only be called for sync, after which we want to redirect to a URL like
    // /registry/external-identity-sources/retrieve/3?source_key=foo

    return [
      'controller'  => 'ExternalIdentitySources',
      'action'      => 'retrieve',
      $this->request->getParam('pass.0'),
      '?' => [
        'source_key'  => $this->request->getQuery('source_key')
      ]
    ];
  }

  /**
   * Retrieve a record from an External Identity Source backend.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string   $id External Identity Source to search
   */
  
  public function retrieve(string $id) {
    try {
      $source_key = $this->request->getQuery('source_key');

      $this->set('vv_eis_record', $this->ExternalIdentitySources->retrieve((int)$id, $source_key));

      $externalIdentityRecordObj = $this->ExternalIdentitySources
                                        ->ExtIdentitySourceRecords
                                        ->find()
                                        ->where(['ExtIdentitySourceRecords.source_key' => $source_key,
                                                 'ExtIdentitySourceRecords.external_identity_source_id' => $id])
                                        ->contain(['ExternalIdentities'])
                                        ->first();
      $this->set('vv_external_identity_record', $externalIdentityRecordObj);

      if($externalIdentityRecordObj === null) {
        // I need an empty entity
        $ExtIdentitySourceRecords = $this->getTableLocator()->get('ExtIdentitySourceRecords');
        // Create an empty entity for FormHelper
        $externalIdentityRecordObj = $ExtIdentitySourceRecords->newEmptyEntity();
      }

      [$title, , ] = StringUtilities::entityAndActionToTitle($externalIdentityRecordObj,
                                                             StringUtilities::entityToClassName($externalIdentityRecordObj),
                                                             'view');
      $this->set('vv_title', $title);
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());

      return $this->generateRedirect(null);
    }
  }

  /**
   * Perform a search against an External Identity Source backend.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string   $id External Identity Source to search
   */

  public function search(string $id) {
    if($this->request->is('post')) {
      try {
        // The search attributes are backend specific, pass them as an array
        $search = $this->request->getData('search');

        $matches = $this->ExternalIdentitySources->search((int)$id, $search);

        $this->set('vv_search_results', $matches);
      }
      catch(\Exception $e) {
        $this->Flash->error($e->getMessage());
      }
    }

    // Obtain the searchable attributes and pass to the view

    $this->set('vv_search_attrs', $this->ExternalIdentitySources->searchableAttributes((int)$id));

    [$title, , ] = StringUtilities::entityAndActionToTitle(null,
                                                           $this->getName(),
                                                           $this->request->getParam('action'));
    $this->set('vv_title', $title);
  }

  /**
   * Perform an External Identity sync.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $id External Identity Source ID
   */

  public function sync(string $id) {
    try {
      $source_key = $this->request->getQuery('source_key');

      $this->ExternalIdentitySources->sync((int)$id, $source_key);
      // XXX Sync does not have a view. We do not need to set a title. Yet need to fetch the updated Identity
      $this->set('vv_eis_record', $this->ExternalIdentitySources->retrieve((int)$id, $source_key));
      
      $this->Flash->success(__d('result', 'ExternalIdentitySources.synced'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }
    
    return $this->generateRedirect(null);
  }
}