<?php
/**
 * COmanage Registry API v2 Controller
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

use InvalidArgumentException;
use Cake\Chronos\Chronos;
use Cake\Http\Exception\BadRequestException;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

use \App\Lib\Enum\ProvisioningContextEnum;
use \App\Lib\Enum\SuspendableStatusEnum;

class ApiV2Controller extends AppController {
  use \App\Lib\Traits\LabeledLogTrait;
  
  /**
   * Perform Cake Controller initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
    
  public function initialize(): void {
    parent::initialize();
    
    // requested model = models
    $reqModel = $this->request->getParam('model');
    // $this->name = Models
    // We override $this->name (which is ApiV2) to make it match to the expected
    // behavior for UI calls (which is Models, eg "Cous"). We need to do this
    // before RegistryAuthComponent runs.
    $modelsName = Inflector::camelize($reqModel);
    $this->name = $modelsName;
    // Similarly, for compatibility with UI related calls we load the model
    $this->$modelsName = TableRegistry::getTableLocator()->get($modelsName);
    $this->tableName = $this->$modelsName->getTable();
    
    // We want API auth, not Web Auth
    $this->RegistryAuth->setConfig('apiUser', true);
  }
  
  /**
   * Handle an add action for a Standard object.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function add() {
    // $this->name = Models
    $modelsName = $this->name;
    // $tableName = models
    $tableName = $this->tableName;
    
    $json = $this->request->getData(); // Parsed by BodyParserMiddleware
    
    if(empty($json[$modelsName])) {
      $this->llog('debug', $modelsName . " object not found in request");
      throw new BadRequestException(__d('error', 'api.object', [$modelsName]));
    }
    
    $results = [];
    
    foreach($json[$modelsName] as $rec) {
      try {
        $obj = $this->$modelsName->newEntity($rec);
        
        if($this->$modelsName->saveOrFail($obj)) {
          $results[] = ['id' => $obj->id];

          // Trigger provisioning, letting errors bubble up (AR-GMR-5)
          if(method_exists($table, "requestProvisioning")) {
            $this->llog('rule', "AR-GMR-5 Requesting provisioning for $modelsName " . $obj->id);
            $table->requestProvisioning(id: $obj->id, context: ProvisioningContextEnum::Automatic);
          }
        }
      }
      catch(\Exception $e) {
        // The default exception error isn't particularly user friendly, so
        // we dig into the entity errors and try to make a message from there.
        $err = $this->exceptionToError($e);
        
        $this->llog('debug', $err);
        $results[] = ['error' => $err];
      }
    }
    
    $this->set('vv_results', $results);

    // Let the view render
    $this->render('/Standard/api/v2/json/add-edit');
  }
  
  /**
   * Callback run prior to the request rendering.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   * @return EventInterface
   */
  
  public function beforeRender(\Cake\Event\EventInterface $event) {
    $this->set('vv_model_name', $this->name);
    $this->set('vv_table_name', $this->tableName);
    
    return parent::beforeRender($event);
  }
  
  /**
   * Handle a delete action for a Standard object.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Integer $id Object ID
   */
  
  public function delete($id) {
    // $this->name = Models (ie: from ModelsTable)
    $modelsName = $this->name;
    
    // Make sure the requested object exists
    try {
      $obj = $this->$modelsName->findById($id)->firstOrFail();
      
// XXX document AR-CO-1 when we implement hard delete/changelog
//     note similar logic in StandardController
      $this->$modelsName->deleteOrFail($obj);
      
      if(method_exists($obj, "isReadOnly") && $obj->isReadOnly()) {
        throw new BadRequestException(__d('error', 'edit.readonly'));
      }

      // Trigger provisioning, letting errors bubble up (AR-GMR-5)
      if(method_exists($table, "requestProvisioning")) {
        $this->llog('rule', "AR-GMR-5 Requesting provisioning for deleted entity $modelsName " . $obj->id);
        $table->requestProvisioning(id: $obj->id, context: ProvisioningContextEnum::Automatic);
      }

      // Render an empty view
      $this->render('/Standard/api/v2/json/delete');
    }
    catch(\Exception $e) {
      // findById throws Cake\Datasource\Exception\RecordNotFoundException
      
      // Rethrow the error so it formats correctly
      throw new BadRequestException($this->exceptionToError($e));
    }
  }
  
  /**
   * Handle an edit action for a Standard object.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Integer $id Object ID
   */
  
  public function edit($id) {
    // $this->name = Models (ie: from ModelsTable)
    $modelsName = $this->name;
    // $tableName = models
    $tableName = $this->$modelsName->getTable();

    $query = $this->$modelsName->findById($id);

    try {
      // Pull the current record
      $obj = $query->firstOrFail();

      if(method_exists($obj, "isReadOnly") && $obj->isReadOnly()) {
        throw new BadRequestException(__d('error', 'edit.readonly'));
      }
      
      $json = $this->request->getData(); // Parsed by BodyParserMiddleware

      if(empty($json[$modelsName])) {
        throw new BadRequestException(__d('error', 'api.object', [$modelsName]));
      }
      
      $obj = $this->$modelsName->patchEntity($obj, $json[$modelsName]);
      
      $this->$modelsName->saveOrFail($obj);

      // Trigger provisioning, letting errors bubble up (AR-GMR-5)
      if(method_exists($this->$modelsName, "requestProvisioning")) {
        $this->llog('rule', "AR-GMR-5 Requesting provisioning for $modelsName " . $obj->id);
        $this->$modelsName->requestProvisioning(id: $obj->id, context: ProvisioningContextEnum::Automatic);
      }

      // Let the view render
      $this->render('/Standard/api/v2/json/add-edit');
    }
    catch(\Exception $e) {
      // findById throws Cake\Datasource\Exception\RecordNotFoundException
      
      // The default exception error isn't particularly user friendly, so
      // we dig into the entity errors and try to make a message from there.
      $err = $this->exceptionToError($e);
      
      $this->llog('debug', $err);
      $results[] = ['error' => $err];

      throw new BadRequestException($this->exceptionToError($e));
    }
  }
  
  /**
   * Convert an Exception to an error string suitable for the REST response,
   * including field validation errors.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Exception $e Exception
   * @return string       Error string
   */
  
  protected function exceptionToError(\Exception $e): string {
    // Default error
    $err = $e->getMessage();
    
    if(method_exists($e, "getEntity")) {
      // Check for field validation errors
      $errors = $e->getEntity()->getErrors();
      
      if(!empty($errors)) {
        // Flatten the array into a text string
        
        $byAttr = [];
        
        foreach($errors as $attr => $msgs) {
          $byAttr[] = $attr . ": " . implode(',', array_values($msgs));
        }
        
        $err = implode(';', $byAttr);
      }
    }
    
    return $err;
  }
  
  /**
   * Generate an API Key for an API User.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id API User ID
   */
  
  public function generateApiKey(string $id) {
    // $id is always an int, but (1) in theory could be a string if we ever
    // switched to UUIDs, and (2) ControllerFactory::invoke() (as triggered by
    // the configuration in routes.php) only supports type string.
    
    // Just let exceptions pop up the stack
    $api_key = $this->ApiUsers->generateKey((int)$id);
    
    $this->set('vv_results', ['api_key' => $api_key]);
    
    // Let the view render
    $this->render('/Standard/api/v2/json/add-edit');
  }
  
  /**
   * Generate an index for a set of Standard Objects.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function index() {
    // $modelsName = Models
    $modelsName = $this->name;
    
    $query = $this->$modelsName->find();
    
    // PrimaryLinkTrait
    $link = $this->getPrimaryLink(true);
    
    // We automatically allow API calls to be filtered on primary link
    if(!empty($link->attr) && !empty($link->value)) {
      $query = $query->where([$this->$modelsName->getAlias().'.'.$link->attr => $link->value]);
    }

    // This will produce a nested object which is very useful for vue integration
    if($this->request->getQuery('extended') !== null) {
      $modelContain = [];
      $associations = $this->$modelsName->associations();
      foreach($associations->getByType(['BelongsTo']) as $a) {
        $modelContain[] = $a->getClassName();
      }

      if(!empty($modelContain)) {
        $query = $query->contain($modelContain);
      }
    }

    if($modelsName == 'AuthenticationEvents') {
      // Special case for filtering on authenticated identifier. There is a
      // similar filter in AuthenticationEventsController::beforeFilter.
      // If other special cases show up this should get refactored into a trait
      // populated by the table (or something similar).
      
      if($this->getRequest()->getQuery('authenticated_identifier')) {
        $query = $query->where(['authenticated_identifier' => \App\Lib\Util\StringUtilities::urlbase64decode($this->getRequest()->getQuery('authenticated_identifier'))]);
      } else {
        // We only allow unfiltered queries for platform users
        
        if(!$this->RegistryAuth->isPlatformAdmin()) {
          throw new \InvalidArgumentException(__d('error', 'input.notprov', 'authenticated_identifier'));
        }
      }
    }
    
    // This magically makes REST calls paginated... can use eg direction=,
    // sort=, limit=, page= 
    $this->set($this->tableName, $this->paginate($query));
    
    // Let the view render
    $this->render('/Standard/api/v2/json/index');
  }

  /**
   * Generate a view for a set of Standard Objects.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function view($id = null) {
    // $this->name = Models
    $modelsName = $this->name;
    // $tableName = models
    $tableName = $this->$modelsName->getTable();
    
    if(empty($id)) {
      throw new InvalidArgumentException(__d('error', 'notprov', ['id']));
    }
    
    $obj = $this->$modelsName->findById($id)->firstOrFail();
    
    $this->set($tableName, [$obj]);
    
    // Let the view render
    $this->render('/Standard/api/v2/json/index');
  }
}