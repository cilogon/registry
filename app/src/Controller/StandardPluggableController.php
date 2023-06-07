<?php
/**
 * COmanage Registry Standard Pluggable Controller
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
use App\Lib\Util\StringUtilities;

class StandardPluggableController extends StandardController {
  /**
   * Redirect into the edit view of the instantiated plugin.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id Object ID
   */
  
  public function configure(string $id) {
    // We basically implement a redirect here to faciliate view rendering.
    // (We only need to map into the plugin on actual link click, instead of
    // potentially many times on an index view for links that may not be used.)

    // $this->name = Models (ie: from ModelsTable)
    $modelsName = $this->name;
    // $table = the actual table object
    $table = $this->$modelsName;

    $parentId = $this->request->getParam('pass')[0];
    $parentObj = $table->findById($parentId)
                       ->firstOrFail();

    $pluginTable = $this->getTableLocator()->get($parentObj->plugin);
    $pluginObj = $pluginTable->find()
                             ->where([StringUtilities::tableToForeignKey($table) => $parentId])
                             ->firstOrFail();
    
    return $this->redirect([
      'plugin'      => StringUtilities::pluginPlugin($parentObj->plugin),
      'controller'  => StringUtilities::pluginModel($parentObj->plugin),
      'action'      => 'edit',
      $pluginObj->id
    ]);
  }
  
  /**
   * Instantiate the plugin for this Pluggable model. Upon success, a redirect into
   * the edit view for the instantiated object will be issued.
   * 
   * @since  COmanage Registry v5.0.0
   * @param object $obj Pluggable object
   */

  protected function instantiatePlugin(object $obj) {
    // Create the row for the entry point model, then redirect into it

    // eg: report_id
    $parentKey = StringUtilities::entityToForeignKey($obj);

    // For now, we just populate the foreign key from the instantiated plugin
    // to its parent object, but we might want to allow the plugin model to
    // set some default values.
    $created = new \Datetime('now');

    $iValues = [
      $parentKey  => $obj->id,
      'created'   => $created->format('Y-m-d H:i:s')
    ];

    $pTable = $this->getTableLocator()->get($obj->plugin);

    $iObj = $pTable->newEntity($iValues);
    
    // We skip validation and rule checking because we're saving a skeletal record. (AR-Plugin-9)

    if($pTable->save($iObj, ['validate' => false, 'checkRules' => false])) {
      // Redirect into plugin

      return $this->redirect([
        'plugin'      => StringUtilities::pluginPlugin($obj->plugin),
        'controller'  => StringUtilities::pluginModel($obj->plugin),
        'action'      => 'edit',
        $iObj->id
      ]);
    } else {
      $this->Flash->error(__d('error', 'save.plugin', [$obj->plugin]));
    }
  }
}