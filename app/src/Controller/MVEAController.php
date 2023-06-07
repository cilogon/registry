<?php
/**
 * COmanage Registry Multi Valued Entity Attributes (MVEA) Controller
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
use \App\Lib\Util\StringUtilities;

class MVEAController extends StandardController {
  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */
  
  public function beforeFilter(\Cake\Event\EventInterface $event) {
    // $this->name = Models
    $modelsName = $this->name;

    if(!$this->request->is('restful')) {
      // Provide additional hints to BreadcrumbsComponent. This needs to be here
      // and not in beforeRender because the component beforeRender will run first.
      
      // This is all we need where person_id is the primary link, but for MVEAs
      // that are more deeply linked (to person_role_id, external_identity_id,
      // or external_identity_role_id) we need to look up the further links.
      $primaryLink = $this->getPrimaryLink(true);

      if($primaryLink->attr == 'person_id' || $primaryLink->attr == 'group_id') {
        $this->Breadcrumb->injectPrimaryLink($primaryLink);
      } else {
        $parentModel = StringUtilities::foreignKeyToClassName($primaryLink->attr);

        $parentPrimaryLink = $this->$modelsName->$parentModel->findPrimaryLink((int)$primaryLink->value);

        $this->Breadcrumb->injectPrimaryLink($parentPrimaryLink);
        $this->Breadcrumb->injectPrimaryLink($primaryLink);
      }
    }
    
    return parent::beforeFilter($event);
  }

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
      // If there is a default type setting for this model, pass it to the view
      if($this->$modelsName->getSchema()->hasColumn('type_id')) {
        $defaultTypeField = "default_" . $fieldName . "_type_id";
        
        $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');
        
        $settings = $CoSettings->find()->where(['co_id' => $this->getCOID()])->firstOrFail();
        
        $this->set('vv_default_type', $settings->$defaultTypeField);
      }
    }
    
    return parent::beforeRender($event);
  }
}