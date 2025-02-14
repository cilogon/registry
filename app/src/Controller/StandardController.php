<?php
/**
 * COmanage Registry Standard Controller
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

use App\Lib\Enum\ApplicationStateEnum;
use App\Lib\Traits\ApplicationStatesTrait;
use App\Lib\Traits\IndexQueryTrait;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use InvalidArgumentException;
use \App\Lib\Enum\ProvisioningContextEnum;
use \App\Lib\Enum\SuspendableStatusEnum;
use \App\Lib\Util\{StringUtilities, FunctionUtilities};
use \Cake\Http\Exception\BadRequestException;

class StandardController extends AppController {
  use IndexQueryTrait;
  use ApplicationStatesTrait;

  // Pagination defaults should be set in each controller
  public $pagination = [];

  /**
   * Handle an add action for a Standard object.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function add() {
    // $this->name = Models (ie: from ModelsTable)
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;
    // $tableName = models
    $tableName = $table->getTable();
    // Schema
    $schema = $table->getSchema();
    // Create an empty entity for FormHelper
    $obj = $table->newEmptyEntity();
    
    if($this->request->is('post')) {
      try {
        // Try to save
        $obj = $table->newEntity($this->request->getData());

        if($table->save($obj)) {
          $this->Flash->success(__d('result', 'saved'));

          // Give the controller an opportunity to set additional Flash messages
          if(method_exists($this, "setSupplementalFlash")) {
            $this->setSupplementalFlash($obj);
          }
          
          // Trigger provisioning, letting errors bubble up (AR-GMR-5)
          if(method_exists($table, "requestProvisioning")) {
            $this->llog('rule', "AR-GMR-5 Requesting provisioning for $modelsName " . $obj->id);
            $table->requestProvisioning(id: $obj->id, context: ProvisioningContextEnum::Automatic);
          }

          // If this is a Pluggable Model, instantiate the plugin and redirect
          // into the Entry Point Model
          if(!empty($obj->plugin) && method_exists($this, "instantiatePlugin")) {
            // instantiatePlugin() is implemented in StandardPluggableController
            return $this->instantiatePlugin($obj);
          }

          return $this->generateRedirect($obj);
        }
        
        $errors = $obj->getErrors();
        
        if(!empty($errors)) {
          $errorlist = [];
          $errorsParsed = Hash::flatten($errors);
          foreach ($errorsParsed as $struct => $issue) {
            $partials = explode('.', $struct);
            // Try to find the column
            $column = collection($partials)->filter(fn($partial) => $schema->getColumn($partial) !== null)->first();
            $errorlist[] = __d('error', 'flash', [$column, $issue]);
          }
          $this->Flash->error(__d('error', 'fields', $errorlist));
        } else {
          $this->Flash->error(__d('error', 'save', [$modelsName]));
        }
      }
      catch(\Exception $e) {
        // This throws \Cake\ORM\Exception\RolledbackTransactionException if
        // aborted in afterSave
        
        $this->Flash->error($e->getMessage());
      }
    }

    // Pass $obj as context so the view can render validation errors
    $this->set('vv_obj', $obj);
    
    // PrimaryLinkTrait, via AppController
    // Check if I have already calculated it up the tree of execution
    if(empty($this->cur_pl->value) && empty($this->cur_pl->attr)) {
      $this->getPrimaryLink();
    }
    // AutoViewVarsTrait, via AppController
    $this->populateAutoViewVars();
    
    // Default title is add new object
    [$title, $supertitle, $subtitle] = StringUtilities::entityAndActionToTitle($obj, $modelsName, 'add');
    $this->set('vv_title', $title);
    $this->set('vv_supertitle', $supertitle);
    $this->set('vv_subtitle', $subtitle);

    // Let the view render
    $this->render('/Standard/add-edit-view');
  }
  
  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */

  public function beforeFilter(\Cake\Event\EventInterface $event) {
    if(!$this->request->is('restful')) {
      // Provide additional hints to BreadcrumbsComponent. This needs to be here
      // and not in beforeRender because the component beforeRender will run first.

      $primaryLink = $this->getPrimaryLink(true);

      if(!empty($primaryLink->attr) && $primaryLink->attr != 'co_id') {
        // eg: EnrollmentFlowSteps -> EnrollmentFlow, JobHistoryRecords -> Job, etc
        $this->Breadcrumb->injectPrimaryLink($primaryLink);
      }
    }

    return parent::beforeFilter($event);
  }

  /**
   * Standard operations before the view is rendered.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface      $event BeforeRender event
   * @return \Cake\Http\Response        HTTP Response
   */
  
// XXX can we merge calls ot (eg) getPrimaryLink and populateAutoViewVars here?
  public function beforeRender(\Cake\Event\EventInterface $event) {
    // $this->name = Models (ie: from ModelsTable)
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;
    
    // Provide some hints to the views
    if($this->request->getParam('action') != 'deleted') {
      $this->getFieldTypes();
      $this->getRequiredFields();
    }
    
    // Set the display field as a view var to make it available to the views
    $this->set('vv_display_field', $table->getDisplayField());
    
    // Populate permissions info, which uses the requested object ID if one
    // was provided. As a first approximation, those actions that permit lookup
    // primary link are also those that pass an $id that can be used to establish
    // permissions. We also allow any action for models without primary links
    // (eg Cos, Plugins, and TrafficDetours).
    
    $id = null;
    
    $params = $this->request->getParam('pass');
    
    if(!empty($params[0])) {
      if(!method_exists($table, "allowLookupPrimaryLink")
         || $table->allowLookupPrimaryLink($this->request->getParam('action'))) {
        $id = (int)$params[0];
      }
    }
    
    $this->set('vv_permissions', $this->RegistryAuth->calculatePermissionsForView($this->request->getParam('action'), $id));
    
    // The template path may vary if we're in a plugin context
    $vv_template_path = ROOT . DS . "templates" . DS . $modelsName;

    if(!empty($this->getPlugin())) {
      $vv_template_path = $this->getPluginPath($this->getPlugin(), "templates") . DS . $modelsName;
    }

    $this->set('vv_template_path', $vv_template_path);

    // Primarily of interest to detailed record views, if this attribute supports
    // Pipeline sourcing (ie: has a source_foo_id field) set the name of the source
    // foreign key into a view var since it's not always calculable.
    if(method_exists($table, 'sourceForeignKey')) {
      $this->set('vv_source_fk', $table->sourceForeignKey());
    }

    // Check to see if the model names a specific layout
    if(method_exists($table, 'getLayout')) {
      $this->viewBuilder()->setLayout($table->getLayout($this->request->getParam('action')));
    }

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
    // $table = the actual table object
    $table = $this->$modelsName;
    
    // Allow a delete via a POST or DELETE
    $this->request->allowMethod(['post', 'delete']);
    
    // Make sure the requested object exists
    try {
      $obj = $table->findById($id)->firstOrFail();
      
// XXX throw 404 on RESTful not found?
      // By default, a delete is a soft delete. The exceptions are when
      // deleting a CO (AR-CO-1) or when an expunge flag is passed and
      // expunge is enabled within the CO (XXX not yet implemented).

      $useHardDelete = ($modelsName == "Cos");

      $table->deleteOrFail($obj, ['useHardDelete' => $useHardDelete]);
      
      // Use the display field to generate the flash message
      
      $field = $table->getDisplayField();
      
      if(!empty($obj->$field)) {
        $this->Flash->success(__d('result', 'deleted.a', [$obj->$field]));
      } else {
        $this->Flash->success(__d('result', 'deleted'));
      }
      
      // Trigger provisioning, letting errors bubble up (AR-GMR-5)
      // In general, tables should check that they were passed a deleted
      // record and martial data/set eligibility appropriately
      if(method_exists($table, "requestProvisioning")) {
        $this->llog('rule', "AR-GMR-5 Requesting provisioning for deleted entity $modelsName " . $obj->id);
        $table->requestProvisioning(id: (int)$id, context: ProvisioningContextEnum::Automatic);
      }

      // Return to index since there is no delete view
      return $this->generateRedirect(null);
    }
    catch(\Cake\ORM\Exception\PersistenceFailedException $e) {
      // deleteOrFail throws Cake\ORM\Exception\PersistenceFailedException
      
      // Application Rules that apply to the entity as a whole (or more than
      // one field) can use "id" as their errorField, and we'll catch that here.
      
      $errors = $obj->getErrors();
      
      if(!empty($errors['id'])) {
        $this->Flash->error(implode(',', array_values($errors['id'])));
      } else {
        $this->Flash->error($e->getMessage());
      }
    }
    catch(\Exception $e) {
      // findById throws Cake\Datasource\Exception\RecordNotFoundException
      $errors = $obj->getErrors();
      
      if(!empty($errors)) {
        // Format is [field => [rule => error]]
        $errstr = "";
        
        foreach($errors as $f => $r) {
          foreach($r as $rule => $err) {
            $errstr .= $err . ",";
          }
        }
        
        $this->Flash->error(rtrim($errstr, ","));
      } else {
        $this->Flash->error($e->getMessage());
      }
    }
    
    // The record is still valid, so redirect back to it
    return $this->redirect(['action' => 'edit', $id]);
  }
  
  /**
   * Handle a deleted action for a Standard object.
   *
   * @since  COmanage Registry v5.0.0
   */
  
  public function deleted() {
    // Set the title when not set at the individual controller
    if(empty($this->viewBuilder()->getVar('vv_title'))) {
      $modelsName = $this->name;
      $fieldName = Inflector::singularize($modelsName);
      if(__d('result', $fieldName . '.deleted') != $fieldName . '.deleted') {
        // Use the standard (singular) deleted message for the field when it exists
        $this->set('vv_title', __d('result', $fieldName . '.deleted'));
      } else {
        // Build the result from the generic 'deleted.a' language key
        $this->set('vv_title', __d('result', 'deleted.a', [$fieldName])); 
      }
    }
    // Set the target window when not set at the individual controller.
    // This should be 'self' (default) or 'top'. 
    if (empty($this->viewBuilder()->getVar('vv_target_window'))) {
      $this->set('vv_target_window', 'self');
    }
    // Render the view
    $this->render('/Standard/deleted');
  }
  
  /**
   * Handle an edit action for a Standard object.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id Object ID
   */
  
  public function edit(string $id) {
    // $this->name = Models (ie: from ModelsTable)
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;
    // $tableName = models
    $tableName = $table->getTable();
    
    // We use findById() rather than get() so we can apply subsequent
    // query modifications via traits
    $query = $table->findById($id);
    
    // QueryModificationTrait
    if(method_exists($this->$modelsName, "getEditContains")) {
      $query = $query->contain($this->$modelsName->getEditContains());
    }
    
    try {
      // Pull the current record
      $obj = $query->firstOrFail();
      
      if(method_exists($obj, "isReadOnly")) {
        // If this is a read only record, redirect to view
        if($obj->isReadOnly()) {
          $redirect = [
            'action' => 'view',
            $obj->id
          ];
          
          return $this->redirect($redirect);
        }
      }
      
      if($this->request->is(['post', 'put'])) {
        // This is an update request
        $opts = [];
        
        // AssociationTrait
        /*
        if(method_exists($table, "getPatchAssociated")) {
          $opts['associated'] = $table->getPatchAssociated();
        }*/
        
        // $obj will have whatever editContains also pulled, but we don't want
        // to save all that stuff by default, so we'll pull a new copy of the
        // object without the associated data.
        $saveObj = $table->findById($id)->firstOrFail();

        try{
          // Attempt the update the record
          $table->patchEntity($saveObj, $this->request->getData(), $opts); 
          
          // This throws \Cake\ORM\Exception\RolledbackTransactionException if aborted
          // in afterSave
          if($table->save($saveObj)) {
            $this->Flash->success(__d('result', 'saved'));
            
            // Give the controller an opportunity to set additional Flash messages
            if(method_exists($this, "setSupplementalFlash")) {
              $this->setSupplementalFlash($obj);
            }
            
            // Trigger provisioning, letting errors bubble up (AR-GMR-5)
            if(method_exists($table, "requestProvisioning")) {
              $this->llog('rule', "AR-GMR-5 Requesting provisioning for $modelsName " . $obj->id);
              $table->requestProvisioning(id: (int)$id, context: ProvisioningContextEnum::Automatic);
            }

            return $this->generateRedirect($saveObj); 
          } else {
            $errors = $saveObj->getErrors();
          }
        } catch(\Exception $e) {
          $errors = [0 => ['exception' => $e->getMessage()]];
        }
        
        if(!empty($errors)) {
          $this->Flash->error(__d('error', 'fields', [ implode(',', 
                                                                 array_map(function($v) use ($errors) { 
                                                                             return __d('error', 'flash', [$v, implode(',', array_values($errors[$v]))]);
                                                                           },
                                                                           array_keys($errors))) ]));
        } else {
          $this->Flash->error(__d('error', 'save', [$modelsName]));
        }
      }
    }
    catch(\Exception $e) {
      // findById throws Cake\Datasource\Exception\RecordNotFoundException
      $this->Flash->error($e->getMessage());
      return $this->generateRedirect(null);
    }
    
    $this->set('vv_obj', $obj);
    // XXX should we also set '$model'? cake seems to autopopulate edit fields just fine without it
    //     note index() uses $tableName, not 'vv_objs' or event 'vv_table_name'
    
    // PrimaryLinkTrait
    $this->getPrimaryLink();
    
    // AutoViewVarsTrait
    $this->populateAutoViewVars($obj);

    // Calculate and set title, supertitle and subtitle
    [$title, $supertitle, $subtitle] = StringUtilities::entityAndActionToTitle($obj, $modelsName, 'edit');

    // We might have calculated the following values earlier. For example, MVEAController runs before the StandarController
    // and makes similar calculations. We will keep the ones calculated before we get here
    if ($this->viewBuilder()->getVar('vv_title') === null) {
      $this->set('vv_title', $title);
    }
    if ($this->viewBuilder()->getVar('vv_supertitle') === null) {
      $this->set('vv_supertitle', $supertitle);
    }
    if ($this->viewBuilder()->getVar('vv_subtitle') === null) {
      $this->set('vv_subtitle', $subtitle);
    }

    // Let the view render
    $this->render('/Standard/add-edit-view');
  }
  
  /**
   * Generate a redirect for a Standard Object operation.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity   Entity to redirect to
   * @return \Cake\Http\Response
   */
  
  public function generateRedirect($entity) {
    $redirect = [];
    
    // By default we return to the index, but we'll also accept "self" or "primaryLink".
    $redirectGoal = $this->getRedirectGoal($this->request->getParam('action'));
    
    if(!$redirectGoal) {
      // Our default behavior is index unless we're in a plugin context

      if(!empty($this->getPlugin())) {
        $redirectGoal = 'pluggableLink';
      } else {
        $redirectGoal = 'index';
      }
    }
    
    if($redirectGoal == 'deleted') {
      // Immediately redirect to the (mostly blank) deleted view
      return $this->redirect(['action' => 'deleted']);
    } elseif($redirectGoal == 'self'
       && $entity
       && in_array($this->request->getParam('action'), ['add', 'copy', 'edit'])) {
      // We typically want to redirect to the edit view of the record,
      // but in some cases (eg: if the record was just frozen) we want to
      // redirect to "view" instead.
      
      $readOnly = false;

      if(method_exists($entity, "isReadOnly")) {
        $readOnly = $entity->isReadOnly();
      }

      $redirect = [
        'action' => $readOnly ? "view" : "edit",
        $entity->id
      ];
    } elseif($redirectGoal == 'pluggableLink' || $redirectGoal == 'primaryLink') {
      // pluggableLink and primaryLink do basically the same thing, except that
      // pluggableLink checks for special handling of the 'plugin' parameter
      $link = $this->getPrimaryLink(true);
      
      if(!empty($link->attr) && !empty($link->value)) {
        $redirect = [
          'controller' => StringUtilities::foreignKeyToClassName($link->attr),
          'action' => 'edit',
          $link->value
        ];

        if($redirectGoal == 'pluggableLink') {
          // If the primary link points to a plugin, we want to redirect
          // into that plugin, otherwise the core code
          $redirect['plugin'] = $link->plugin ?? null;
        }
      }
    } elseif($redirectGoal == 'special') {
      // The controller will implement a special calculation

      $redirect = $this->calculateRedirectTarget($entity);
    } else {
      // Default is to redirect to the index view
      $redirect = ['action' => 'index'];
      
      $link = $this->getPrimaryLink(true);
      
      if(!empty($link->attr) && !empty($link->value)) {
        $redirect['?'] = [$link->attr => $link->value];
      }

      if(!empty($this->getPlugin())) {
        $redirect['plugin'] = $this->getPlugin();
      }
    }

    return $this->redirect($redirect);
  }
  
  /**
   * Make a list of fields types suitable for FieldHelper
   * 
   * @since  COmanage Registry v5.0.0
   */

  protected function getFieldTypes() {
    // $this->name = Models (ie: from ModelsTable)
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;

    $schema = $table->getSchema();

    // We don't pass the schema object as is, partly because cake might change it
    // and partly to simplify access to the parts the views (FieldHelper, really)
    // actually need.

    // Note the schema does have field lengths for strings, but typeMap
    // doesn't return them and we're not doing anything with them at the moment.
    $this->set('vv_field_types', $schema->typeMap());
  }

  /**
   * Build a list of required fields suitable for FieldHelper
   *
   * @since  COmanage Registry v5.0.0
   */
  
  protected function getRequiredFields() {
    // $this->name = Models (ie: from ModelsTable)
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;
    
    // Build a list of required fields for FieldHelper
    $reqFields = [];
    
    $validator = $table->getValidator();
    $fields = $validator->getIterator();
    
    foreach($fields as $name => $cfg) {
      if(!$validator->isEmptyAllowed($name, ($this->request->getParam('action') == 'add'))) {
        $reqFields[] = $name;
      }
    }
    
    $this->set('vv_required_fields', $reqFields);
  }
  
  /**
   * Generate an index for a set of Standard Objects.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function index() {
    // $this->name = Models
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;
    // $tableName = models
    $tableName = $table->getTable();
    // Construct the Query
    $query = $this->getIndexQuery();

    if(method_exists($table, 'findIndexed')) {
      $query = $table->findIndexed($query);
    }

    // Fetch the data and paginate
    $paginationLimit = $this->getValue(ApplicationStateEnum::PaginationLimit, DEF_SEARCH_LIMIT);
    $resultSet = $this->paginate($query, [
      'limit' => (int)$paginationLimit
    ]);

    // Pass vars to the View
    $this->set($tableName, $resultSet);
    $this->set('vv_permission_set', $this->RegistryAuth->calculatePermissionsForResultSet($resultSet));
    // AutoViewVarsTrait
    $this->populateAutoViewVars();

    // Default index view title is model name
    [$title, , ] = StringUtilities::entityAndActionToTitle($resultSet, $modelsName, 'index');
    $this->set('vv_title', $title);
    
    // Let the view render
    $this->render('/Standard/index');
  }

  /**
   * Populate any auto view variables, as requested via AutoViewVarsTrait.
   *
   * @since  COmanage Registry v5.0.0
   * @param  object $obj Current object (eg: from edit), if set
   */

  protected function populateAutoViewVars(object $obj=null) {
    // $this->name = Models
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;

    // AutoViewVarsTrait
    if(method_exists($table, 'getAutoViewVars') && $table->getAutoViewVars()) {
      foreach ($table->calculateAutoViewVars($this->getCOID(), $obj) as $vvar => $value) {
        $this->set($vvar, $value);
      }
    }
  }

  /**
   * Handle a provisioning request for a Standard object.  
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $id Object ID
   */

  public function provision($id) {
    // $this->name = Models
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;
    // $tableName = models
    $tableName = $table->getTable();

    // Note that only Primary Models support provisioning, but those that
    // don't won't have permission to execute this function.
    
    try {
      $table->requestProvisioning(
        id: (int)$id,
        context: ProvisioningContextEnum::Manual,
        provisioningTargetId: (int)$this->getRequest()->getQuery('provisioning_target_id')
      );
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    // We don't render any flash messages since they could get complex
    // depending on what was provisioned, so instead we redirect into the
    // provisioning status index for the object.
    // Redirect to the provisioning status view

    $redirect = [
      'controller' => 'ProvisioningTargets',
      'action' => 'status',
      '?' => [
        StringUtilities::tableToForeignKey($table) => $id
      ]
    ];

    return $this->redirect($redirect);
  }

  /**
   * Unfreeze a frozen record.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $id Entity ID
   */

  public function unfreeze($id) {
    // $this->name = Models
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;

    try {
      // Pull the current record
      $obj = $table->get((int)$id);
    }
    catch(\Exception $e) {
      // findById throws Cake\Datasource\Exception\RecordNotFoundException
      $this->Flash->error($e->getMessage());
      return $this->generateRedirect(null);
    }

    // Normally we'd wrap this in a function on the table or entity, but
    // it's such a simple change that it doesn't seem to be worth it atm.
    $obj->frozen = false;
    $table->save($obj);

    return $this->generateRedirect($obj);
  }

  /**
   * Handle a view action for a Standard object.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id Object ID
   */
  
  public function view($id = null) {
    // $this->name = Models
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;
    // $tableName = models
    $tableName = $table->getTable();
    
    // We use findById() rather than get() so we can apply subsequent
    // query modifications via traits
    $query = $table->findById($id);
    
    // QueryModificationTrait
    if(method_exists($table, "getViewContains")) {
      $query = $query->contain($table->getViewContains());
    }
    
    try {
      // Pull the current record
      $obj = $query->firstOrFail();
    }
    catch(\Exception $e) {
      // findById throws Cake\Datasource\Exception\RecordNotFoundException
      $this->Flash->error($e->getMessage());
      return $this->generateRedirect(null);
    }
    
    $this->set('vv_obj', $obj);
    
    // PrimaryLinkTrait
    $this->getPrimaryLink();
    
    // AutoViewVarsTrait
    // We still used this in view() to map select values
    $this->populateAutoViewVars($obj);

    // Calculate and set title, supertitle and subtitle
    [$title, $supertitle, $subtitle] = StringUtilities::entityAndActionToTitle($obj, $modelsName, 'view');

    $this->set('vv_title', $title);
    $this->set('vv_supertitle', $supertitle);
    $this->set('vv_subtitle', $subtitle);

    // Let the view render
    $this->render('/Standard/add-edit-view');
  }
}