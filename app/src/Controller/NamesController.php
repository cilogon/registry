<?php
/**
 * COmanage Registry Names Controller
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

class NamesController extends MVEAController {
  use \App\Lib\Traits\PermissionsTrait;
  
  public $pagination = [
    'order' => [
      'Names.family' => 'asc',
      'Names.given' => 'asc'
    ]
  ];

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function initialize(): void {
    parent::initialize();
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'primary' =>  ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }
  
  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */
  
  public function beforeRender(\Cake\Event\EventInterface $event) {
    if(!$this->request->is('restful')) {
      // Get the set of permitted name fields to pass to the view.
      // (We don't need required name fields since FormHelper will handle that.)
      
// XXX maybe $CoSettings should be available via AppController, like $this->getCOID()?
      $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');
      
      $settings = $CoSettings->find()->where(['co_id' => $this->getCOID()])->firstOrFail();
      
      $this->set('vv_permitted_fields', $settings->name_permitted_fields_array());
      $this->set('vv_default_type', $settings->name_default_type_id);
    }
    
    return parent::beforeRender($event);
  }
  
  /**
   * Set a Name as primary.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id          Name ID
   * @return \Cake\Http\Response HTTP Response
   */
  
  public function primary(string $id) {
    // All we need to do is set this name to be primary, the model code will
    // handle the various Application Rules.
    
    try {
      $query = $this->Names->findById($id);
      
      // Pull the current record
      $obj = $query->firstOrFail();
      
      $obj->primary_name = true;
      $this->Names->save($obj);
      
      $this->Flash->success(__d('result', 'Names.primary_name'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }
    
    return $this->generateRedirect((int)$id);
  }
}