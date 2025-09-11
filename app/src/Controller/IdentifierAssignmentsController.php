<?php
/**
 * COmanage Registry IdentifierAssignments Controller
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
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use \App\Lib\Util\StringUtilities;

class IdentifierAssignmentsController extends StandardPluggableController {
  protected array $paginate = [
    'order' => [
      'IdentifierAssignments.description' => 'asc'
    ]
  ];

  /**
   * Assign Identifiers.
   * 
   * @since  COmanage Registry v5.0.0
   */

  public function assign() {
    $link = $this->getPrimaryLink(true);

    if($link->attr == 'co_id') {
      // We've been asked to assign Identifiers for all entities within a CO,
      // which we do by queuing a job.

      $JobTable = TableRegistry::getTableLocator()->get("Jobs");

      try {
        $contexts = ['People', 'Groups'];

        foreach($contexts as $context) {
          $JobTable->register(
            coId:             (int)$link->value,
            plugin:           'CoreJob.AssignerJob',
            parameters:       ['context' => $context],
            registerSummary:  __d('result', 'IdentifierAssignments.queued.ok', [$context, $link->value])
          );
        }

        $this->Flash->success(__d('result', 'IdentifierAssignments.queued.ok', [implode(', ', $contexts), $link->value]));
      }
      catch(\Exception $e) {
        $this->Flash->error($e->getMessage());
      }

      // We need to explicitly set the redirect since generateRedirect() will miscalculate
      return $this->redirect([
        'action'  => 'index',
        '?'       => ['co_id' => $link->value]
      ]);
    } else {
      // We're assigning Identifiers for a single entity
      try {
        $results = $this->IdentifierAssignments->assign(
          entityType: StringUtilities::foreignKeyToClassName($link->attr),
          entityId: (int)$link->value
        );

        if(!empty($results)) {
          // We could get multiple types of results from different Identifier Assignments

          if(!empty($results['assigned'])) {
            $this->Flash->success(__d('result', 'IdentifierAssignments.assigned.ok', implode(',', array_keys($results['assigned']))));
          }

          if(!empty($results['errors'])) {
            $this->Flash->error(implode(',', $results['errors']));
          }

          if(!empty($results['already'])) {
            $this->Flash->information(__d('result', 'IdentifierAssignments.assigned.already', implode(',', array_keys($results['already']))));
          }
        }
      }
      catch(\Exception $e) {
        $this->Flash->error($e->getMessage());
      }

      $this->generateRedirect(null);
    }
  }
}