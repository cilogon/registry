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

use Cake\Chronos\Chronos;
use Cake\Controller\Controller;
use Cake\Http\Exception\BadRequestException;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use InvalidArgumentException;
use \App\Lib\Enum\EnrollmentAuthzEnum;
use \App\Lib\Enum\ProvisioningContextEnum;
use \App\Lib\Enum\SuspendableStatusEnum;

// This controller is a bit of a special case in that it combines the functionality
// of StandardController (add, edit, view) with model specific functionality
// (generateApiKey, provision). Access to these specific functions is defined via
// routes.php, and enabled via permissions in the model's Table file. Given the
// relatively few model specific API extensions, this is probably OK, but if we end
// up with significantly more of these we might need to consider some refactoring.

class ApiV2Controller extends AppController {
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\IndexQueryTrait;

  protected string $tableName = '';

  /**
   * Perform Cake Controller initialization.
   *
   * @since  COmanage Registry v5.0.0
   */
    
  public function initialize(): void {
    parent::initialize();
    
    // requested model = models
    $reqModel = $this->request->getParam('model');
    /** var string $modelsName */
    // We override $this->name (which is ApiV2) to make it match to the expected
    // behavior for UI calls (which is Models, eg "Cous"). We need to do this
    // before RegistryAuthComponent runs.
    $modelsName = Inflector::camelize($reqModel);
    $this->setName($modelsName);
    // Make this the default table for fetchTable()
    $this->defaultTable = $modelsName;
    $table = $this->fetchTable();
    $this->tableName = $table->getTable();
    
    // We want API auth, not Web Auth
    $this->RegistryAuth->setConfig('apiUser', true);
  }
  
  /**
   * Handle an add action for a Standard object.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function add() {
    /** var string $modelsName */
    $modelsName = $this->getName();
    /** var Cake\ORM\Table $table */
    $table = $this->getCurrentTable();
    
    $json = $this->request->getData(); // Parsed by BodyParserMiddleware
    
    if(empty($json[$modelsName])) {
      $this->llog('debug', $modelsName . " object not found in request");
      throw new BadRequestException(__d('error', 'api.object', [$modelsName]));
    }
    
    $results = [];
    
    foreach($json[$modelsName] as $rec) {
      try {
        $obj = $table->newEntity($rec);
        
        if($table->saveOrFail($obj)) {
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
    $this->viewBuilder()->setLayout('rest');
    $this->render('/Standard/api/v2/json/add-edit');
  }

  /**
   * beforeFilter callback.
   *
   * @param \Cake\Event\EventInterface $event Event.
   * @return \Cake\Http\Response|null|void
   */
  public function beforeFilter(\Cake\Event\EventInterface $event)
  {
    parent::beforeFilter($event);

    if ($this->request->is('ajax') && $this->request->is(['post', 'put'])) {
      $this->FormProtection->setConfig('validate', false);
    }
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
   * Calculate the CO ID associated with the request.
   *
   * @since  COmanage Registry v5.0.0
   * @return int      CO ID, or null if no CO contextwas found
   */

  public function calculateRequestedCOID(): ?int {
    if($this->request->getQuery('group_id') !== null) {
      $groupId = $this->request->getQuery('group_id');
      $Group = TableRegistry::getTableLocator()->get('Groups');

      $groupRecord = $Group->get($groupId);
      return $groupRecord->co_id;
    }

    return null;
  }
  
  /**
   * Handle a delete action for a Standard object.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Integer $id Object ID
   */
  
  public function delete($id) {
    /** var string $modelsName */
    $modelsName = $this->getName();
    /** var Cake\ORM\Table $table */
    $table = $this->getCurrentTable();
    // $tableName = models
    $tableName = $table->getTable();


    // Make sure the requested object exists
    try {
      $obj = $table->findById($id)->firstOrFail();

      if(method_exists($obj, "isReadOnly") && $obj->isReadOnly()) {
        throw new BadRequestException(__d('error', 'edit.readonly'));
      }
// XXX document AR-CO-1 when we implement hard delete/changelog
//     note similar logic in StandardController
      $table->deleteOrFail($obj);

      // Trigger provisioning, letting errors bubble up (AR-GMR-5)
      if(method_exists($table, "requestProvisioning")) {
        $this->llog('rule', "AR-GMR-5 Requesting provisioning for deleted entity $modelsName " . $obj->id);
        $table->requestProvisioning(id: $obj->id, context: ProvisioningContextEnum::Automatic);
      }

      // Render an empty view
      $this->viewBuilder()->setLayout('rest');
      $this->render('/Standard/api/v2/json/delete');
    }
    catch(\Exception $e) {
      // findById throws Cake\Datasource\Exception\RecordNotFoundException
      
      // Rethrow the error so it formats correctly
      throw new BadRequestException($this->exceptionToError($e));
    }
  }

  protected function dispatchIndex(string $mode = 'default') {
    // There are use cases where we will pass co_id and another model_id as a query parameter. The co_id might be
    // required for the primary link calculations while the foreign key for filtering. Since we are using the
    // most constrained identifier to calculate the co_id, we then check if the two parameters match. If not,
    // the request should fail, so as to prevent any security holes.
    if($this->request->getQuery('co_id') !== null
      && $this->getCOID() !== null
      && (int)$this->getCOID() !== (int)$this->request->getQuery('co_id')) {
      $this->llog('error', 'CO Id calculated from Group ID does not match CO Id query parameter');
      // Mask this with a generic UnauthorizedException
      throw new UnauthorizedException(__d('error', 'perm'));
    }

    /** var Cake\ORM\Table $table */
    $table = $this->getCurrentTable();

    $reqParameters = [...$this->request->getQuery()];
    $pickerMode = ($mode === 'picker');

    // Construct the Query
    $query = $this->getIndexQuery($pickerMode, $reqParameters);

    // findIndexed breaks the REST API (which doesn't pull related models),
    // so only use it in Picker Mode
    if($pickerMode && method_exists($table, 'findIndexed')) {
      $query = $table->findIndexed($query);
    }
    
    // This magically makes REST calls paginated... can use eg direction=,
    // sort=, limit=, page=
    $this->set($this->tableName, $this->paginate($query));

    // Let the view render
    $this->viewBuilder()->setLayout('rest');
    $this->render('/Standard/api/v2/json/index');
  }

  /**
   * Handle an edit action for a Standard object.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Integer $id Object ID
   */
  
  public function edit($id) {
    /** var string $modelsName */
    $modelsName = $this->getName();
    /** var Cake\ORM\Table $table */
    $table = $this->getCurrentTable();
    // $tableName = models
    $tableName = $table->getTable();

    $query = $table->findById($id);

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
      
      $obj = $table->patchEntity($obj, $json[$modelsName]);
      
      $table->saveOrFail($obj);

      // Trigger provisioning, letting errors bubble up (AR-GMR-5)
      if(method_exists($table, "requestProvisioning")) {
        $this->llog('rule', "AR-GMR-5 Requesting provisioning for $modelsName " . $obj->id);
        $table->requestProvisioning(id: $obj->id, context: ProvisioningContextEnum::Automatic);
      }

      // Let the view render
      $this->viewBuilder()->setLayout('rest');
      $this->render('/Standard/api/v2/json/add-edit');
    }
    catch(\Exception $e) {
      // findById throws Cake\Datasource\Exception\RecordNotFoundException
      
      // The default exception error isn't particularly user friendly, so
      // we dig into the entity errors and try to make a message from there.
      $err = $this->exceptionToError($e);
      
      $this->llog('debug', $err);

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
    $this->viewBuilder()->setLayout('rest');
    $this->render('/Standard/api/v2/json/add-edit');
  }
  
  /**
   * Generate an index for a set of Standard Objects.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function index() {
    $this->dispatchIndex();
  }

  /**
   * Provision an entity.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string $id Provisioning Target ID
   */
  
  public function provision(string $id) {
    // we require a provisioning target ID in order to simplify primary key lookup.
    // (To accept "all" or embed the ID into the JSON request would require custom
    // logic to map the request to a CO... possible, but more complicated.)
    
    $json = $this->request->getData(); // Parsed by BodyParserMiddleware

    if(empty($json['provisioningRequest']['entityType']) 
       || empty($json['provisioningRequest']['entityId'])) {
      throw new \InvalidArgumentException(__d('error', 'invalid.request'));
    }

    // We need to find the table for the entity type being provisioned in order to
    // call requestProvisioning() on that table. We'll indirectly validate the
    // requested entity type by checking for that function.

    $entityType = $json['provisioningRequest']['entityType'];
    $entityId = (int)$json['provisioningRequest']['entityId'];

    $Table = TableRegistry::getTableLocator()->get($entityType);

    if(!method_exists($Table, 'requestProvisioning')) {
      throw new \InvalidArgumentException(__d('error', 'invalid.request'));
    }

    $Table->requestProvisioning(
      id: $entityId,
      context: \App\Lib\Enum\ProvisioningContextEnum::Manual,
      provisioningTargetId: (int)$id
    );
    
    // Let the view render
    $this->viewBuilder()->setLayout('rest');
    $this->render('/Standard/api/v2/json/add-edit');
  }

  /**
   * Generate a view for a set of Standard Objects.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function view($id = null) {
    /** var Cake\ORM\Table $table */
    $table = $this->getCurrentTable();
    // $tableName = models
    $tableName = $table->getTable();
    $request = $this->getRequest();
    
    if(empty($id)) {
      throw new InvalidArgumentException(__d('error', 'notprov', ['id']));
    }
    
    // We allow archived records to be retrieved via the API, but only if
    // explicitly requested

    $archived = $request->getQuery('archived') === 'yes';

    $obj = $table->findById($id)->applyOptions(['archived' => $archived])->firstOrFail();
    
    $this->set($tableName, [$obj]);
    
    // Let the view render
    $this->viewBuilder()->setLayout('rest');
    $this->render('/Standard/api/v2/json/index');
  }

  /**
   * Pick a set of Standard Objects.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function pick() {
    $this->dispatchIndex(mode: 'picker');
  }

  /**
   * Indicate whether this Controller will handle some or all authnz.
   *
   * @param EventInterface $event Cake event, ie: from beforeFilter
   * @return string               "no", "open", "authz", "yes", or "notauth"
   * @since  COmanage Registry v5.2.0
   */
  public function willHandleAuth(\Cake\Event\EventInterface $event): string
  {
    $request = $this->getRequest();
    $reqAction = $request->getParam('action');
    $session = $request->getSession();
    $mode = 'no';

    $auth = $session->read('Auth');

    // Calculate people picker permissions on the fly for an enrollment flow/petition
    if(
      $this->name == 'People'
      && $reqAction == 'pick'
      && !empty($request->getQuery('petition_id'))
    ) {
      $petitionId = (int)$request->getQuery('petition_id');
      // We need to check if this is part of an Enrollment Flow
      $Petitions = $this->fetchTable('Petitions');

      // Pull the Petition to find its CO
      $petition = $Petitions->get(
        $petitionId,
        contain: ['EnrollmentFlows' => ['EnrollmentFlowSteps']]
      );

      // We need to check the Petitioner Authorization.
      $hasAuthorizedUser = $petition->enrollment_flow->authz_type == EnrollmentAuthzEnum::AuthUser
        ? !empty($auth['external']['user']) : true;

      foreach ($petition->enrollment_flow->enrollment_flow_steps as $step) {
        if ($step->plugin == 'CoreEnroller.AttributeCollectors') {
          $AttributeCollectors = $this->fetchTable('CoreEnroller.AttributeCollectors');
          $attributeCollectorsRecord =  $AttributeCollectors->find()
            ->where(['enrollment_flow_step_id' => $step->id])
            ->contain(['EnrollmentAttributes'])
            ->first();

          $mode = $hasAuthorizedUser && $attributeCollectorsRecord->enable_person_find ? 'yes' : 'no';
        }
      }
    }

    // Apply standard behavior
    return $mode;
  }
}