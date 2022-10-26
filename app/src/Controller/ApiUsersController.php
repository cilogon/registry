<?php
/**
 * COmanage Registry API Users Controller
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

class ApiUsersController extends StandardController {
  public $paginate = [
    'order' => [
      'ApiUsers.username' => 'asc'
    ]
  ];
  
  /**
   * Generate a new API Key.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id API User ID (This is really an int, but Cake throws an error since it wants to pass type string)
   */
  
  public function generate(string $id) {
    // We don't autogenerate after add because we'd have to interfere with performRedirect.
    
    try {
      $this->set('vv_obj', $this->ApiUsers->get($id));
      $this->set('vv_api_key', $this->ApiUsers->generateKey((int)$id));
    }
    catch(Exception $e) {
      $this->Flash->error($e->getMessage());
    }
    
    // Let the view render, but tell it to use a different fields file
    $this->set('vv_fields_inc', 'fields-generate.inc');
    $this->set('vv_title', __d('operation', 'api.key.generate'));
    
    $this->render('/Standard/add-edit-view');
  }
}