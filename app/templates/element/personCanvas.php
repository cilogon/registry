<?php
  /**
   * COmanage Registry Person Canvas Element
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
  if (!empty($vv_obj)) {
    // This will work for most top-level edit views
    // XXX we might produce the $vv_primary_link for edit views so the approach below could be deprecated
    $curId = $vv_obj->id;
    if(!empty($vv_person_id)) {
      $curId = $vv_person_id;
    } elseif(!empty($vv_primary_link_id)) {
      $curId = $vv_primary_link_id;
    }
  }
  
  // Build the Add menu
  $action_args = array();
  $action_args['vv_attr_id'] =  $curId;
  $action_args['vv_actions_type'] = 'person-actions-add-menu';
  $action_args['vv_actions_title'] = __d('operation','add');
  $action_args['vv_actions_icon'] = 'add_circle';
  $action_args['vv_actions_class'] = 'person-actions-add-menu';
  $actionOrderDefault = $this->Menu->getMenuOrder('Default');
  $personAddMenuActions = $vv_add_menu_links;
  foreach(($personAddMenuActions ?? []) as $a) {
    $actionOrder = !empty($a['order']) ? $a['order'] : $actionOrderDefault++;
    $actionIcon = !empty($a['icon']) ? $a['icon'] : $this->Menu->getMenuIcon('Default');
    $actionIconClass = !empty($a['iconClass']) ? $a['iconClass'] : '';
    $actionClass = !empty($a['class']) ? $a['class'] : '';
    $actionUrl = $this->Url->build(
      [
        'controller' => $a['controller'],
        'action' => $a['action'],
        '?' => [
          'person_id' => $curId
        ]
      ]
    );
    $actionLabel = __d('controller', Cake\Utility\Inflector::camelize($a['controller']), [1]);
    $action_args['vv_actions'][] = [
      'order' => $actionOrder,
      'icon' => $actionIcon,
      'iconClass' => $actionIconClass,
      'url' => $actionUrl,
      'class' => $actionClass,
      'label' => $actionLabel
    ];
  }
?>
<div class="field-actions person-actions-add-menu-container">
  <?= $this->element('menuAction', $action_args) ?>
</div>


<?php
  // Construct the canvas using JavaScript components.
  // Person Attributes to display.
  $attributes = [
    'names',
    'email_addresses',
    'identifiers',
    'ad_hoc_attributes',
    'addresses',
    'telephone_numbers',
    'urls',
    'pronouns'
  ];
  
  // Count the number of widgets that will be displayed 
  $widgetCount = 0;
  foreach($attributes as $attr) {
    if(!empty($vv_obj[$attr])) {
      $widgetCount++;
    }
  }
  
  $objId = null;
  if(!empty($vv_obj)) {
    $objId = $vv_obj->id;
  }
?>
<div id="person-canvas" class="co-cards">
  <!-- Person Attributes -->
  <div id="person-canvas-attributes-js" class="row row-cols-1 g-4 <?= ($widgetCount > 1) ? 'row-cols-md-2' : ''?>">
    <?php
      foreach($attributes as $attr) {
        if(!empty(($vv_obj[$attr]))) {
          print $this->element(
            'mveaJs',
            [
              'htmlId' => 'person-canvas-' . $attr . '-js',
              'parentId' => $objId,
              'mveaType' => $attr,
              'entityType' => 'person'
            ]
          );
        }
      }
      // XXX Add the DOB as its own special card.
    ?>
  </div>
</div>