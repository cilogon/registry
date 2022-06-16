<?php
/**
 * COmanage Registry Standard Index Template
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

/*
 * XXX Replace this file with the version from COMMON once pagination is fixed
 * - need to pull pagination since COs now has 21 entries (registrytest)
 */

declare(strict_types = 1);

//use \App\Lib\Enum\StatusEnum;
use \Cake\Utility\Inflector;

// $this->name = Models
$modelsName = $this->name;
// $tablename = models
// XXX backport to match?
$tableName = Inflector::tableize(Inflector::singularize($this->name));
$tableFK = Inflector::singularize($tableName) . "_id";

// Do we have records for this index? This will be set to true during render if we do.
// Otherwise, we'll print out a "no records" message.
$recordsExist = false;

// Our default link actions, in order of preference, unless the column config overrides it
$linkActions = ['edit', 'view'];

// Read the index configuration ($indexColumns) for this model
include(ROOT . DS . "templates" . DS . $modelsName . DS . "columns.inc");

// $linkFilter is used for models that belong to a specific parent model (eg: co_id)
$linkFilter = [];

if(!empty($vv_primary_link) && !empty($this->request->getQuery($vv_primary_link))) {
  $linkFilter = [$vv_primary_link => $this->request->getQuery($vv_primary_link)];
}

function _column_key($modelsName, $c, $tz=null) {
  if(strpos($c, "_id", strlen($c)-3)) {
    // Key is of the form field_id, use .ct label instead
    $k = Inflector::camelize(Inflector::pluralize(substr($c, 0, strlen($c)-3)));
    
    return __d('controller', $k, [1]);
  }
  
  // Look for a model specific key first
  $label = __d('field', $modelsName.'.'.$c);
  
  if($label != $modelsName.'.'.$c) {
    return $label;
  }
  
  if($tz) {
    // If there is a timezone aware label, use that
    $label = __d('field', $c.'.tz', [$tz]);
    
    if($label != $c.'.tz') {
      return $label;
    }
  }
  
  // Otherwise look for the general key
  return __d('field', $c);
}
?>
<div class="titleNavContainer">
  <div class="pageTitle">
    <h1><?= $vv_title; ?></h1>
  </div>

  <?php if($vv_permissions['add']): ?>
    <ul id="topLinks">
      <li>
        <?= $this->Html->link('<em class="material-icons" aria-hidden="true">add_circle</em> ' .
            __d('operation', 'add.a', __d('controller', $modelsName, [1])),
          ['action' => 'add', '?' => $linkFilter],
          ['escape' => false]); ?>
      </li>
      <?php
        if(!empty($topLinks)) {
          foreach($topLinks as $t) {
            if($vv_permissions[ $t['link']['action'] ]) {
              // We need to inject $linkFilter, but not overwrite any existing query params
              if(!empty($t['link']['?'])) {
                $t['link']['?'] = array_merge($t['link']['?'], $linkFilter);
              } else {
                $t['link']['?'] = $linkFilter;
              }
              
              print '<li>' .
                $this->Html->link(
                  '<em class="material-icons" aria-hidden="true">' . $t['icon']. '</em> ' . $t['label'],
                  $t['link'],
                  ['escape' => false, 'class' => $t['class']]
                ) . '
              </li>';
            }
          }
        }
      ?>
    </ul>
  <?php endif; ?>
</div>
<?php if(!empty($indexBanners)): ?>
  <?php foreach($indexBanners as $b): ?>
    <div class="co-info-topbox">
      <em class="material-icons">info</em>
      <?= $b; ?>
    </div>
  <?php endforeach; // $indexBanners ?>
<?php endif; // $indexBanners ?>

<?php if(!empty($banners)): ?>
  <?php foreach($banners as $b): ?>
    <div class="co-info-topbox">
      <em class="material-icons">info</em>
      <?= $b; ?>
    </div>
  <?php endforeach; // $banners ?>
<?php endif; // $banners ?>

<!-- Search block -->
<?php if(!empty($enableSearch)): ?>
  <?= $this->element('search'); ?>
<?php endif; // $enableSearch ?>

<!-- Index table -->
<div class="table-container">
  <table id="<?= $tableName . '-table'; ?>">
    <tr>
      <?php foreach($indexColumns as $col => $cfg): ?>
      <th<?= !empty($cfg['cssClass']) ? ' class="' . $cfg['cssClass'] . '"' : ''; ?>>
        <?php
        $label = !empty($cfg['label']) ? $cfg['label'] : _column_key($modelsName, $col, $vv_tz);

        if(isset($cfg['sortable']) && $cfg['sortable']) {
          if(is_string($cfg['sortable'])) {
            print $this->Paginator->sort($cfg['sortable'], $label);
          } else {
            print $this->Paginator->sort($col, $label);
          }
        } else {
          print $label;
        }
        ?>
      </th>
      <?php endforeach; ?>
      <th class="actions"><?= __d('field', 'action'); ?></th>
    </tr>
  <?php foreach($$tableName as $entity): ?>
    <tr>
      <?php foreach($indexColumns as $col => $cfg): ?>
      <td<?= !empty($cfg['cssClass']) ? ' class="' . $cfg['cssClass'] . '"' : ''; ?>>
        <?php
          $suffix = "";
          
          if(!empty($cfg['append'])) {
            // The value is a method on the entity that returns a string to
            // append to the label
            $f = $cfg['append'];
            
            $str = $entity->$f();
            
            if(!empty($str)) {
              // For our first pass, we insert a comma, but this might not generalize
              $suffix = ", " . $str;
            }
          }
          
          switch($cfg['type']) {
            case 'boolean':
              if(!empty($entity->$col) && $entity->$col) {
                print __d('enumeration', $cfg['class'].'.1') . $suffix;
              } else {
                print __d('enumeration', $cfg['class'].'.0') . $suffix;
              }
              break;
            case 'button':
              if(!empty($entity->$col)) {
                $buttonAttrs = [];
                $buttonText = $entity->$col;
                if(!empty($cfg['button']['attrs'])) {
                  $buttonAttrs = $cfg['button']['attrs'];
                }
                $buttonAttrs['type'] = 'button';
                if(!empty($cfg['button']['text']) && $cfg['button']['text'] != 'fieldVal') {
                  $buttonText = $cfg['button']['text'];
                }
                if(!empty($cfg['truncate']) && is_int($cfg['truncate'])) {
                  // We check for $truncate + 1 because there's no point trimming
                  // the last character if we're just going to replace it with ...
                  $buttonText = (strlen($buttonText) > $cfg['truncate'] + 1) ? substr($buttonText,0,$cfg['truncate']).'...' : $buttonText;
                }
                if(!empty($cfg['button']['popover'])) {
                  if($cfg['button']['popover'] == 'fieldVal') {
                    $buttonAttrs['data-bs-content'] = $entity->$col;
                  } else {
                    $buttonAttrs['data-bs-content'] = $cfg['button']['popover'];
                  }
                  $label = !empty($cfg['label']) ? $cfg['label'] : _column_key($modelsName, $col, $vv_tz);
                  $buttonAttrs['title'] = $label;
                  $buttonAttrs['data-bs-toggle'] = 'popover';
                  $buttonAttrs['data-bs-container'] = 'body';
                  $buttonAttrs['data-bs-placement'] = 'top';
                  $buttonAttrs['data-bs-animation'] = 'false';
                }
                print $this->Form->button($buttonText, $buttonAttrs);
              }
              break;
            case 'closure':
              $fn = $cfg['function'];
              print $fn($entity);
              break;
            case 'datetime':
  // XXX dates can be rendered as eg $entity->created->format(DATE_RFC850);
              if(!empty($entity->$col)) {
                print $this->Time->nice($entity->$col, $vv_tz) . $suffix;
              }
              break;
            case 'enum':
              if($entity->$col) {
                // XXX Need to add badging - see index.php in Match
                print __d('enumeration', $cfg['class'].'.'.$entity->$col) . $suffix;
              }
              break;
            case 'fk':
              // Assuming $col is of the form foo_id, look to see if the corresponding
              // AutoViewVar $foos is set, and if so render the lookup value instead
              $f = null;
              if(preg_match('/^(.*?)_id$/', $col, $f)) {
                $avv = Inflector::variable(Inflector::pluralize($f[1]));
                
                if(!empty(${$avv}[$entity->$col])) {
                  // We found the viewvar (eg: $foos), and it has a corresponding value
                  // (eg: $foos[3]), so render it
                  print ${$avv}[$entity->$col]. $suffix;  // XXX filter_var?
                } else {
                  // No match, just render the value
                  print $entity->$col. $suffix;
                }
              } else {
                // Just print the value
                print $entity->$col. $suffix;
              }
              break;
            case 'link':
            case 'relatedLink':
            case 'echo':
            default:
              // By default our label is the column value, but it might be overridden
              $label = $entity->$col . $suffix;
              
              if(!empty($cfg['model']) && !empty($cfg['field'])) {
                $m = $cfg['model'];
                $f = $cfg['field'];
                
                if(!empty($cfg['submodel'])) {
                  // We have a related model, eg actor_person.primary_name
                  $sm = $cfg['submodel'];
                  
                  if(!empty($entity->$m->$sm->$f)) {
                    $label = $entity->$m->$sm->$f . $suffix;
                  }
                } else {
                  if(!empty($entity->$m->$f)) {
                    $label = $entity->$m->$f . $suffix;
                  }
                }
              }
              
              $linked = false;
              
              // $linkActions can be overridden in columns.inc to apply to all
              // generated links, or $cfg['action'] can be set to apply only to
              // a specific field (column).
              $tryActions = (!empty($cfg['action']) ? [ $cfg['action'] ] : $linkActions);
              
              if($cfg['type'] == 'link') {
                foreach($tryActions as $a) {
                  // Does this user have permission for this action?
                  if($vv_permission_set[$entity->id][$a]) {
                    print $this->Html->link($label, ['action' => $a, $entity->id]);
                    $linked = true;
                    break 2;
                  }
                }
              } elseif($cfg['type'] == 'relatedLink') {
                $m = $cfg['model'];
                
                if(!empty($entity->$m->id)) {
                  // We need the controller for the related entity, however $m
                  // might be an alias and $entity->getSource() returns the
                  // aliased class name. So we use PHP's get_class instead.
                  $c = Inflector::tableize(substr(get_class($entity->$m), strrpos(get_class($entity->$m), '\\')+1));
                  
                  foreach($tryActions as $a) {
                    // Does this user have permission for this action?
// XXX we actually need to know the permissions on the target (ie: actor person)
                    if(true ||
                       $vv_permission_set[$entity->id][$a]) {
                      print $this->Html->link($label, ['controller' => $c, 'action' => $a, $entity->$m->id]);
                      $linked = true;
                      break 2;
                    }
                  }
                }
              }
              
              if(!$linked) {
                // Just echo the value
                print $label;
              }
              break;
          }
        ?>
      </td>
      <?php endforeach; // $indexColumns ?>
      <td class="actions">
        <?php

          // Action list for command menu dropdown / button listing
          $action_args = array();
          $action_args['vv_attr_id'] =  $entity->id;
          
          // Edit
          if($vv_permission_set[$entity->id]['edit']) {
            $action_args['vv_actions'][] = array(
              'order' => $this->Menu->getMenuOrder('Edit'),
              'icon' => $this->Menu->getMenuIcon('Edit'),
              'url' => $this->Url->build(['action' => 'edit', $entity->id]),
              'label' => __d('operation', 'edit')
            );
          } elseif($vv_permission_set[$entity->id]['view']) {
            $action_args['vv_actions'][] = array(
              'order' => $this->Menu->getMenuOrder('View'),
              'icon' => $this->Menu->getMenuIcon('View'),
              'url' => $this->Url->build(['action' => 'view', $entity->id]),
              'label' => __d('operation', 'view')
            );
          }

          // Insert additional actions as per the .inc file
          if(!empty($indexActions)) {
// TODO: create an element or move this to MenuHelper so it can be used by topLinks as well as indexActions 
// XXX this isn't quite the right test
//          if(isset($entity->status) && $entity->status == StatusEnum::Active) {
              $actionOrderDefault = $this->Menu->getMenuOrder('Default');
              foreach($indexActions as $a) {
                $ok = false;
                if(!empty($a['controller'])) {
                  $tableName = Inflector::camelize($a['controller']);
                  
                  if(isset($vv_permission_set[$entity->id][$tableName][ $a['action'] ])) {
                    $ok = $vv_permission_set[$entity->id][$tableName][ $a['action'] ];
                  }
                } else {
                  $ok = $vv_permission_set[$entity->id][ $a['action'] ];
                }
                
                if($ok && !empty($a['if'])) {
                  // If there's a conditional on the field, test the entity
                  $f = $a['if'];
                  
                  $ok = $entity->$f();
                }
                
                if($ok) {
                  $actionOrder = !empty($a['order']) ? $a['order'] : $actionOrderDefault++;
                  $actionIcon = !empty($a['icon']) ? $a['icon'] : $this->Menu->getMenuIcon('Default');
                  $actionClass = !empty($a['class']) ? $a['class'] : '';
                  $actionUrl = ''; 
                  $actionLabel = '';
                  $actionOnClick = []; // used for confirmation dialog 

                  // Generate the link text and urls:

                  // If we have a .confirm text, we need to generate a confirm dialog box
                  $confirmKey = $a['action'].'.confirm';
                  $confirmTxt = __d('operation', $confirmKey);

                  if($confirmTxt != $confirmKey) {
                    // We found the localized string
                    $actionPostBtnArray = ['action' => $a['action'], $entity->id];
                    $actionUrl = $this->Url->build(['action' => $a['action'], $entity->id]);
                    // XXX should be configurable which field we put in, maybe displayField?
                    $action_args['vv_actions'][] = array(
                      'order' => $actionOrder,
                      'icon' =>  $actionIcon,
                      'url' => 'javascript:void(0);',
                      'label' => __d('operation', $a['action']),
                      'class' => !empty($actionClass) ? $actionClass . ' nospin' : 'nospin',
                      'onclick' => array(
                        'dg_bd_txt' => __d('operation', $confirmKey, [$entity->id]), // dialog body text
                        'dg_post_btn_array' => $actionPostBtnArray,                 // postButton array for building the postButton
                        'dg_url' => $actionUrl,                                     // action url for building a unique ID
                        'dg_conf_btn' => __d('operation', 'confirm'),  // dialog confirm button text
                        'dg_cancel_btn' => __d('operation', 'cancel'), // dialog cancel button text
                        'dg_title' => __d('operation', 'confirm'),     // dialog box title
                        'dg_bd_txt_repl_str' => ''                                  // dialog body text replacement strings 
                      ),
                    );
                  } elseif(!empty($a['controller'])) {
                    // We're linking into a related controller
                    $actionLabel = __d('controller', Inflector::camelize(Inflector::pluralize($a['controller'])), [99]);
                    $actionUrl = $this->Url->build(
                      ['controller' => $a['controller'],
                       'action'     => $a['action'],
                       '?' => [ $tableFK => $entity->id] ]
                    );
                  } else {
                    $actionLabel = __d('operation', $a['action']); 
                    $actionUrl = $this->Url->build(['action' => $a['action'], $entity->id]);
                  }

                  // If a specific label is sent in the config, use it instead
                  if(!empty($a['label'])) {
                    $actionLabel = $a['label'];
                  }

                  // Set the action link configuration
                  $action_args['vv_actions'][] = array(
                    'order' => $actionOrder,
                    'icon' => $actionIcon,
                    'url' => $actionUrl,
                    'label' => $actionLabel,
                    'class' => $actionClass,
                    'onclick' => $actionOnClick
                  );    
                }
              }
              
//            }
          }

          // Delete
          if($vv_permission_set[$entity->id]['delete']) {
            $actionPostBtnArray = ['action' => 'delete', $entity->id];
            $actionUrl = $this->Url->build(['action' => 'delete', $entity->id]);
            $action_args['vv_actions'][] = array(
              'order' => $this->Menu->getMenuOrder('Delete'),
              'icon' =>  $this->Menu->getMenuIcon('Delete'),
              'url' => 'javascript:void(0);',
              'label' => __d('operation', 'delete'),
              'class' => 'deletebutton nospin',
              'onclick' => array(
                'dg_bd_txt' => __d('operation', 'delete.confirm', [$entity->id]),
                'dg_post_btn_array' => $actionPostBtnArray,
                'dg_url' => $actionUrl,
                'dg_conf_btn' => __d('operation', 'remove'),
                'dg_cancel_btn' => __d('operation', 'cancel'),
                'dg_title' => __d('operation', 'remove'),
                'dg_bd_txt_repl_str' => ''
              ),
            );
          }

          if(!empty($action_args['vv_actions'])) {
            print '<div class="field-actions">';
            print $this->element('menuAction', $action_args);
            print '</div>';
          }
          
        ?>
      </td>
    </tr>
    <?php $recordsExist = true; ?>
  <?php endforeach; // $$tablename ?>
  <?php if(!$recordsExist): ?>
    <tr><td colspan="<?= count($indexColumns); ?>"><?= __d('information','global.records.none') ?></td></tr>
  <?php endif; ?>
  </table>
</div>

<?php
  print $this->element("pagination");