<?php
/**
 * COmanage Registry Generic View Template For Add/Edit/View Actions
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

//$action = $this->template;
// $this->name = Models
$modelsName = $this->name;
// $tablename = models
// XXX backport to match?
$tableName = \Cake\Utility\Inflector::tableize(\Cake\Utility\Inflector::singularize($this->name));

// If you're looking to set a custom $vv_title, you might be able to use
// generateDisplayField() on the Table instead

// Include subnavigation structures on add/edit/view pages
// XXX: if CFM-218 (Make fields.inc configuration only) is accepted, move the contents of fields-nav.inc into fields.inc
// When subnav exists, include on all Edit views and on Add/View for items with a parent.
if($vv_action == 'edit' || !empty($vv_bc_parent_obj) || !empty($vv_primary_link_id)) {
  if(file_exists(ROOT . DS . "templates" . DS . $modelsName . DS . "fields-nav.inc")) {
    include(ROOT . DS . "templates" . DS . $modelsName . DS . "fields-nav.inc");
  }  
}

if(file_exists(ROOT . DS . "templates" . DS . $modelsName . DS . "fields-links.inc")) {
  include(ROOT . DS . "templates" . DS . $modelsName . DS . "fields-links.inc");
} 

// $linkFilter is used for models that belong to a specific parent model (eg: co_id)
$linkFilter = [];

if(!empty($vv_primary_link) && !empty($this->request->getQuery($vv_primary_link))) {
  $linkFilter = [$vv_primary_link => $this->request->getQuery($vv_primary_link)];
}

// $flashArgs pass banner messages to the flash element container
$flashArgs = [];
if(!empty($banners)) {
  // XXX this doesn't work yet because we don't include fields.inc until later
  //     either create a second file to include earlier, or use a function to emit
  //     the fields (which would be more consistent with how Views render...)
  $flashArgs['vv_banners'] = $banners;
}

// If subnavigation is present a supertitle and the subnavigation will be placed above
// the normal page title. The flash messages will be shown up there as well.
if(!empty($subnav)) {
  // Include the $flashArgs for the subnavigation element
  $subnav['flashArgs'] = $flashArgs;
  if(!empty($topLinks) && ($modelsName == 'People' &&  $vv_action == 'edit')) {
    // We are in Person canvas mode: pass along the top links for building the Actions menu.
    $subnav['topLinks'] = $topLinks;
  }
  // Generate the subnavigation title and tabs
  print $this->element('subnavigation', $subnav);
}
?>

<div class="pageTitleContainer">
  <div class="pageTitle">
    <?php if(empty($subnav)): ?>
      <h1><?= $vv_title; ?></h1>
    <?php else: ?>
      <h2><?= $vv_title; ?></h2>
    <?php endif; ?>
  </div>
  <?php
    // Action list for top menu dropdown / button listing
    $action_args = array();
    $action_args['vv_attr_id'] =  $vv_obj->id;
    
    foreach(($topLinks ?? []) as $t) {
      $perm = false;

      if(!empty($t['link']['controller'])) {
        // We're linking into a related model

        $linkModel = \Cake\Utility\Inflector::camelize($t['link']['controller']);

        if(isset($vv_permissions[$linkModel][ $t['link']['action'] ])) {
          $perm = $vv_permissions[$linkModel][ $t['link']['action'] ];
        }

        // Inject a link to the current object ID
        $t['link']['?'][\App\Lib\Util\StringUtilities::entityToForeignKey($vv_obj)] = $vv_obj->id;
      } else {
        $perm = $vv_permissions[ $t['link']['action'] ];

        // We need to inject $linkFilter, but not overwrite any existing query params
        if(!empty($t['link']['?'])) {
          $t['link']['?'] = array_merge($t['link']['?'], $linkFilter);
        } else {
          $t['link']['?'] = $linkFilter;
        }
      }

      if($perm) {
        $action_args['vv_actions'][] = [
          'order' => $this->Menu->getMenuOrder($t['order']),
          'icon' => $this->Menu->getMenuIcon($t['icon']),
          'url' => $this->Url->build($t['link']),
          'label' => $t['label'],
        ];
      }
    }
  
    // Delete
    if($vv_action != 'add' && !empty($vv_obj->id) && $vv_permissions['delete']) {
      $actionPostBtnArray = ['action' => 'delete', $vv_obj->id];
      $actionUrl = $this->Url->build(['action' => 'delete', $vv_obj->id]);
      $action_args['vv_actions'][] = array(
        'order' => $this->Menu->getMenuOrder('Delete'),
        'icon' =>  $this->Menu->getMenuIcon('Delete'),
        'url' => 'javascript:void(0);',
        'label' => __d('operation', 'delete'),
        'class' => 'deletebutton nospin',
        'onclick' => array(
          'dg_bd_txt' => __d('operation', 'delete.confirm', [$vv_obj->id]),
          'dg_post_btn_array' => $actionPostBtnArray,
          'dg_url' => $actionUrl,
          'dg_conf_btn' => __d('operation', 'remove'),
          'dg_cancel_btn' => __d('operation', 'cancel'),
          'dg_title' => __d('operation', 'remove'),
          'dg_bd_txt_repl_str' => ''
        )
      );
    }
  
    if(!empty($action_args['vv_actions'])) {
      print '<div class="field-actions top-links">';
      print $this->element('menuAction', $action_args);
      print '</div>';
    }
  ?>
</div>

<?php if(empty($subnav)): ?>
  <?php /* Flash Messages are placed below the main title when there's no subnavigation. */ ?>
  <?= $this->element('flash', $flashArgs); ?>
<?php endif; ?>

<?php
// By default, the form will POST to the current controller
// Note we need to open the form for view so Cake will autopopulate values
print $this->Form->create($vv_obj);

$linkId = null;

if(!empty($vv_primary_link)) {
  if(!empty($this->request->getQuery($vv_primary_link))) {
    $linkId = $this->request->getQuery($vv_primary_link);
  } elseif(!empty($this->request->getData($vv_primary_link))) {
    $linkId = $this->request->getData($vv_primary_link);
  } elseif(!empty($vv_obj->$vv_primary_link)) {
    $linkId = $vv_obj->$vv_primary_link;
  }
}

print $this->Field->startControlSet($this->name, 
                                    $vv_action,
                                    // XXX We need a model specific mechanism to disable read-only
                                    ($vv_action == 'add' || $vv_action == 'edit'),
                                    $vv_required_fields);

// We allow the fields.inc file to be specified for Controllers that have more
// complicated/non-default actions.
$fieldsFile = "fields.inc";

if(!empty($vv_fields_inc)) {
  $fieldsFile = $vv_fields_inc;
}

// The controller will calculate the template path for us, since it could be
// in one of several paths if we are in a plugin context.
include($vv_template_path . DS . $fieldsFile);

if(!empty($hidden)) {
  // Inject any hidden variables set by the include file
  foreach($hidden as $attr => $v) {
    print $this->Form->hidden($attr, ['value' => $v]);
  }
}

if($vv_action != 'add') {
  print '<li id="cm-entity-id">' . __d('information', 'entity.id', $vv_obj->id) . '</li>';  
}

if($vv_action == 'add' || $vv_action == 'edit') {
  // We don't want/need to output these for view actions
  
  if(!empty($linkId)) {
    // Hidden values used to link to parent objects (eg: matchgrid_id)
    print $this->Form->hidden($vv_primary_link, ['value' => $linkId]);
  }
  
  print $this->Field->submit(__d('operation', 'save'));
}

print $this->Form->end();

print $this->Field->endControlSet();

/** MVEA Canvas output **/
if($vv_action != 'add' && !empty($mveas)) {
  // Pass along the $mveas and any $addMenuLinks defined in templates/.../fields-nav.inc config. 
  print $this->element('mveaCanvas',
    [
      'vv_mveas' => $mveas,
      'vv_add_menu_links' => !empty($addMenuLinks) ? $addMenuLinks : '',
      'vv_entity_type' => $mveasEntityType
    ]);
}  

// XXX insert changelog metadata (+nav? or maybe we should have a dedicate index view that shows all records in revision order?)
