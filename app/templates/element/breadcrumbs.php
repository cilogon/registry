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
    ['plugin'       => null,
     'controller'   => 'cos',
     'action'       => 'select']
  );
  
  // Link to CO if set, or to COmanage CO if not (since we must be in that configuration tree)
// XXX this link doesn't land anywhere yet...
  $this->Breadcrumbs->add(
    !empty($vv_cur_co->name) ? $vv_cur_co->name : "COmanage",
    ['plugin'       => null,
     'controller'   => 'dashboards',
     'action'       => 'dashboard',
     '?'            => ['co_id' => !empty($vv_cur_co) ? $vv_cur_co->id : 1]]
  );
  
  if(isset($vv_is_configuration_model) && $vv_is_configuration_model
     && !($modelsName == 'Dashboards' && $vv_action == 'configuration')) {
    // Insert a link back to the configuration menu
    
    $this->Breadcrumbs->add(
      __d('menu', 'co.configuration'),
      ['plugin'       => null,
       'controller'   => 'dashboards',
       'action'       => 'configuration',
       '?'            => ['co_id' => !empty($vv_cur_co) ? $vv_cur_co->id : 1]]
    );
  }

  if(!empty($vv_primary_link_obj->plugin)
     // JobHistoryRecords have Jobs as their primary link, which define a plugin
     // but aren't standard Pluggable Models, so we exempt them here. If this
     // becomes a pattern we should annotate something instead.
     && $modelsName != 'JobHistoryRecords') {
    // We're in a plugin. Insert a link back to the pluggable object.

    $plModelsName = \App\Lib\Util\StringUtilities::entityToClassName($vv_primary_link_obj);
    $plTable = $vv_primary_link_obj->getSource();

    $this->Breadcrumbs->add(
      __d('controller', $plModelsName, [99]),
      [
        'plugin'      => null,
        'controller'  => $plModelsName, //\Cake\Utility\Inflector::dasherize($plModelsName),
        'action'      => 'index',
        '?'           => ['co_id' => !empty($vv_cur_co) ? $vv_cur_co->id : 1]
      ]
    );

    $this->Breadcrumbs->add(
      // We should look up the display field but since we need the table to do it
      // it's a bit complicated to get. For now, we'll just used name.
      $vv_primary_link_obj->name,
      [
        'plugin'      => null,
        'controller'  => $plModelsName, //\Cake\Utility\Inflector::dasherize($plModelsName),
        'action'      => 'edit',
        $vv_primary_link_obj->id
      ]
    );
  }

// XXX this could possibly somehow merge with the MVEA logic below
  // If we have a parent object interrogate it to construct a link
  if(!empty($vv_bc_parent_obj)) {
    // eg: Groups
    $parentTable = $vv_bc_parent_obj->getSource();
    // eg: groups
    $parentController = \Cake\Utility\Inflector::dasherize($parentTable);

    $this->Breadcrumbs->add(
      __d('controller', $parentTable, [99]),
      ['plugin'     => null,
       'controller' => $parentController,
       '?'          => ['co_id' => !empty($vv_cur_co) ? $vv_cur_co->id : 1]]
    );
    
    $this->Breadcrumbs->add(
      $vv_bc_parent_obj->$vv_bc_parent_displayfield,
      ['plugin'     => null,
       'controller' => $parentController,
       'action'     => $vv_bc_parent_obj->isReadOnly() ? 'view' : 'edit',
       $vv_bc_parent_obj->id]
    );
  }
  
  // If we're rendering an MVEA, insert a link to the parent entity
  if(!empty($vv_primary_link_id)) {
    if(!empty($vv_person_name)) {
      $this->Breadcrumbs->add(
        __d('controller', 'People', [99]),
        ['plugin'     => null,
         'controller' => 'people',
         '?'          => ['co_id' => !empty($vv_cur_co) ? $vv_cur_co->id : 1]]
      );
      
      $this->Breadcrumbs->add(
        $vv_person_name->full_name,
        ['plugin'     => null,
         'controller' => 'people',
         'action'     => 'edit',
         $vv_person_id]
      );
    }
    
    if(!empty($vv_person_role)) {
      $this->Breadcrumbs->add(
        __d('controller', 'PersonRoles', [99]),
        ['plugin'     => null,
         'controller' => 'person_roles',
         '?'          => ['person_id' => $vv_person_role_id]]
      );
      
      $this->Breadcrumbs->add(
        $vv_person_role,
        ['plugin'     => null,
         'controller' => 'person_roles',
         'action'     => 'edit',
         $vv_person_role_id]
      );
    }
    
    if(!empty($vv_ei_name)) {
      $this->Breadcrumbs->add(
        __d('controller', 'ExternalIdentities', [99]),
        ['plugin'     => null,
         'controller' => 'external_identities',
         '?'          => ['co_id' => !empty($vv_cur_co) ? $vv_cur_co->id : 1]]
      );
      
      $this->Breadcrumbs->add(
        $vv_ei_name->full_name,
        ['plugin'     => null,
         'controller' => 'external_identities',
         'action'     => 'edit',
         $vv_ei_id]
      );
    }
    
    if(!empty($vv_ei_role)) {
      $this->Breadcrumbs->add(
        __d('controller', 'ExternalIdentityRoles', [99]),
        ['plugin'     => null,
         'controller' => 'external_identity_roles',
         '?'          => ['external_identity_id' => $vv_ei_id]]
      );
      
      $this->Breadcrumbs->add(
        $vv_ei_role,
        ['plugin'     => null,
         'controller' => 'external_identity_roles',
         'action'     => 'edit',
         $vv_ei_role_id]
      );
    }
  }
  
  if($vv_action != 'index'
     && !($modelsName == 'Dashboards' && $vv_action == 'configuration')
     // Plugin breadcrumbs are handled above, but see above note re JobHistoryRecords
     && (empty($vv_primary_link_obj->plugin) || $modelsName == 'JobHistoryRecords')) {
    // Default parent is index, to which we might need to append the Primary Link ID
    
    $target = [
      'plugin'     => null,
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
// or does not generalize. If we start adding more exceptions here, we should
// flip the logic and let api_users:generate declare that it wants a link back.
  if(!in_array($vv_action, ['add', 'edit', 'index', 'view'])
     && !empty($vv_obj->id)
     && !empty($vv_obj->$vv_display_field)) {
    $oaction = ($vv_permissions['edit'] 
                ? 'edit'
                : ($vv_permissions['view'] ? 'view' : null));
    
    if($oaction) {
      $this->Breadcrumbs->add(
        $vv_obj->$vv_display_field,
        ['plugin'       => null,
         'controller'   => $tableName,
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