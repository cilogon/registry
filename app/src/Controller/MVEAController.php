<?php
/**
 * COmanage Registry Multi Valued Entity Attributes (VMEA) Controller
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
use Cake\Utility\Inflector;

class MVEAController extends StandardController {
  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */
  
  public function beforeRender(\Cake\Event\EventInterface $event) {
    // $this->name = Models
    $modelsName = $this->name;
    // field = model (or model_name)
    $fieldName = Inflector::underscore(Inflector::singularize($modelsName));
    
    if(!$this->request->is('restful')) {
      // Use the PrimaryLink to set information for breadcrumbs
      
      $link = $this->getPrimaryLink(true);
      
      if(!empty($link->value)) {
        $this->set('vv_primary_link_id', $link->value);
        
        switch($link->attr) {
          case 'person_role_id':
            $PersonRoles = TableRegistry::get('PersonRoles');
            $roleEntity = $PersonRoles->findById((int)$link->value)->firstOrFail();
            // Note this is a string, but vv_person_name is an entity
            $this->set('vv_person_role', $PersonRoles->generateDisplayField($roleEntity));
            $this->set('vv_person_role_id', $link->value);
            
            // Also set a name
            $Names = TableRegistry::get('Names');
            $this->set('vv_person_name', $Names->primaryName($roleEntity->person_id));
            $this->set('vv_person_id', $roleEntity->person_id);
            break;
          case 'person_id':
            $Names = TableRegistry::get('Names');
            $this->set('vv_person_name', $Names->primaryName((int)$link->value));
            $this->set('vv_person_id', $link->value);
            break;
          default;
            break;
        }
      }
      
      // If there is a default type setting for this model, pass it to the view
      if($this->$modelsName->getSchema()->hasColumn('type_id')) {
        $defaultTypeField = $fieldName . "_default_type_id";
        
        $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');
        
        $settings = $CoSettings->find()->where(['co_id' => $this->getCOID()])->firstOrFail();
        
        $this->set('vv_default_type', $settings->$defaultTypeField);
      }
    }
    
    return parent::beforeRender($event);
  }
}