<?php
/**
* COmanage Registry Subnavigation Tabs Element
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
*
* The subnavigation is structured in first, second, and third level blocks.
* Only External Identity Roles have a third-level of navigation.
* 
*/
use Cake\View\Helper;

$linkFilter = [];
$flashArgs = [];
$curId = NULL;
$curController = $this->request->getParam('controller');
$curAction = $this->request->getParam('action');
$navController = $curController;

if(!empty($vv_primary_link) && !empty($this->request->getQuery($vv_primary_link))) {
  // This will work for most top-level index views
  $curId = $this->request->getQuery($vv_primary_link);
  $linkFilter = [$vv_primary_link => $curId];
  // For top-level nav
  if(!empty($vv_person_id)) {
    $curId = $vv_person_id;
    $linkFilter = ['person_id' => $vv_person_id];
  }
} elseif (!empty($vv_obj)) {
  // This will work for most top-level edit views
  // XXX we might produce the $vv_primary_link for edit views so the approach below could be deprecated
  // XXX $vv_primary_link_obj is the equivalent for plugins however
  $curId = $vv_obj->id;
  if(!empty($tabsId) && !empty($tabsController)) {
    // these have been explicitly set in the $subnav array in fields-nav.inc, so just use them.
    $curId = $tabsId;
    $navController = $tabsController;
  } elseif(!empty($vv_person_id)) {
    $curId = $vv_person_id;
  } elseif($active == 'plugin' && !empty($vv_primary_link_obj) && !empty($vv_primary_link_model)) {
    $curId = $vv_primary_link_obj->id;
    $navController = $vv_primary_link_model;
  } elseif(!empty($vv_primary_link_id)) {
    $curId = $vv_primary_link_id;
  } 
  
  // For top-level nav while in edit pages.
  if ($name == 'person') {
    $linkFilter = ['person_id' => $curId];
  } elseif ($name == 'group') {
    $linkFilter = ['group_id' => $curId];
  }
} elseif(!empty($vv_bc_title_links)) {
  // All else fails? Use the breadcrumb which has figured this out.
  // XXX We might just be able to do this and skip all the above after breadcrumbs have been refactored
  $curId = end($vv_bc_title_links[0]['target']);
}

if(!empty($vv_obj)) {
  // Set the badge style for Person Status
  $statusBadgeClass = 'bg-warning';
  if($vv_obj['status'] == 'A') {
    $statusBadgeClass = 'bg-outline-secondary primary';
  } elseif (in_array($vv_obj['status'], ['D','N','S','X','XP'])) {
    $statusBadgeClass = 'bg-danger';
  }  
}

$supertitle = __d('information','global.title.none');
if(!empty($tabsSupertitle)) {
  // this has been explicitly set in the $subnav array in fields-nav.inc, so just use it.
  $supertitle = $tabsSupertitle; 
} elseif($active == 'plugin' && !empty($vv_bc_parent_obj)) {
  $supertitle = $vv_bc_parent_obj->$vv_bc_parent_displayfield;
} elseif(!empty($vv_person_name)) {
  $supertitle = $vv_person_name->full_name;
} elseif(!empty($vv_supertitle)) {
  $supertitle = $vv_supertitle;
} elseif(!empty($vv_obj)) {
  $supertitle = $vv_obj->$vv_display_field;
} elseif(!empty($vv_bc_parent_obj)) {
  $supertitle = $vv_bc_parent_obj->$vv_bc_parent_displayfield;
} elseif(!empty($vv_bc_title_links)) {
  // All else fails? Use the breadcrumb which has figured this out.
  // XXX We might just be able to do this and skip all the above after breadcrumbs have been refactored
  $supertitle = $vv_bc_title_links[0]['label'];
}
?>

<div id="subnavigation">
  <div class="supertitle-container">
    <div class="supertitle">
      <h1><?= $supertitle ?></h1>
      <?php if(!empty($vv_obj['status']) && $active == 'person'): ?>
        <span class="person-status-badge mr-1 badge <?= $statusBadgeClass ?>">
          <?= __d('enumeration', 'StatusEnum.' . $vv_obj['status']); ?>
        </span>
      <?php endif; ?>
    </div>
    
    <?php /* XXX Turn off the global actions menu but leave it here for now. We may restore it for a different purpose later.
    <?php if($name == 'person'): ?>
      <!-- Specialty person dropdown menus - global actions -->
      <nav id="person-actions">
        <?php
          // Build the global Person Actions menu
          $action_args = array();
          $action_args['vv_attr_id'] =  $curId;
          $action_args['vv_actions_type'] = 'person-actions-menu';
          $action_args['vv_actions_title'] = __d('field','actions',[99]);
          $action_args['vv_actions_icon'] = 'settings';
          $action_args['vv_actions_class'] = 'person-actions-menu';
          if(!empty($topLinks)) {
            foreach ($topLinks as $action) {
              $action_args['vv_actions'][] = array(
                'order' => $this->Menu->getMenuOrder($action['order']),
                'icon' => $action['icon'],
                'url' => $this->Url->build($action['link']),
                'label' => $action['label']
              );
            }
          }
          // delete
          $action_args['vv_actions'][] = array(
            'order' => $this->Menu->getMenuOrder('Delete'),
            'icon' =>  $this->Menu->getMenuIcon('Delete'),
            'url' => ['action' => 'delete', $curId],
            'label' => __d('operation', 'delete'),
            'class' => 'deletebutton nospin',
            'confirm' => array(
              'dg_body_txt' => __d(
                'operation', 
                'delete.confirm', 
                [$supertitle . ', ' . __d('information','entity.id',[$curId])]
              ),
              'dg_post_btn_array' => $actionPostBtnArray,
              'dg_confirm_btn' => __d('operation', 'remove'),
              'dg_cancel_btn' => __d('operation', 'cancel'),
              'dg_title' => __d('operation', 'remove'),
              'dg_body_txt_replacements' => ''
            )
          );
        ?>
        <div class="field-actions person-actions-menu-container">
          <?= $this->element('menuAction', $action_args) ?>
        </div>
      </nav>
    <?php endif; // person-actions ?>
      */ ?>
  </div>

  <?php /* Flash Messages are placed below supertitle when subnavigation exists. */ ?>
  <?= $this->element('flash', $flashArgs); ?>
  
  <!-- Top-Level Subnavigation Tabs -->  
  <nav id="cm-<?= $name ?>-subnav-tabs" class="cm-subnav-tabs">
    <ul class="nav nav-tabs">
  
      <?php if($name == 'person'): ?>      
        <!-- Person Subnavigation -->
        <?php
          // Simplify our tests for roles and external identifiers
          $isPersonRole = (
            $vv_primary_link == 'person_role_id'
            || $vv_primary_link == 'person_role_id'
            || ($curController == 'PersonRoles' && ($curAction == 'edit' || $curAction == 'view'))
          ) ? true : false;
  
          $isExternalIdRole = (
            $vv_primary_link == 'external_identity_role_id'
            || ($curController == 'ExternalIdentityRoles' && ($curAction == 'edit' || $curAction == 'view'))
          ) ? true : false;
  
          $isExternalId = (
            $vv_primary_link == 'external_identity_id'
            || $vv_primary_link == 'external_identity_role_id'
            || ($curController == 'ExternalIdentities' && ($curAction == 'edit' || $curAction == 'view'))
            || ($curController == 'ExternalIdentityRoles' && ($curAction == 'edit' || $curAction == 'view'))
          ) ? true : false;
          
          // Determine active tab. This logic is necessitated by MVEAs (that are displayed in multiple contexts).
          $isCanvasTab = ($active == 'canvas') ? true : false;
          
          $isPersonTab = (
            $active == 'person' 
            && !$isPersonRole
            && !$isExternalId
          ) ? true : false;
          
          $isRolesTab = (
            $active == 'person_roles'
            || $isPersonRole
            && !$isExternalId
          ) ? true : false;
          
          $isExternalIdentitiesTab = (
            $active == 'external_identities' 
            || $isExternalId
          ) ? true : false;
  
          $isGroupsTab = ($active == 'groups') ? true : false;
        ?>
        
        <li class="nav-item">
          <?php
            $linkClass = $isPersonTab ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'People', [1]),
              [ 'controller' => 'people',
                // TODO: the following test needs to be made based on read-only status of the Person only
                //'action' => $curAction == 'view' ? 'view' : 'edit',
                'action' => 'edit',
                $curId
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
        <li class="nav-item">
          <?php
            $linkClass = $isRolesTab ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'PersonRoles', [99]),
              [ 'controller' => 'person_roles',
                'action' => 'index',
                '?' => $linkFilter
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
        <li class="nav-item">
          <?php
            $linkClass = $isExternalIdentitiesTab ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'ExternalIdentities', [99]),
              [ 'controller' => 'external-identities',
                'action' => 'index',
                '?' => $linkFilter
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
        <?php 
        /* XXX Groups tab placeholder - Keep for now. A group listing may only need to 
           exist on the Overview tab.
        <li class="nav-item">
          <?php
            $linkClass = $isGroupsTab ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'Groups', [99]),
              [ 'controller' => 'groups',
                'action' => 'index',
                '?' => $linkFilter
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
        */ ?>
      <?php endif; // person ?>
      
      <?php if ($name == 'group'): ?>
      <!-- Group Subnavigation -->
        <li class="nav-item">
          <?php
            $linkClass = ($active == 'properties') ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'Properties', [99]),
              [ 'controller' => 'groups',
                // TODO: the following test needs to be made based on read-only status of the group
                'action' => $curAction == 'view' ? 'view' : 'edit',
                $curId
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
        <li class="nav-item">
          <?php
            $linkClass = ($active == 'members') ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'Members', [99]),
              [ 'controller' => 'group_members',
                'action' => 'index',
                '?' => $linkFilter
              ],
              ['class' => $linkClass]
            ); 
          ?>
        </li>
        <li class="nav-item">
          <?php
            $linkClass = ($active == 'nestings') ? 'nav-link active' : 'nav-link';
            print $this->Html->link(
              __d('controller', 'Nestings', [99]),
              [ 'controller' => 'group_nestings',
                'action' => 'index',
                '?' => $linkFilter
              ],
              ['class' => $linkClass]
            );
          ?>
        </li>
      <?php endif; // group ?>
      
      <?php if ($name == 'plugin'): ?>
        <!-- General Plugin Configuration Subnavigation -->
        <!-- Used for all plugins that have a parent object with a child plugin config -->
        <li class="nav-item">
          <?php
            $linkClass = ($active == 'properties') ? 'nav-link active' : 'nav-link';
            
            // Because we are in a plugin, the normal link() and build() functions want to 
            // include the plugin path as part of the URL (and plugin => false cannot be used
            // with these functions like it can with references to resources). Pass a string instead.
            $navUrl = '/' . \Cake\Utility\Inflector::dasherize($navController) . 
              ($curAction == 'view' ? '/view/' : '/edit/') . $curId;
            
            print $this->Html->link(
              __d('controller', 'Properties', [99]),
              $navUrl,
              ['class' => $linkClass]
            );
          ?>
        </li>
        <li class="nav-item">
          <?php
            $linkClass = ($active == 'plugin') ? 'nav-link active' : 'nav-link';
            $navUrl = '/' . \Cake\Utility\Inflector::dasherize($navController) . '/configure/' . $curId;
            print $this->Html->link(
              __d('operation', 'configure.plugin'),
              $navUrl,
              ['class' => $linkClass]
            );
          ?>
        </li>
        <?php if(
          ($navController == 'ExternalIdentitySources' && (!empty($vv_permissions['search']) || !empty($vv_permissions['edit']))) ||
          ($curController == 'ExtIdentitySourceRecords' && !empty($vv_permissions['view']))
        ): ?>
          <li class="nav-item">
            <?php
              $linkClass = ($active == 'search') ? 'nav-link active' : 'nav-link';
              $navUrl = '/' . \Cake\Utility\Inflector::dasherize($navController) . '/search/' . $curId;
              print $this->Html->link(
                __d('information', 'ExternalIdentitySources.records'),
                $navUrl,
                ['class' => $linkClass]
              );
            ?>
          </li>
        <?php endif; ?>
      <?php endif; // plugin ?>
      
    </ul>
  </nav>

  <?php if(!empty($subActive) && $isExternalId): ?>
    <!-- Second Level Subnavigation Links -->
    <?php
      $parentId = $curId;
      $curId = $this->request->getQuery($vv_primary_link);
      if(!empty($vv_ei_id)) {
        $curId = $vv_ei_id;
        $linkFilter = ['external_identity_id' => $curId];
      } elseif(!empty($vv_obj)) {
        $curId = $vv_obj->id;
        $linkFilter = ['external_identity_id' => $curId];
      } 
    ?>
    <?php if($isExternalId): ?>
    <nav id="cm-<?= $name ?>-subnav-links" class="cm-subnav-links">
      <ul class="list-inline">
        <?php if($name == 'person'): ?>
          <li class="list-inline-item">
            <?php
              // Properties
              $cc = 'external-identities';
              $cid = $curId;
              if(!empty($vv_ei_id)) {
                $cid = $vv_ei_id;  
              }
              $linkClass = ($subActive == 'properties') ? 'nav-link active' : 'nav-link';
              print $this->Html->link(
                __d('controller', 'Properties', [99]),
                [ 'controller' => $cc,
                  'action' => 'view',
                  $cid
                ],
                ['class' => $linkClass]
              );
            ?>
          </li>
          <li class="list-inline-item">
            <?php
              // External Identity Roles
              $linkClass = ($subActive == 'external_identity_roles' ||  $isExternalIdRole) ? 'nav-link active' : 'nav-link';
              print $this->Html->link(
                __d('controller', 'ExternalIdentityRoles', [99]),
                [ 'controller' => 'external_identity_roles',
                  'action' => 'index',
                  '?' => $linkFilter
                ],
                ['class' => $linkClass]
              );
            ?>
          </li>
          <?php endif; ?>
        <?php endif; // person subnav ?>
      </ul>
    </nav>
    
  <?php endif; // end  $isExternalId ?>
</div>
