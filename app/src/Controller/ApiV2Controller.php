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
use \App\Lib\Enum\PeoplePickerAuthzEnum;
use \App\Lib\Enum\ProvisioningContextEnum;
use \App\Lib\Enum\SuspendableStatusEnum;
use \App\Lib\Util\SearchUtilities;

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
    if($this->getRequest()->getParam('action') == 'pick') {
      // The People Picker requires special logic to resolve the CO,
      // depending on how the Picker is called. Note at most one
      // query key may be provided, providing more than one is an error.

      // We currently support the following contexts:
      // (1) Sponsor/Manager on Person Role (uses default URL)
      //     /people/pick?co_id=x&q=y
      // (2) Group Member Picker
      //     /people/pick?group_id=x&q=y
      // (3) Enrollment Flow Pickers, via Person Selector or Attribute Collector
      //     While these provide petition_id, strictly speaking they could just use co_id
      //     /people/pick?petition_id=x&q=y

      $groupId = (int)$this->getRequest()->getQuery('group_id');
      $petitionId = (int)$this->getRequest()->getQuery('petition_id');

      if($groupId && $petitionId) {
        $this->llog('error', "Person Picker called with invalid arguments");
        throw new BadRequestException(__d('error', 'invalid.request'));
      }

      if($groupId) {
        $Groups = TableRegistry::getTableLocator()->get('Groups');

        return $Groups->findCoForRecord($groupId);
      } elseif($petitionId) {
        $Petitions = TableRegistry::getTableLocator()->get('Petitions');

        return $Petitions->findCoForRecord($petitionId);
      } else {
        // If there is no other key that we need to look up, we accept an unkeyed CO ID

        return (int)$this->getRequest()->getQuery('co_id');
      }
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
   * Pick a set of Standard Objects.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function pick() {
    // CFM-524 Refactored to use Global Search backends. Leaving this here commented out
    // for historical purposes since the previous code has not been un-refactored.
    // $this->dispatchIndex(mode: 'picker');

    $q = $this->getRequest()->getQuery('q');

    $coId = $this->getCOID();
    $groupId = $this->getRequest()->getQuery('group_id');

    $ret = [];

    // We require at least two characters to complete a search.
    // The frontend can further restrict this before.

    if((int)$coId > 0 && strlen($q) >= 2) {
      // We don't currently validate CO ID since the authorization checks should have
      // done that already

      $ret = SearchUtilities::pickerSearch(
        (int)$coId,
        (string)$q,
        $groupId ? (int)$groupId : null
      );
    }

    $this->set('people', $ret);

    // Let the view render
    $this->viewBuilder()->setLayout('rest');
    $this->render('/Standard/api/v2/json/index');
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
      context: ProvisioningContextEnum::Manual,
      provisioningTargetId: (int)$id
    );
    
    // Let the view render
    $this->viewBuilder()->setLayout('rest');
    $this->render('/Standard/api/v2/json/add-edit');
  }

  /**
   * Record a Terms and Conditions Agreement.
   * 
   * @since  COmange Registry v5.3.0
   * @param  string $id       Terms And Conditions ID
   * @param  bool   $trusted  If true, allow the request to assert Actor and Subject
   */

  public function recordTAndC(string $id, bool $trusted=false) {
    // We operate on TermsAndConditions rather than TAndCAgreements
    // for consistency with the UI.

    $coId = $this->getCOID();

    if(!$coId) {
      throw new \InvalidArgumentException(__d('error', 'invalid.request'));
    }

    $actorPersonId = null;
    $subjectPersonId = null;
    $identifier = null;

    $json = $this->request->getData(); // Parsed by BodyParserMiddleware

    if($trusted) {
      // The client may (must) specify both the actor and the subject

      if(empty($json['personId']) || empty($json['actorPersonId']) || empty($json['identifier'])) {
        throw new \InvalidArgumentException(__d('error', 'invalid.request'));
      }

      $actorPersonId = $json['actorPersonId'];
      $subjectPersonId = $json['personId'];
      $identifier = $json['identifier'];
    } else {
      // For use with the AJAX API, if the current user is a CO Admin the subject may be
      // specified. Otherwise nothing may be specified.

      $identifier = $this->RegistryAuth->getAuthenticatedUser();
      $actorPersonId = $this->RegistryAuth->getPersonID($coId);

      if($this->RegistryAuth->isCoAdmin($coId) && !empty($json['personId'])) {
        // Only CO Admins may agree on behalf of other People
        $subjectPersonId = $json['personId'];
      } else {
        $subjectPersonId = $actorPersonId;
      }
    }

    $TAndCAgreements = TableRegistry::getTableLocator()->get('TAndCAgreements');

    $agreement = $TAndCAgreements->record(
      termsAndConditionsId: (int)$id,
      personId: $subjectPersonId,
      actorPersonId: $actorPersonId,
      identifier: $identifier
    );

    $TAndCAgreements->People->requestProvisioning(
      id: $subjectPersonId,
      context: ProvisioningContextEnum::Automatic
    );

    $this->set('vv_results', ['id' => $agreement->id]);

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
    $Table = $this->fetchTable();

    // Default behavior is to not handle Auth
    $mode = 'no';

    $auth = $session->read('Auth');

    if(
      $this->name == 'People'
      && $reqAction == 'pick'
    ) {
      // While this check is primarily intended for the Attribute Collection
      // steps of an Enrollment Flow, we use a global configuration because
      // (1) it's simpler to implement and (2) once any part of the application
      // requires an unathenticated People Picker, it is effectively available
      // for the entire CO regardless of context.

      $coId = $this->getCOID();

      if($coId) {
        $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');

        $settings = $CoSettings->find()->where(['co_id' => $coId])->firstOrFail();

        switch($settings->person_picker_authz) {
          case PeoplePickerAuthzEnum::AuthUser:
            if(!empty($auth['external']['user'])) {
              return 'yes';
            }
            break;
          case PeoplePickerAuthzEnum::CoAdmin:
            if($this->RegistryAuth->isCoAdmin($coId)
               || $this->RegistryAuth->isPlatformAdmin()) {
              return 'yes';
            }
            break;
          case PeoplePickerAuthzEnum::CoOrCouAdmin:
            if($this->RegistryAuth->isCoOrCouAdmin($coId)
               || $this->RegistryAuth->isPlatformAdmin()) {
              return 'yes';
            }
            break;
          case PeoplePickerAuthzEnum::None:
            // Person Picker is open, eg for unauthenticated enrollments
            return 'open';
            break;
          case PeoplePickerAuthzEnum::Person:
            if($this->RegistryAuth->isCoMember($coId)
               || $this->RegistryAuth->isPlatformAdmin()) {
              return 'yes';
            }
            break;
        }

        $this->llog('debug', "Person Picker called with insufficient permissions");
        return 'notauth';
      }
    }

    // Apply standard behavior
    return $mode;
  }
}