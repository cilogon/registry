<?php
/**
 * COmanage Registry Clusters Controller
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

// XXX not doing anything with Log yet
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;

class ClustersController extends StandardPluggableController {
  public array $paginate = [
    'order' => [
      'Clusters.description' => 'asc'
    ]
  ];

  /**
   * Assign accounts for all Clusters for a Person,
   * 
   * @since  COmanage Registry v5.3.0
   */

  public function assign() {
    $targetID = (int)$this->request->getQuery('person_id');

    if(empty($targetID)) {
      throw new \InvalidArgumentException(__d('error', 'notprov', ['person_id']));
    }

    try {
      $results = $this->Clusters->assign(personId: $targetID);

      // We can get a mix of success and failure here. We'll generate flashes for each.

      if(!empty($results['assigned'])) {
        $msg = implode(',', Hash::extract($results['assigned'], '{n}.description'));

        $this->Flash->success(__d('result', 'Clusters.assigned', [$msg]));
      }

      if(!empty($results['existing'])) {
        $msg = implode(',', Hash::extract($results['existing'], '{n}.description'));

        $this->Flash->information(__d('result', 'Clusters.assigned.already', [$msg]));
      }

      // We'll generate one flash for each failure to call out that multiple errors
      // happened, though under most circumstances rendering more than one or two of
      // these is unlikely.

      foreach($results['error'] as $e) {
        $this->Flash->error($e['message']);
      }
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->redirect(['action' => 'status', '?' => ['person_id' => $targetID]]);
  }

  /**
   * Generate a status index.
   *
   * @since  COmanage Registry v5.3.0
   */

  public function status() {
    $targetID = (int)$this->request->getQuery('person_id');
    
    if(empty($targetID)) {
      throw new \InvalidArgumentException(__d('error', 'notprov', ['person_id']));
    }

    $this->set('vv_cluster_statuses', $this->Clusters->status(personId: $targetID));

    if(!$this->request->is('restful')) {
      $People = TableRegistry::getTableLocator()->get('People');

      $entity = $People->get($targetID, contain: ['PrimaryName']);
      $targetName = $entity->primary_name->full_name;

      $this->set('vv_title', __d('information', 'Clusters.status.title', $targetName));
      $this->set('vv_subject', $entity);
    }
  }
}