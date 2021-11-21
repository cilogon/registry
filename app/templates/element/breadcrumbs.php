<?php
/*
 * COmanage Registry Breadcrumbs
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

if($this->request->getRequestTarget(false) != '/') {
  // Don't bother rendering breadcrumbs if we're already at the top page

  // $this->name = Models
  $modelsName = $this->name;
  // $tablename = models
  $tableName = \Cake\Utility\Inflector::tableize(\Cake\Utility\Inflector::singularize($this->name));
  
  $this->Breadcrumbs->setTemplates([
    'wrapper' => '{{content}}',
    'item' => '<a href="{{url}}"{{innerAttrs}}>{{title}}</a>{{separator}}',
    'itemWithoutLink' => '<span{{innerAttrs}}>{{title}}</span>{{separator}}',
    'separator' => '<span{{innerAttrs}}>{{separator}}</span>'
  ]);

  $this->Breadcrumbs->prepend(
    __('registry.meta.registry'),
    ['controller'   => 'cos',
     'action'       => 'select']
  );
  
  // Link to CO if set, or to COmanage CO if not (since we must be in that configuration tree)
// XXX this link doesn't land anywhere yet...
  $this->Breadcrumbs->add(
    !empty($vv_cur_co->name) ? $vv_cur_co->name : "COmanage",
    ['controller'   => 'dashboards',
     'action'       => 'dashboard',
     '?'            => [
      'co_id' => !empty($vv_cur_co) ? $vv_cur_co->id : 1
    ]]
  );
  
  if(isset($vv_is_configuration_model) && $vv_is_configuration_model
     && !($modelsName == 'Dashboards' && $vv_action == 'configuration')) {
    // Insert a link back to the configuration menu
    
    $this->Breadcrumbs->add(
      __d('menu', 'co.configuration'),
      ['controller'   => 'dashboards',
       'action'       => 'configuration',
       '?'            => [
        'co_id' => !empty($vv_cur_co) ? $vv_cur_co->id : 1
      ]]
    );
  }
  
  if($vv_action != 'index'
     && !($modelsName == 'Dashboards' && $vv_action == 'configuration')) {
    // Default parent is index, to which we might need to append the Primary Link ID
    
    $target = [
      'controller' => $tableName,
      'action'     => 'index'
    ];
    
    if(!empty($vv_primary_link) && !empty($vv_primary_link_obj->id)) {
      $target['?'] = [$vv_primary_link => $vv_primary_link_obj->id];
    }
    
    $this->Breadcrumbs->add(
      __d('controller', $modelsName, [99]),
      $target
    );
  }
  
  // If we have an object id and are not one of the "standard" actions,
  // insert a breadcrumb back to the main object view.
// XXX This is initially for api_users:generate, not clear how much this does
// or does not generalize  
  if(!in_array($vv_action, ['add', 'edit', 'index', 'view'])
     && !empty($vv_obj->id)
     && !empty($vv_obj->$vv_display_field)) {
    $oaction = ($vv_permissions['edit'] 
                ? 'edit'
                : ($vv_permissions['view'] ? 'view' : null));
    
    if($oaction) {
      $this->Breadcrumbs->add(
        $vv_obj->$vv_display_field,
        ['controller'   => $tableName,
         'action'       => $oaction,
         $vv_obj->id ]
      );
    }
  }
  
  if(!empty($vv_title)) {
    $this->Breadcrumbs->add(
      $vv_title
    );
  }
  
  print $this->Breadcrumbs->render(
    [],
    ['separator' => ' &gt; ']
  );
}