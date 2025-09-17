<?php
/**
 * COmanage Registry Standard Plugin Controller
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

use \App\Lib\Util\StringUtilities;
use \App\Lib\Enum\SuspendableStatusEnum;

class StandardPluginController extends StandardController {
  /**
   * Callback run prior to the request action.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */

  public function beforeFilter(\Cake\Event\EventInterface $event) {
    /** var string $modelsName */
    $modelsName = $this->getName();

    if(!$this->request->is('restful')) {
      // Provide additional hints to BreadcrumbsComponent. This needs to be here
      // and not in beforeRender because the component beforeRender will run first.
      
      // This is all we need where person_id is the primary link, but for MVEAs
      // that are more deeply linked (to person_role_id, external_identity_id,
      // or external_identity_role_id) we need to look up the further links.
      $primaryLink = $this->getPrimaryLink(true);

      $this->Breadcrumb->skipParents(['/^\/[a-zA-Z0-9-]+\/[a-zA-Z0-9-]+\/edit\//']);

      if(!empty($primaryLink->attr)) {
        if($primaryLink->attr == 'server_id') {
          // Servers shouldn't show up as configuration, so automatically hide it
          // eg for server plugins
          $this->Breadcrumb->skipConfig(['/^\//']);
        }

        // The authenticator routes have a unique pattern. We will not inject the Breadcrumb here, but
        // we will construct it in the MultipleAuthtenticatorController.
        // For very deep breadcrumbs we need to skip the generic rule
        // and allow the controller to handle it.
        if(
          !str_ends_with($primaryLink->attr, '_authenticator_id')
          && !str_ends_with($primaryLink->attr, '_server_id')
        ) {
          $this->Breadcrumb->injectPrimaryLink($primaryLink);
        }
      }
    }
    
    parent::beforeFilter($event);
  }

  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */

  public function beforeRender(\Cake\Event\EventInterface $event) {
    $link = $this->getPrimaryLink(true);
    
    if(!empty($link->value) && !empty($link->model_name)) {
      // This might be a plugin table in Plugin.Model notation
      $parentTable = TableRegistry::getTableLocator()->get($link->model_name);

      $parentObj = $parentTable->get($link->value);
      $parentDisplayField = $parentTable->getDisplayField();

      $this->set('vv_bc_parent_obj', $parentObj);
      $this->set('vv_bc_parent_displayfield', $parentDisplayField);
      $this->set('vv_bc_parent_primarykey', $parentTable->getPrimaryKey());
      
      // Override the title set in StandardController. Since that was set in edit()
      // which is called before the rendering hooks, this title will take precedence.

      [$title, , ] = StringUtilities::entityAndActionToTitle($parentObj, $link->model_name, 'configure');
      $this->set('vv_title', $title);
    }

    return parent::beforeRender($event);
  }

  /**
   * Determine the filesystem path to a file within a plugin.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $pluginName Physical plugin name
   * @param  string $file       File name within plugin
   * @return string             Path to file
   */

  protected function getPluginPath(string $pluginName, string $file): string {
    $PluginTable = $this->getTableLocator()->get('Plugins');

    // Because plugins are uniquely named (AR-Plugin-1) we can do a find based
    // on the name to get the object.

    $plugin = $PluginTable->find()
                          ->where([
                              'plugin' => $pluginName,
                              'status' => SuspendableStatusEnum::Active
                            ])
                          ->firstOrFail();

    return $PluginTable->pluginPath($plugin, $file);
  }
}