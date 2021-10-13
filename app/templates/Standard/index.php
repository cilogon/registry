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

// $this->name = Models
$modelsName = $this->name;
// $tablename = models
// XXX backport to match?
$tableName = \Cake\Utility\Inflector::tableize(\Cake\Utility\Inflector::singularize($this->name));

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
    $k = \Cake\Utility\Inflector::classify(\Cake\Utility\Inflector::pluralize(substr($c, 0, strlen($c)-3)));
    
    return __('registry.ct.'.$k, [1]);
  }
  
  // Look for a model specific key first
  $label = __('registry.fd.'.$modelsName.'.'.$c);
  
  if($label != 'registry.fd.'.$modelsName.'.'.$c) {
    return $label;
  }
  
  if($tz) {
    // If there is a timezone aware label, use that
    $label = __('registry.fd.'.$c.'.tz', [$tz]);
    
    if($label != 'registry.fd.'.$c.'.tz') {
      return $label;
    }
  }
  
  // Otherwise look for the general key
  return __('registry.fd.'.$c);
}
?>
<div class="titleNavContainer">
  <div class="pageTitle">
    <h1><?= $vv_title; ?></h1>
  </div>
  
  <?php
  if(!empty($banners)) {
    foreach($banners as $b): ?>  
  <div class="co-info-topbox">
    <em class="material-icons">info</em>
    <?php print $b; ?>
  </div>
  <?php endforeach; // $banners
  }
  ?>

  <?php if($vv_permissions['add']): ?>
    <ul id="topLinks">
      <li>
        <?= $this->Html->link(__('registry.op.add.a', __('registry.ct.'.$modelsName, [1])),
          ['action' => 'add', '?' => $linkFilter],
          ['class' => 'addbutton']); ?>
      </li>
    </ul>
  <?php endif; ?>
</div>
<?php if(!empty($indexBanners)): ?>
<?php foreach($indexBanners as $b): ?>
<div class="co-info-topbox">
  <em class="material-icons">info</em>
  <?php print $b; ?>
</div>
<?php endforeach; // $indexBanners ?>
<?php endif; // $indexBanners ?>
<div class="table-container">
  <table id="<?= $tableName . '-table'; ?>">
    <tr>
      <?php foreach($indexColumns as $col => $cfg): ?>
      <th>
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
      <th><?= __('registry.fd.action'); ?></th>
    </tr>
  <?php foreach($$tableName as $entity): ?>
    <tr>
      <?php foreach($indexColumns as $col => $cfg): ?>
      <td>
        <?php
          switch($cfg['type']) {
            case 'boolean':
              if(!empty($entity->$col) && $entity->$col) {
                print __('registry.en.'.$cfg['class'].'.1');
              } else {
                print __('registry.en.'.$cfg['class'].'.0');
              }
              break;
            case 'datetime':
              print $this->Time->nice($entity->$col, $vv_tz);
              break;
            case 'enum':
              if($entity->$col) {
                print __('registry.en.'.$cfg['class'].'.'.$entity->$col);
              }
              break;
            case 'fk':
              // Assuming $col is of the form foo_id, look to see if the corresponding
              // AutoViewVar $foos is set, and if so render the lookup value instead
              $f = null;
              if(preg_match('/^(.*?)_id$/', $col, $f)) {
                $avv = \Cake\Utility\Inflector::variable(\Cake\Utility\Inflector::pluralize($f[1]));
                
                if(!empty(${$avv}[$entity->$col])) {
                  // We found the viewvar (eg: $foos), and it has a corresponding value
                  // (eg: $foos[3]), so render it
                  print ${$avv}[$entity->$col];  // XXX filter_var?
                } else {
                  // No match, just render the value
                  print $entity->$col;
                }
              } else {
                // Just print the value
                print $entity->$col;
              }
              break;
            case 'link':
            case 'echo':
            default:
              // By default our label is the column value, but it might be overridden
              $label = $entity->$col;
              
              if(!empty($cfg['model']) && !empty($cfg['field'])) {
                $m = $cfg['model'];
                $f = $cfg['field'];
                
                if(!empty($entity->$m->$f)) {
                  $label = $entity->$m->$f;
                }
              }
              
              $linked = false;
              
              if($cfg['type'] == 'link') {
                foreach($linkActions as $a) {
                  // Does this user have permission for this action?
                  if($vv_permission_set[$entity->id][$a]) {
                    print $this->Html->link($label, ['action' => $a, $entity->id]);
                    $linked = true;
                    break 2;
                  }
                }
              }
              
              if(!$linked) {
                // Just echo the value
                print $label;
              }
              break;
  // XXX dates can be rendered as eg $entity->created->format(DATE_RFC850);
          }
        ?>
      </td>
      <?php endforeach; // $indexColumns ?>
      <td>
        <?php
          if($vv_permission_set[$entity->id]['edit']) {
            print $this->Html->link(
              __('registry.op.edit'),
              ['action' => 'edit', $entity->id],
              ['class' => 'editbutton']
            );
          } elseif($vv_permission_set[$entity->id]['view']) {
            print $this->Html->link(
              __('registry.op.view'),
              ['action' => 'view', $entity->id],
              ['class' => 'viewbutton']
            );
          }

          if($vv_permission_set[$entity->id]['delete']) {
// XXX this is throwing CSRF error even though delete button on edit-record page is working?
//     probably because this is using Form helper, but we're outside of a form?
            print $this->Form->postLink(
              __('registry.op.delete'),
              ['action' => 'delete', $entity->id],
  // XXX should be configurable which field we put in, maybe displayField?
              ['confirm' => __('registry.op.delete.confirm', [$entity->id]),
               'class'   => 'deletebutton']
            );
          }

          if(!empty($indexActions)) {
            // Insert additional actions as per the .inc file

// XXX this isn't quite the right test
//            if(isset($entity->status) && $entity->status == StatusEnum::Active) {
              foreach($indexActions as $a) {
                if($vv_permission_set[$entity->id][ $a['action'] ]) {
                  // If we have a .confirm text, use postLink instead

                  $confirmKey = 'registry.op.'.$a['action'].'.confirm';
                  $confirmTxt = __($confirmKey);

                  if($confirmTxt != $confirmKey) {
                    // We found the localized string

                    print $this->Form->postLink(
                      __('registry.op.' . $a['action']),
                      ['action' => $a['action'], $entity->id],
          // XXX should be configurable which field we put in, maybe displayField?
                      ['confirm' => __($confirmKey, [$entity->id]),
                       'class'   => $a['class']]
                    );
                  } else {
                    print $this->Html->link(
                      __('registry.op.' . $a['action']),
                      ['action' => $a['action'], $entity->id],
                      ['class' => $a['class']]
                    );
                  }
                }
              }
//            }
          }
        ?>
      </td>
    </tr>
  <?php endforeach; // $$tablename ?>
  </table>
</div>

<?php
  print $this->element("pagination");