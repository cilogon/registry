<?php
  /**
   * COmanage Registry MVEA Canvas Element
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
  
  // Get the object ID
  $objId = null;
  if(!empty($vv_obj)) {
    $objId = $vv_obj->id;
  }
  
  // Build the Add menu
  if(!empty($vv_add_menu_links)) {
    $action_args = array();
    $action_args['vv_attr_id'] = $objId;
    $action_args['vv_actions_type'] = 'mvea-add-menu';
    $action_args['vv_actions_title'] = __d('operation', 'add.attribute');
    $action_args['vv_actions_icon'] = 'add_circle';
    $action_args['vv_actions_icon_class'] = 'material-symbols-outlined';
    $action_args['vv_actions_class'] = 'mvea-add-menu';
    $actionOrderDefault = $this->Menu->getMenuOrder('Default');
    $addMenuActions = $vv_add_menu_links;
    foreach (($addMenuActions ?? []) as $a) {
      $actionOrder = !empty($a['order']) ? $a['order'] : $actionOrderDefault++;
      $actionIcon = !empty($a['icon']) ? $a['icon'] : $this->Menu->getMenuIcon('Default');
      $actionIconClass = !empty($a['iconClass']) ? $a['iconClass'] : '';
      $actionClass = !empty($a['class']) ? $a['class'] . ' nospin' : 'nospin';
      $actionUrl = [
        'controller' => $a['controller'],
        'action' => $a['action'],
        '?' => [
          $vv_entity_type . '_id' => $objId
        ]
      ];
      $actionLabel = __d('controller', Cake\Utility\Inflector::camelize($a['controller']), [1]);
      $actionDataAttrs = [['data-cm-mveatype', $a['controller']]];
      $action_args['vv_actions'][] = [
        'order' => $actionOrder,
        'icon' => $actionIcon,
        'iconClass' => $actionIconClass,
        'url' => $actionUrl,
        'class' => $actionClass,
        'label' => $actionLabel,
        'dataAttrs' => $actionDataAttrs
      ];
    }
  }
?>
<div id="mvea-canvas-title-container">
  <h2><?= __d('information','global.attributes') ?></h2>
  <?php if(!empty($vv_add_menu_links) && $vv_action == 'edit'): ?>
    <div id="mvea-add-menu-container" class="field-actions">
      <?= $this->element('menuAction', $action_args) ?>
    </div>
  <?php endif; ?>
</div>


<?php
  // Construct the canvas using JavaScript components.
  // Attributes to display.
  $attributes = $vv_mveas;
  
  // Count the number of widgets that will be displayed 
  $widgetCount = 0;
  foreach($attributes as $attr) {
    if(!empty($vv_obj[$attr])) {
      $widgetCount++;
    }
  }
?>
<div id="mvea-canvas" class="co-cards">
  <div id="mvea-canvas-attributes-js" class="row row-cols-1 g-4<?= ($widgetCount > 1) ? ' row-cols-md-2' : ''?>">
    <?php if($widgetCount == 0): ?>
        <div class="no-attributes"><?= __d('information','noattrs') ?></div>
    <?php else: ?>
      <?php 
        foreach ($attributes as $attr) {
          if (!empty(($vv_obj[$attr]))) {
            print $this->element(
              'mveaJs',
              [
                'htmlId' => 'mvea-canvas-' . $attr . '-js',
                'parentId' => $objId,
                'mveaType' => $attr,
                'entityType' => $vv_entity_type
              ]
            );
          }
        }
      ?>
    <?php endif; ?>
    <?= $this->element('mveaModal') ?>
  </div>
</div>