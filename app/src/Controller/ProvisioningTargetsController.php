<?php
/**
 * COmanage Registry Provisioning Targets Controller
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
use Cake\Utility\Inflector;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

class ProvisioningTargetsController extends StandardPluggableController {
  protected array $paginate = [
    'order' => [
      'ProvisioningTargets.ordr' => 'asc'
    ]
  ];

  /**
   * Perform Controller initialization.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function initialize(): void {
    parent::initialize();
    
    // Configure breadcrumb rendering
    $this->Breadcrumb->skipConfig(['/^\/provisioning-targets\/status/']);
    $this->Breadcrumb->skipParents(['/^\/provisioning-targets\/status/']);
  }

  /**
   * Register a (re)provisioning job.
   * 
   * @since  COmanage Registry v5.0.0
   */

  public function reprovision(string $id) {
    // We call this "reprovision" and not "provision" to avoid conflicts with
    // StandardController::provision.

    $JobTable = TableRegistry::getTableLocator()->get("Jobs");

    try {
      $target = $this->ProvisioningTargets->get((int)$id);

      $models = ['People', 'Groups'];

      foreach($models as $model) {
        $JobTable->register(
          coId:             $this->getCOID(),
          plugin:           'CoreJob.ProvisionerJob',
          parameters:       ['model' => $model, 'provisioning_target_id' => $id],
          registerSummary:  __d('result', 'ProvisioningTargets.queued.ok', [$model, $target->description, $target->id])
        );
      }

      $this->Flash->success(__d('result', 'ProvisioningTargets.queued.ok', [implode(', ', $models), $target->description, $target->id]));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->generateRedirect($target ?? null);
  }

  /**
   * Generate a status index.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function status() {
    // We support filtering on person_id or group_id.

    $targetModel = 'People';
    $targetFK = 'person_id';
    $targetName = '(?)';

    if(!empty($this->request->getQuery('group_id'))) {
      $targetModel = 'Groups';
      $targetFK = 'group_id';
    }

    $targetID = (int)$this->request->getQuery($targetFK);

    $this->set('vv_provisioning_statuses', $this->ProvisioningTargets->status(
      coId: $this->getCOID(),
      groupId: $targetFK == 'group_id' ? $targetID : null,
      personId: $targetFK == 'person_id' ? $targetID : null
    ));

    $this->set('vv_target_fk', $targetFK);
    $this->set('vv_target_id', $targetID);

    if(!$this->request->is('restful')) {
      $Model = TableRegistry::getTableLocator()->get($targetModel);

      if($targetModel == 'People') {
        $entity = $Model->get($targetID, contain: ['PrimaryName']);
        $targetName = $entity->primary_name->full_name;
      } else {
        $entity = $Model->get($targetID);
        $targetName = $entity->name;
      }

      $this->set('vv_title', __d('information', 'ProvisioningTargets.status.title', $targetName));
    }
  }
}