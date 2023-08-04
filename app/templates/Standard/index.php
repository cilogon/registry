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

// Do we have records for this index? This will be set to true during render of the
// first table data row if we do. Otherwise, we'll print out a "no records" message.
$recordsExist = false;

// Our default link actions, in order of preference, unless the column config overrides it
$linkActions = ['edit', 'view'];

// Read the index configuration ($indexColumns) and the associated actions for this model
$incFile = ROOT . DS . "templates" . DS . $modelsName . DS . "columns.inc";
if(!is_readable($incFile)) {
  throw new \InvalidArgumentException("$incFile is not readable");
}
include($incFile);

// $linkFilter is used for models that belong to a specific parent model (eg: co_id)
$linkFilter = [];

if(!empty($vv_primary_link) && !empty($this->request->getQuery($vv_primary_link))) {
  $linkFilter = [$vv_primary_link => $this->request->getQuery($vv_primary_link)];
}

// $flashArgs pass banner messages to the flash element container
$flashArgs = [];
if(!empty($indexBanners)) {
  $flashArgs['vv_index_banners'] = $indexBanners;
}
if(!empty($banners)) {
  $flashArgs['vv_banners'] = $banners;
}

// If subnavigation is present a supertitle and the subnavigation will be placed above
// the normal page title. The flash messages will be shown up there as well.
if(!empty($subnav)) {
  // Include the $flashArgs for the subnavigation element
  $subnav['flashArgs'] = $flashArgs;
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
  // Index view top link action item can be atomized using the user's identifier
  // since there will not always be an object id available. Like the case of add action
  if($vv_permissions['add']) {
    $action_args = array();
    $action_args['vv_attr_id'] =  $vv_user['username'];
    $action_args['vv_actions'] = array();

    // Include the Add link to actions menu unless suppressed by the page
    if(empty($suppressAddLink)) {
      $action_args['vv_actions'][] = [
        'order' => $this->Menu->getMenuOrder('Add'),
        'icon' => $this->Menu->getMenuIcon('Add'),
        'url' => [
          'controller' => $modelsName,
          'action' => 'add',
          '?' => $linkFilter
        ],
        'label' => __d('operation', 'add.a', __d('controller', $modelsName, [1])),
      ];
    }

    foreach(($topLinks ?? []) as $t) {
      if($vv_permissions[ $t['link']['action'] ]) {
        // We need to inject $linkFilter, but not overwrite any existing query params
        if(!empty($t['link']['?'])) {
          $t['link']['?'] = array_merge($t['link']['?'], $linkFilter);
        } else {
          $t['link']['?'] = $linkFilter;
        }

        $action_args['vv_actions'][] = [
          'order' => $this->Menu->getMenuOrder($t['order']),
          'icon' => $this->Menu->getMenuIcon($t['icon']),
          'url' => $t['link'],
          'label' => $t['label'],
          'class' => !empty($t['class']) ? $t['class'] : '',
          'confirm' => !empty($t['confirm']) ? $t['confirm'] : []
        ];
      }
    }
    // Declare the type of actions being sent so we can produce the bulk actions switch only for top-links.
    // XXX Bulk actions are currently being provided if a user has "add" permissions. Review this.
    $action_args['vv_actions_type'] = 'top-links';
    
    if(!empty($bulkActions)) {
      $action_args['vv_bulk_actions'] = $bulkActions;
    }
  }
  ?>

  <?php if(!empty($action_args['vv_actions'])): ?>
    <div class="field-actions top-links">
      <?= $this->element('menuAction', $action_args); ?>
    </div>
  <?php endif; ?>
</div>

<?php if(empty($subnav)): ?>
  <?php /* Flash Messages are placed below the main title when there's no subnavigation. */ ?>
  <?= $this->element('flash', $flashArgs); ?>
<?php endif; ?>
  
<!-- Search block -->
<?php if(isset($vv_searchable_attributes)): ?>
  <?php 
    $filterArgs = array();
    if(!empty($indexColumns)) {
      // The $indexColumns are passed to the filters for labels and sorting
      $filterArgs['indexColumns'] = $indexColumns;
    }
  ?>
  <?= $this->element('filter', $filterArgs); ?>
<?php endif; ?>

<!-- Index table -->
<div class="table-container">
  <?php 
    $indexTableClasses = 'index-table list-mode';
    if (!empty($rowActions)) {
      $indexTableClasses .= ' with-actions';  
    }
  ?>  
  <table id="<?= $tableName . '-table'; ?>" class="<?= $indexTableClasses; ?>">
    <thead>
      <tr>
        <?php if(!empty($rowActions)): ?>
          <th class="actions"></th>
        <?php endif; ?>
        <?php
          // The first heading will get the bulk select all checkbox.
          $firstHeading = true;
        ?>
        <?php foreach($indexColumns as $col => $cfg): ?>
          <th<?= !empty($cfg['cssClass']) ? ' class="' . $cfg['cssClass'] . '"' : ''; ?>>
            <?php
            if($firstHeading) {
              print '<span class="row-link-heading">';
            }
            
            $label = !empty($cfg['label']) ? $cfg['label'] : \App\Lib\Util\StringUtilities::columnKey($modelsName, $col, $vv_tz);
            
            if(isset($cfg['sortable']) && $cfg['sortable']) {
              if(is_string($cfg['sortable'])) {
                print $this->Paginator->sort($cfg['sortable'], $label);
              } else {
                print $this->Paginator->sort($col, $label);
              }
            } else {
              print $label;
            }

            if($firstHeading) {
              print '</span>';
            }
            ?>
            <?php if($firstHeading): ?>
              <div class="form-check bulk-action-checkbox-container">
                <input class="form-check-input" type="checkbox" value="" id="bulk-action-select-all">
                <label class="form-check-label" for="bulk-action-select-all">
                  <?= $label; ?>
                </label>
              </div>
              <?php
              // The first heading has been used.
              $firstHeading = false;
              ?>
            <?php endif; ?>
          </th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach($$tableName as $entity): ?>
      <tr>
      <?php if(!empty($rowActions)): ?>
      <?php
        // Action list for command menu dropdown / button listing
        $action_args = array();
        $action_args['vv_attr_id'] =  $entity->id;
        
        // Insert actions as per the .inc file
        // TODO: create an element or move this to MenuHelper so it can be used by topLinks as well as actions
        $actionOrderDefault = $this->Menu->getMenuOrder('Default');
        foreach($rowActions as $a) {
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
            $actionIconClass = !empty($a['iconClass']) ? $a['iconClass'] : '';
            $actionClass = !empty($a['class']) ? $a['class'] : '';
            $actionUrl = ['action' => $a['action'], $entity->id];
            $actionLabel = !empty($a['label']) ? $a['label'] : __d('operation', $a['action']);

            if (!empty($a['controller'])) {
              // We're linking into a related controller
              $actionLabel = !empty($a['label']) ? $a['label'] : __d('controller', Inflector::camelize(Inflector::pluralize($a['controller'])), [99]);
              $actionUrl = [
                'controller' => $a['controller'],
                'action' => $a['action'],
                '?' => [$tableFK => $entity->id]
              ];
            }

            // Generate the link text and urls:
            
            if(!empty($a['confirm'])) {
              // Gather the default confirmation body text. By convention this is named
              // [action].confirm in the operation.po file but can be overridden in the actions array.
              $confirmKey = $a['action'].'.confirm';
              $confirmTxt = __d('operation', $confirmKey, [$entity->id]);

              // Gather the dialog text - we need to expand these (here) so we can provide the default confirmTxt when appropriate
              $dialogBodyText = !empty($a['confirm']['dg_body_txt']) ? $a['confirm']['dg_body_txt'] : $confirmTxt;
              $dialogTitle = !empty($a['confirm']['dg_title']) ? $a['confirm']['dg_title'] : $actionLabel;
              $confirmButtonText = !empty($a['confirm']['dg_confirm_btn']) ? $a['confirm']['dg_confirm_btn'] : __d('operation','confirm');
              $cancelButtonText = !empty($a['confirm']['dg_cancel_btn']) ? $a['confirm']['dg_cancel_btn'] : __d('operation','cancel');
              $replacements = !empty($a['confirm']['dg_body_txt_replacements']) ? $a['confirm']['dg_body_txt_replacements'] : '';
              
              $action_args['vv_actions'][] = array(
                'order' => $actionOrder,
                'icon' =>  $actionIcon,
                'iconClass' => $actionIconClass,
                'url' => $actionUrl,
                'label' => $actionLabel,
                'class' => !empty($actionClass) ? $actionClass . ' nospin' : 'nospin',
                'confirm' => [
                  'dg_title' => $dialogTitle,
                  'dg_body_txt' => $dialogBodyText,
                  'dg_confirm_btn' => $confirmButtonText,
                  'dg_cancel_btn' => $cancelButtonText,
                  'dg_body_txt_replacements' => $replacements
                ]
              );
            } else {
              // Set the action link configuration
              $action_args['vv_actions'][] = array(
                'order' => $actionOrder,
                'icon' => $actionIcon,
                'iconClass' => $actionIconClass,
                'url' => $actionUrl,
                'label' => $actionLabel,
                'class' => $actionClass
              );
            }
          }
        }

        ?>  
      
        <td class="actions">
          <div class="field-actions">
            <?php if(!empty($action_args['vv_actions'])): ?>
              <?= $this->element('menuAction', $action_args); ?>
            <?php endif; ?>
          </div>
        </td>
      <?php endif; ?>
        
      <?php
        // We will set $isFirstLink to false after the first link is set. This is used to
        // establish the row-link class (and thus click action) for the row. There can be only one.
        // This is also used to determine which label will be assigned to the bulk action checkbox.
        $isFirstLink = true; 
      ?>  
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
                    $label = !empty($cfg['label']) ? $cfg['label'] : \App\Lib\Util\StringUtilities::columnKey($modelsName, $col, $vv_tz);
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

                // Output the bulk-action checkbox and label
                if($isFirstLink) {
                  print '<div class="form-check bulk-action-checkbox-container">';
                  print '<input class="form-check-input" type="checkbox" value="" id="bulk-action-id-' . $entity->id . '" data-entity-id="' . $entity->id . '">';
                  print '<label class="form-check-label" for="bulk-action-id-' . $entity->id . '">';
                  print $label;
                  print '</label>';
                  print '</div>';
                }
                
                // $linkActions can be overridden in columns.inc to apply to all
                // generated links, or $cfg['action'] can be set to apply only to
                // a specific field (column).
                $tryActions = (!empty($cfg['action']) ? [ $cfg['action'] ] : $linkActions);
                
                if($cfg['type'] == 'link') {
                  foreach($tryActions as $a) {
                    // Does this user have permission for this action?
                    if($vv_permission_set[$entity->id][$a]) {
                      // Handle $isFirstLink
                      $args = [];
                      $readOnlyIcon = '';
                      if($isFirstLink) {
                        $linkClass = 'row-link';
                        if($a == 'edit') {
                          $linkClass .= ' row-link-edit';
                        } elseif ($a == 'view') {
                          $linkClass .= ' row-link-view';
                          $readOnlyIcon = ' <em class="material-icons-outlined read-only-icon">edit_off</em>';
                        } else {
                          $linkClass .= ' row-link-' . $a;
                        }
                        $args = ['class' => $linkClass];
                        $isFirstLink = false;
                      }
                      // Output the link
                      if(!empty($readOnlyIcon)) {
                        print '<div class="read-only-link-container">';
                      }
                      print $this->Html->link($label, ['action' => $a, $entity->id], $args);
                      if(!empty($readOnlyIcon)) {
                        print $readOnlyIcon;
                        print '</div>';
                      }
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
                        // Handle $isFirstLink
                        $args = [];
                        $readOnlyIcon = '';
                        if($isFirstLink) {
                          $linkClass = 'row-link';
                          if($a == 'edit') {
                            $linkClass .= ' row-link-edit';
                          } elseif ($a == 'view') {
                            $linkClass .= ' row-link-view';
                            $readOnlyIcon = ' <em class="material-icons-outlined read-only-icon">edit_off</em>';
                          }
                          $args = ['class' => $linkClass];
                          $isFirstLink = false;
                        }
                        // Output the link
                        if(!empty($readOnlyIcon)) {
                          print '<div class="read-only-link-container">';
                        }
                        print $this->Html->link($label, ['controller' => $c, 'action' => $a, $entity->$m->id], $args);
                        if(!empty($readOnlyIcon)) {
                          print $readOnlyIcon;
                          print '</div>';
                        }  
                        $linked = true;
                        break 2;
                      }
                    }
                  }
                }
                
                if(!$linked) {
                  // Just echo the value
                  print $label;
                  $isFirstLink = false;
                }
                break;
            }
          ?>
        </td>
        <?php endforeach; // $indexColumns ?>
      </tr>
      <?php $recordsExist = true; ?>
    <?php endforeach; // $$tablename ?>
    <?php if(!$recordsExist): ?>
      <tr><td colspan="<?= count($indexColumns); ?>"><?= __d('information','global.records.none') ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?= $this->element("pagination");