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
use \App\Lib\Enum\SuspendableStatusEnum;

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
      try {
        // Try to save
        $obj = $table->newEntity($this->request->getData());
        
        if($table->save($obj)) {
          $this->Flash->success(__d('result', 'saved'));
          
          return $this->generateRedirect($obj->id);
        }
        
        $errors = $obj->getErrors();
        
        if(!empty($errors)) {
          $this->Flash->error(__d('error', 'fields', [ implode(',', 
                                                                 array_map(function($v) { return __d('field', $v); },
                                                                           array_keys($errors))) ]));
        } else {
          $this->Flash->error(__d('error', 'save', [$modelsName]));
        }
      }
      catch(\Exception $e) {
        // This throws \Cake\ORM\Exception\RolledbackTransactionException if
        // aborted in afterSave
        
        $this->Flash->error($e->getMessage());
      }
      
      // Pass $obj as context so the view can render validation errors
      $this->set('vv_obj', $obj);
    } else {
      // Create an empty entity for FormHelper
      
      $this->set('vv_obj', $table->newEmptyEntity());
    }
    
    // PrimaryLinkTrait, via AppController
    $this->getPrimaryLink();
    
    // AutoViewVarsTrait, via AppController
    $this->populateAutoViewVars();
    
    // Default title is add new object
    $this->set('vv_title', __d('operation', 'add.a', __d('controller', $modelsName, [1])));

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
      if((method_exists($table, "allowLookupPrimaryLink")
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
        $this->Flash->success(__d('result', 'deleted.a', [$obj->$field]));
      } else {
        $this->Flash->success(__d('result', 'deleted'));
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
        
        // Attempt the update the record
        $table->patchEntity($saveObj, $this->request->getData(), $opts); 
        
        // This throws \Cake\ORM\Exception\RolledbackTransactionException if aborted
        // in afterSave
        if($table->save($saveObj)) {
          $this->Flash->success(__d('result', 'saved'));
          
          return $this->generateRedirect((int)$id); 
        }
        
        $errors = $saveObj->getErrors();
        
        if(!empty($errors)) {
          $this->Flash->error(__d('error', 'fields', [ implode(',', 
                                                                 array_map(function($v) { return __d('field', $v); },
                                                                           array_keys($errors))) ]));
        } else {
          $this->Flash->error(__d('error', 'save', [$modelsName]));
        }
      }
    }
    catch(\Exception $e) {
      // findById throws Cake\Datasource\Exception\RecordNotFoundException
      
      $this->Flash->error($e->getMessage());
      return $this->generateRedirect((int)$id);
    }
    
    $this->set('vv_obj', $obj);
    // XXX should we also set '$model'? cake seems to autopopulate edit fields just fine without it
    //     note index() uses $tableName, not 'vv_objs' or event 'vv_table_name'
    
    // PrimaryLinkTrait
    $this->getPrimaryLink();
    
    // AutoViewVarsTrait
    $this->populateAutoViewVars($obj);
    
    if(method_exists($table, 'generateDisplayField')) {
      // We don't use a trait for this since each table will implement different logic
      
      $this->set('vv_title', __d('operation', 'edit.ai', $table->generateDisplayField($obj), $id));
    } else {
      // Default view title is edit object display field
      $field = $table->getDisplayField();
      
      if(!empty($obj->$field)) {
        $this->set('vv_title', __d('operation', 'edit.ai', $obj->$field, $id));
      } else {
        $this->set('vv_title', __d('operation', 'edit.ai', __d('controller', $modelsName, [1]), $id));
      }
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
    
    // By default we return to the index, but we'll also accept "self" or "primaryLink".
    $redirectGoal = $this->getRedirectGoal();
    
    if($redirectGoal == 'self'
       && $id
       && in_array($this->request->getParam('action'), ['add', 'edit'])) {
      // Redirect to the edit view of the record just added
      // (if the user has add permission, they probably have edit permission)
      
      $redirect = [
        'action' => 'edit',
        $id
      ];
    } elseif($redirectGoal == 'primaryLink') {
      // XXX implement me
      throw new \RuntimeException('generateRedirect NOT IMPLEMENTED');
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
      $query = $table->find()->where([$table->getAlias().'.'.$link->attr => $link->value]);
    } else {
      $query = $table->find();
    }
    
    // QueryModificationTrait
    if(method_exists($table, "getIndexContains")
       && $table->getIndexContains()) {
      $query->contain($table->getIndexContains());
    }
  
    // SearchFilterTrait
    if(method_exists($table, "getSearchableAttributes")) {
      $searchableAttributes = $table->getSearchableAttributes();
    
      if(!empty($searchableAttributes)) {
        // Here we iterate over the attributes and we add a new where clause for each one
        foreach(array_keys($searchableAttributes) as $attribute) {
          if(!empty($this->request->getQuery($attribute))) {
            $query = $table->whereFilter($query, $attribute, $this->request->getQuery($attribute));
          }
        }
      
        $this->set('vv_searchable_attributes', $searchableAttributes);
      }
    }
    
    // The Cake documents describe $this->paginate (which worked in Cake 2),
    // but it doesn't seem to work in Cake 4. So we just use $this->pagination
    // ourselves here.
    $resultSet = $this->Paginator->paginate($query, $this->pagination);
    
    $this->set($tableName, $resultSet);
    $this->set('vv_permission_set', $this->RegistryAuth->calculatePermissionsForResultSet($resultSet));
    
    // Default index view title is model name
    $this->set('vv_title', __d('controller', $modelsName, [99]));
    
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
            // Inject configuration. Since we're only ever looking at the types
            // table, inject the current CO along with the requested attribute
            $avv['model'] = 'Types';
            $avv['where'] = [
              'attribute' => $avv['attribute'],
              'status'    => SuspendableStatusEnum::Active
            ];
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
                    $avv['where'][$table->getAlias().'.'.$linkFilter] = $v;
                    //$query = $query->where([$table->getAlias().'.'.$linkFilter => $v]);
                  }
                }
              } else {
                // Use the specified finder, if configured
                $query = $query->find($avv['find']);
              }
            } else {
// XXX is this the best logic? maybe some relation to filterPrimaryLink?
              // By default, filter everything on CO ID
              
              $avv['where']['co_id'] = $this->getCOID();
              //$query = $query->where([$table->getAlias().'.co_id' => $this->getCOID()]);
            }
            
            if(!empty($avv['where'])) {
              // Filter on the specified clause (of the form [column=>value])
              $query = $query->where($avv['where']);
            }
            
            // Sort the list by display field
            if(!empty($avv['model']) && method_exists($this->$avvmodel, "getDisplayField")) {
              $query->order([$this->$avvmodel->getDisplayField() => 'ASC']);
            } elseif(method_exists($table, "getDisplayField")) {
              $query->order([$table->getDisplayField() => 'ASC']);
            }
            
            $this->set($vvar, $query->toArray());
            break;
          case 'parent':
            $modelsName = $this->name;
            // $table = the actual table object
            $table = $this->$modelsName;
            // XXX We assume that all models that load the Tree behavior will
            //     implement a potentialParents method
            $this->set($vvar, $table->potentialParents($this->getCOID()));
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
   * @since  COmanage Registry v5.0.0
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
      return $this->generateRedirect((int)$id);
    }
    
    $this->set('vv_obj', $obj);
    
    // PrimaryLinkTrait
    $this->getPrimaryLink();
    
    // AutoViewVarsTrait
    // We still used this in view() to map select values
    $this->populateAutoViewVars($obj);
    
    if(method_exists($table, 'generateDisplayField')) {
      // We don't use a trait for this since each table will implement different logic
      
      $this->set('vv_title', __d('operation', 'view.ai', $table->generateDisplayField($obj), $id));
    } else {
      // Default view title is the object display field
      $field = $table->getDisplayField();
      
      if(!empty($obj->$field)) {
        $this->set('vv_title', __d('operation', 'view.ai', $obj->$field, $id));
      } else {
        $this->set('vv_title', __d('operation', 'view.ai', __d('controller', $modelsName, [1]), $id));
      }
    }
    
    // Let the view render
    $this->render('/Standard/add-edit-view');
  }
}