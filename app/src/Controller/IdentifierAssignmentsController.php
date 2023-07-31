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
use \App\Lib\Util\StringUtilities;

class IdentifierAssignmentsController extends StandardPluggableController {
  public $paginate = [
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