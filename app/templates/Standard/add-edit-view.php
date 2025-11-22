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
/** var string $modelsName */
$modelsName = $this->getName();
// $tablename = models
// XXX backport to match?
$tableName = \Cake\Utility\Inflector::tableize(\Cake\Utility\Inflector::singularize($this->name));

// $vv_template_path will be set for plugins
$templatePath = $vv_template_path ?? ROOT . DS . "templates" . DS . $modelsName;

// Include fields configuration for the form. This will typically include the $fields array.
$fields = []; // Backstop the fields array: some configs have no fields.
$fieldsFile = "fields.inc"; // This is the default.
if(file_exists($templatePath . DS . 'fields-' . $vv_action . '.inc')) {
  // We have an action file override in the form of "fields-ACTION.inc". Use it.
  $fieldsFile = 'fields-' . $vv_action . '.inc';
}
include($templatePath . DS . $fieldsFile);

// $flashArgs pass alert messages to the flash element container.
// $alertMessages will hold the messages to pass to $flashArgs.  
$flashArgs = [];
$alertMessages = [];

// Check for changelog archive view. These alerts come first.
$clfield = $vv_obj->changelogAttributeName();

if($vv_obj->deleted) {
  $alertMessages[] = [
    'type' => 'information',
    'message' => __d('information', 'changelog.deleted')
  ];
} elseif(!empty($vv_obj->$clfield)) {
  $alertMessages[] = [
    'type' => 'information',
    'message' => __d('information', 'changelog.archived')
  ];
}

// Include any configured messages from the $alerts array in the fields file
$alertMessages = array_merge($alertMessages, $alerts ?? []);

if(!empty($alertMessages)) {
  // We have alerts. Assign them to $flashArgs for later rendering.
  $flashArgs['vv_alerts'] = $alertMessages;
}

// If you're looking to set a custom $vv_title, you might be able to use
// generateDisplayField() on the Table instead

// $linkFilter is used for models that belong to a specific parent model (eg: co_id)
$linkFilter = [];

if(!empty($vv_primary_link) && !empty($this->request->getQuery($vv_primary_link))) {
  $linkFilter = [$vv_primary_link => $this->request->getQuery($vv_primary_link)];
}

// Subnavigation
$hasSubnav = false;
if($vv_action !== 'add' && file_exists(ROOT . DS . 'templates' . DS . 'Standard/subnavigation.inc')) {
  include(ROOT . DS . 'templates' . DS . 'Standard/subnavigation.inc');
  $hasSubnav = $this->get('hasSupertitle');
}

// When under subnavigation we do not want a title with Edit or Add or View followed by a number.
// We might find ourselves in that situation since we calculate the title for the breadcrumbs and
// this simple description is not wrong. It is just not appropriate for the subnavigation title
$title = $vv_title;
$re = '/^(Add|Edit|View)\s([a-zA-Z]+?)\s[0-9]+/m';
$pregMatch = preg_match_all($re, $vv_title, $matches, PREG_SET_ORDER, 0);
if (
  $hasSubnav
  && filter_var($pregMatch, FILTER_VALIDATE_BOOLEAN)
) {
  $vvObjTable = $this->Tab->getModelTableReference($fullModelsName);
  $displayField = $vvObjTable->getDisplayField();
  if($displayField !== 'id') {
    $title = __d('operation', "$vv_action.$modelsName.a", [$vv_obj->$displayField]);
  } else {
    $title = __d('operation', $vv_action . '.a', [__d('controller', $modelsName, 1)]);
  }
}
?>

<div class="page-title-container">
  <div class="page-title">
    <?php if(!$hasSubnav): ?>
      <h1><?= $title ?></h1>
    <?php else: ?>
      <h2><?= $title ?></h2>
    <?php endif; ?>
  </div>
  <?php
    if($vv_action !== 'add') {
      // Action list for top menu dropdown / button listing
      $action_args = array();
      $action_args['vv_attr_id'] = $vv_obj->id;

      foreach(($topLinks ?? []) as $t) {
        $perm = false;

        if(!empty($t['url'])) {
          // We are passing in a plain URL. Pass it along directly.
          // For the moment (v5.1+), this is only used to create a toplink to a Mostly Static Page.
          $perm = true;
          $t['link'] = $t['url'];
        } elseif(!empty($t['link']['controller'])) {
          // We're linking into a related model, which may or may not be in a plugin

          $linkModel = \Cake\Utility\Inflector::camelize($t['link']['controller']);

          if(!empty($t['link']['plugin'])) {
            $linkModel = \Cake\Utility\Inflector::camelize($t['link']['plugin'])
              . "." . $linkModel;
          }

          if(isset($vv_permissions[$linkModel][$t['link']['action']])) {
            $perm = $vv_permissions[$linkModel][$t['link']['action']];
          }

          // Inject a link to the current object ID
          $t['link']['?'][\App\Lib\Util\StringUtilities::entityToForeignKey($vv_obj)] = $vv_obj->id;
        } else {
          $perm = $vv_permissions[$t['link']['action']];

          // We need to inject $linkFilter, but not overwrite any existing query params
          if(!empty($t['link']['?'])) {
            $t['link']['?'] = array_merge($t['link']['?'], $linkFilter);
          } else {
            $t['link']['?'] = $linkFilter;
          }
        }

        if($perm && !empty($t['if'])) {
          // If there's a conditional on the field, test the entity
          $f = $t['if'];

          $perm = $vv_obj->$f();
        }

        if($perm) {
          $action_args['vv_actions'][] = $t;
          $key = array_key_last($action_args['vv_actions']);
          $action_args['vv_actions'][$key]['order'] = $this->Menu->getMenuOrder($t['order']);
          $action_args['vv_actions'][$key]['icon'] = $this->Menu->getMenuIcon($t['icon']);
          $action_args['vv_actions'][$key]['url'] = $t['link'] ?? '';
          $action_args['vv_actions'][$key]['class'] = $t['class'] ?? '';
          $action_args['vv_actions'][$key]['confirm'] = $t['confirm'] ?? '';
        }
      }

      // Delete
      if($vv_action != 'add' && !empty($vv_obj->id) && $vv_permissions['delete']) {
        $action_args['vv_actions'][] = [
          'order' => $this->Menu->getMenuOrder('Delete'),
          'icon' => $this->Menu->getMenuIcon('Delete'),
          'iconClass' => 'material-symbols-outlined',
          'url' => ['action' => 'delete', $vv_obj->id],
          'label' => __d('operation', 'delete'),
          'class' => 'deletebutton',
          'confirm' => [
            'method' => 'post',
            'dg_title' => __d('operation', 'delete'),
            'dg_body_txt' => __d('operation', 'delete.confirm', [$vv_obj->id]),
            'dg_confirm_btn' => __d('operation', 'delete')
          ]
        ];
      }

      if(!empty($action_args['vv_actions'])) {
        print '<div class="field-actions top-links">';
        print $this->element('menuAction', $action_args);
        print '</div>';
      }
    }
  ?>
</div>
  
<?php if(!$hasSubnav): ?>
  <?php /* Flash Messages are placed below the main title when there's no subnavigation. */ ?>
  <?= $this->element('flash', $flashArgs) ?>
<?php endif; ?>

<?php
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

/*
 * Views have a set() method that is analogous to the set() found in Controller objects.
 * Using set() from your view file will add the variables to the layout and elements
 * that will be rendered later.
 */

if(!empty($vv_alternate_template)) {
  // We have a special template. Include the template directly here. It does not include the standard form.
  // For the moment, this is used to provide a special view for Petitions. Note that the fields.inc
  // file may still include other configuration (e.g. for navigation).
  include($templatePath . DS . $vv_alternate_template);
} else {
  // We will output the standard Form (for add, edit, and view).
  // By default, the form will POST to the current controller.
  // Note we need to open the form for view so Cake will autopopulate values.
  if($this->Field->includesFileField($fields)) {
    // A file upload field is in the fields.inc configuration.
    // We will change the form encoding type to multipart/form-data.
    print $this->Form->create($vv_obj, ['type' => 'file']);
  } else {
    // We are using the standard form.
    print $this->Form->create($vv_obj);
  }
  
  // Output the visible form fields (or values, in the case of view):
  if(!empty($fields)) {
    print $this->element('form/unorderedList', ['vv_fields' => $fields]);
  }

  // Inject hidden fields set by the fields.inc file.
  // We don't need to output hidden fields for view actions.
  if($vv_action !== 'view') {
    if(!empty($hidden)) {
      foreach($hidden as $attr => $v) {
        print $this->Form->hidden($attr, ['value' => $v]);
      }
    }
    // Include the $linkId if present
    if(!empty($linkId)) {
      print $this->Form->hidden($vv_primary_link, ['value' => $linkId]);
    }
  }

  // Close the Form
  print $this->Form->end();
}

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

if($vv_action != 'add') {
  // Insert object metadata and changelog
  print $this->element('entityMetadata');
  print $this->element('changelog');
}