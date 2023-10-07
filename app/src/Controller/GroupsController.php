<?php
/**
 * COmanage Registry Groups Controller
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

class GroupsController extends StandardController {
  public $paginate = [
    'order' => [
      'Groups.name' => 'asc'
    ]
  ];
  
  /**
   * Reconcile a Group's memberships.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id Group ID
   */

  public function reconcile(string $id) {
    try {
      $group = $this->Groups->get((int)$id);

      $this->Groups->reconcile($group->id);

      $this->Flash->success(__d('result', 'Groups.reconciled'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->generateRedirect($group ?? null);
  }
}