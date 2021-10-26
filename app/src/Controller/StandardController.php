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

use InvalidArgumentException;
use \Cake\Http\Exception\BadRequestException;

class StandardController extends AppController {
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
    
    if($this->request->is('post')) {
      // Try to save
      $obj = $table->newEntity($this->request->getData());
      
      // This throws \Cake\ORM\Exception\RolledbackTransactionException if aborted
      // in afterSave
      if($table->save($obj)) {
        $this->Flash->success(__('registry.rs.saved'));
        
        return $this->generateRedirect(null);
      }
      
      $errors = $obj->getErrors();
      
      if(!empty($errors)) {
        $this->Flash->error(__('registry.er.fields', [ implode(',', 
                                                               array_map(function($v) { return __('registry.fd.'.$v); },
                                                                         array_keys($errors))) ]));
      } else {
        $this->Flash->error(__('registry.er.save', [$modelsName]));
      }
      
      // Pass $obj as context so the view can render validation errors
      $this->set('vv_obj', $obj);
    } else {
      // Create an empty entity for FormHelper
      
      $this->set('vv_obj', $table->newEmptyEntity());
    }
    
    // PrimaryLinkTrait
    $this->getPrimaryLink();
    
    // AutoViewVarsTrait
    $this->populateAutoViewVars();
    
    // Default title is add new object
    $this->set('vv_title', __('registry.op.add.a', __('registry.ct.'.$modelsName, [1])));

    // Let the view render
    $this->render('/Standard/add-edit-view');
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
    
    $this->getRequiredFields();
    
    // Set the display field as a view var to make it available to the views
    $this->set('vv_display_field', $table->getDisplayField());
    
    // Populate permissions info, which uses the requested object ID if one
    // was provided. As a first approximation, those actions that permit lookup
    // primary link are also those that pass an $id that can be used to establish
    // permissions, and also Cos (which has no primary link).
    
    $id = null;
    
    $params = $this->request->getParam('pass');
    
    if(!empty($params[0])) {
      if((method_exists($table, "getPrimaryLink")
          && $table->allowLookupPrimaryLink($this->request->getParam('action')))
         ||
         $modelsName == 'Cos') {
        $id = (int)$params[0];
      }
    }
    
    $this->set('vv_permissions', $this->RegistryAuth->calculatePermissionsForView($this->request->getParam('action'), $id));
    
    return parent::beforeRender($event);
  }
  
  /**
   * Default implementation for calculating permissions for standard controllers,
   * intended to be overridden by controllers with more speciific requirements.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int   $id Record ID if relevant, or null
   * @return array     Array of permissions
   */
  
  public function calculatePermissions(?int $id): array {
    $ret = [];
    
    // $this->name = Models (ie: from ModelsTable)
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;
    
    // Do we have an authenticated user?
    $authenticatedUser = (bool)$this->RegistryAuth->getAuthenticatedUser();

    // Is this user a Platform Administrator?
    $platformAdmin = $this->RegistryAuth->isPlatformAdmin();
    
    // Is this user a CO Administrator?
    $coAdmin = $this->RegistryAuth->isCoAdmin($this->getCOID());
    
    // Is this record read only?
    $readOnly = false;

    if($id) {
      $readOnlyActions = ['view'];
      
      // Does this table have an isReadOnly call?
      
      if(method_exists($table, "isReadOnly")) {
        // Pull the record so we can interrogate it
        
        $obj = $table->get($id);
        
        $readOnly = $table->isReadOnly($obj);
        
        if(!empty($this->permissions['readOnly'])) {
          // Merge in controller specific actions permitted on read only entities
          $readOnlyActions = array_merge($readOnlyActions, $this->permissions['readOnly']);
        }
      }
      
      // Permissions for actions that operate over individual entities
      
      foreach($this->permissions['entity'] as $action => $roles) {
        $ok = false;
        
        if(!$readOnly || in_array($action, $readOnlyActions)) {
          foreach($roles as $role) {
            // eg: $role = "platformAdmin", which corresponds to the variables set, above
            if($$role) {
              $ok = true;
              break;
            }
          }
        }

        $ret[$action] = $ok;
      }
    } else {
      // Permissions for actions that operate over tables
      
      foreach($this->permissions['table'] as $action => $roles) {
        $ok = false;
        
        foreach($roles as $role) {
          // eg: $role = "platformAdmin", which corresponds to the variables set, above
          if($$role) {
            $ok = true;
            break;
          }
        }
        
        $ret[$action] = $ok;
      }
    }
    
    return $ret;
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
// XXX document AR-CO-1 when we implement hard delete/changelog
      $table->deleteOrFail($obj);
      
      // Use the display field to generate the flash message
      
      $field = $table->getDisplayField();
      
      if(!empty($obj->$field)) {
        $this->Flash->success(__('registry.rs.deleted.a', [$obj->$field]));
      } else {
        $this->Flash->success(__('registry.rs.deleted'));
      }
      
      // Return to index since there is no delete view
      return $this->generateRedirect(null);
    }
    catch(\Cake\ORM\Exception\PersistenceFailedException $e) {
      // deleteOrFail throws Cake\ORM\Exception\PersistenceFailedException
      $this->Flash->error($e->getMessage());
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
   * Handle an edit action for a Standard object.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Integer $id Object ID
   */
  
  public function edit($id) {
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
      
      if(method_exists($table, "isReadOnly")) {
        // If this is a read only record, redirect to view
        if($table->isReadOnly($obj)) {
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
        
        // Attempt the update the record
        $table->patchEntity($obj, $this->request->getData(), $opts); 
        
        // This throws \Cake\ORM\Exception\RolledbackTransactionException if aborted
        // in afterSave
        if($table->save($obj)) {
          $this->Flash->success(__('registry.rs.saved'));
          
          return $this->generateRedirect($obj->id); 
        }
        
        $errors = $obj->getErrors();
        
        if(!empty($errors)) {
          $this->Flash->error(__('registry.er.fields', [ implode(',', 
                                                                 array_map(function($v) { return __('registry.fd.'.$v); },
                                                                           array_keys($errors))) ]));
        } else {
          $this->Flash->error(__('registry.er.save', [$modelsName]));
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
    
    // Default view title is edit object display field
    $field = $table->getDisplayField();
    
    if(!empty($obj->$field)) {
      $this->set('vv_title', __('registry.op.edit.a', $obj->$field));
    } else {
      $this->set('vv_title', __('registry.op.edit.a', __('registry.ct.'.$modelsName, [1])));
    }
    
    // Let the view render
    $this->render('/Standard/add-edit-view');
  }
  
  /**
   * Generate a redirect for a Standard Object operation.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int $id ID of object to redirect to
   * @return \Cake\Http\Response
   */
  
  public function generateRedirect(?int $id) {
    $redirect = [];
    
    if(in_array($this->request->getParam('action'), ['add', 'edit']) && $id) {
      // Redirect to the edit view of the record just added
      // (if the user has add permission, they probably have edit permission)
      
      $redirect = [
        'action' => 'edit',
        $id
      ];
    } else {
      // Default is to redirect to the index view
      $redirect = ['action' => 'index'];
      
      $link = $this->getPrimaryLink(true);
      
      if(!empty($link->attr) && !empty($link->value)) {
        $redirect['?'] = [$link->attr => $link->value];
      }
    }
    
    return $this->redirect($redirect);
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

    $query = null;
    
    // PrimaryLinkTrait
    $link = $this->getPrimaryLink(true);
    
    // AutoViewVarsTrait
    $this->populateAutoViewVars();
    
    if(!empty($link->attr)) {
      // If a link attribute is defined but no value is provided, then query
      // where the link attribute is NULL
      $query = $table->find()->where([$link->attr => $link->value]);
    } else {
      $query = $table->find();
    }
    
    // QueryModificationTrait
    if(method_exists($table, "getIndexContains")
       && $table->getIndexContains()) {
      $query->contain($table->getIndexContains());
    }
    
    // The Cake documents describe $this->paginate (which worked in Cake 2),
    // but it doesn't seem to work in Cake 4. So we just use $this->pagination
    // ourselves here.
    $resultSet = $this->Paginator->paginate($query, $this->pagination);
    
    $this->set($tableName, $resultSet);
    $this->set('vv_permission_set', $this->RegistryAuth->calculatePermissionsForResultSet($resultSet));
    
    // Default index view title is model name
    $this->set('vv_title', __('registry.ct.'.$modelsName, [99]));
    
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
    
    // Populate certain view vars (eg: selects) automatically.
    
    // AutoViewVarsTrait
    if(method_exists($table, "getAutoViewVars")
       && $table->getAutoViewVars()) {
      foreach($table->getAutoViewVars() as $vvar => $avv) {
        switch($avv['type']) {
          case 'array':
            // Use the provided array of values. By default, we use the values
            // for the keys as well, to generate HTML along the lines of
            // <option value="Foo">"Foo"</option>
            $this->set($vvar, array_combine($avv['array'], $avv['array']));
            break;
          case 'enum':
            // We just want the localized text strings for the defined constants
            $class = '\\App\\Lib\\Enum\\'.$avv['class'];
            $this->set($vvar, $class::getLocalizedConsts());
            break;
          // "auxiliary" and "select" do basically the same thing, but the former
          // returns the full object and the latter just returns a hash suitable
          // for a select. "type" is a shorthand for "select" for type_id.
          case 'type':
            // Inject configuration
            $avv['model'] = 'Types';
            // We assume the model using type_id has a primary link of co_id
            $avv['find'] = 'filterPrimaryLink';
          case 'auxiliary':
// XXX add list as in match?
          case 'select':
            // We assume $modelName has a direct relationship to $avv['model']
            $avvmodel = $avv['model'];
            $this->loadModel($avvmodel);
            
            if($avv['type'] == 'auxiliary') {
              $query = $this->$avvmodel->find();
            } else {
              $query = $this->$avvmodel->find('list');
            }
            
            if(!empty($avv['find'])) {
              if($avv['find'] == 'filterPrimaryLink') {
                // We're filtering the requested model, not our current model.
                // See if the requested key is available, and if so run the find.
                
                $linkFilter = $table->getPrimaryLink();
                
                if($linkFilter) {
                  // Try to find the $linkFilter value
                  $v = null;
                  
                  // We might have been passed an object with the current value
                  if($obj && !empty($obj->$linkFilter)) {
                    $v = $obj->$linkFilter;
                  } elseif(!empty($this->request->getQuery($linkFilter))) {
                    $v = $this->request->getQuery($linkFilter);
                  }
// XXX also need to check getData()?
// XXX shouldn't this use $this->getPrimaryLink() instead? Or maybe move $this->primaryLink
//     to PrimaryLinkTrait and call it there?
                  
                  if($v) {
                    $query = $query->where([$linkFilter => $v]);
                  }
                }
              } else {
                // Use the specified finder, if configured
                $query = $query->find($avv['find']);
              }
            }
            
            if(!empty($avv['where'])) {
              // Filter on the specified clause (of the form [column=>value])
              $query = $query->where($avv['where']);
            }
            
            $this->set($vvar, $query->toArray());
            break;
          default:
// XXX I18n? and in match?
            throw new \LogicException('Unknonwn Auto View Var Type {0}', [$avv['type']]);
            break;
        }
      }
    }    
  }

  /**
   * Handle a view action for a Standard object.
   *
   * @since  COmanage Registry v6.0.0
   * @param  Integer $id Object ID
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
    
    // AssociationTrait
/*
    if(method_exists($table, "getViewContains")) {
      $query = $query->contain($table->getViewContains());
    }*/
    
    try {
      // Pull the current record
      $obj = $query->firstOrFail();
    }
    catch(\Exception $e) {
      // findById throws Cake\Datasource\Exception\RecordNotFoundException
      
      $this->Flash->error($e->getMessage());
      return $this->generateRedirect();
    }
    
    $this->set('vv_obj', $obj);
    
    // PrimaryLinkTrait
    $this->getPrimaryLink();
    
    // AutoViewVarsTrait
    // We still used this in view() to map select values
    $this->populateAutoViewVars($obj);
    
    // Default view title is view object display field
    $field = $table->getDisplayField();
    
    if(!empty($obj->$field)) {
      $this->set('vv_title', __('registry.op.view.a', $obj->$field));
    } else {
      $this->set('vv_title', __('registry.op.view.a', __('registry.ct.'.$modelsName, [1])));
    }
    
    // Let the view render
    $this->render('/Standard/add-edit-view');
  }
}