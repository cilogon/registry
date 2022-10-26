<?php
/**
 * COmanage Registry Types Controller
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

class TypesController extends StandardController {
  public $paginate = [
    'order' => [
      'Types.attribute' => 'asc',
      'Types.display_name' => 'asc'
    ]
  ];
  
  /**
   * Restore default types for the requested CO.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function restore() {
    try {
      $this->Types->addDefaults($this->getCOID());
      
      $this->Flash->success(__d('result', 'saved'));
    }
    catch(\Exception $e) {
      // findById throws Cake\Datasource\Exception\RecordNotFoundException
      
      $this->Flash->error($e->getMessage());
    }
    
    return $this->generateRedirect(null);
  }
}