<?php
/*
 * COmanage Registry Action Menu
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

$actionsCount = count($vv_actions);
$actionsCountClass = $actionsCount > 0 ? ' actions-count-' . $actionsCount : '';
$actionsMenuClass = (!empty($vv_actions_class) ? $vv_actions_class : 'field-actions-menu') . ' dropdown dropleft' . $actionsCountClass;
$actionsMenuUid = md5($vv_attr_id);
$actionsType = !empty($vv_actions_type) ? $vv_actions_type : 'row-actions';
$actionsTitle = !empty($vv_actions_title) ? $vv_actions_title : '';
$actionsIcon = !empty($vv_actions_icon) ? $vv_actions_icon : 'settings';
?>

<div id="action-menu_<?= $actionsMenuUid; ?>"
     class="<?= $actionsMenuClass; ?>">
  <?php
  $linkparams = array(
    'id' => 'action-menu-content_' . $actionsMenuUid,
    'class' => 'nospin action-menu-toggle',
    'escape' => false,
    'data-bs-toggle' => 'dropdown',
    'aria-haspopup' => 'true',
    'aria-expanded' => 'false',
    'title' => __d('field', 'action')
  );
  print $this->Html->link(
    '<span class="material-icons">' . $actionsIcon . '</span> ' . $actionsTitle,
    'javascript:void(0);',
    $linkparams
  );

  // Sort the actions
  usort($vv_actions, function ($item1, $item2) {
    if ($item1['order'] == $item2['order']) return 0;
    return $item1['order'] < $item2['order'] ? -1 : 1;
  });
    
  ?>
  <ul id="action-list_<?= $actionsMenuUid; ?>" class="dropdown-menu nospin">
    <?php foreach($vv_actions as $action): ?>
      <?php 
        $actionDataAttrs = '';
        if(!empty($action['dataAttrs'])) {
          foreach($action['dataAttrs'] as $dataAttr) {
            $actionDataAttrs = ' ' . $dataAttr[0] . '="' . $dataAttr[1] . '"';
          }
        } 
      ?>
      <li class="action-list-item">
        <?php $actionCssClass = (!empty($action['class'])) ? "dropdown-item " . $action['class'] : "dropdown-item"; ?>
        <?php if(empty($action['onclick'])): ?>
          <a class="<?= $actionCssClass; ?>" href="<?= $action['url']; ?>"<?= !(empty($actionDataAttrs)) ? $actionDataAttrs : '' ?>>
            <?php if(!empty($action['icon'])): ?>
              <?php if(!empty($action['iconClass'])): ?>
                <em class="<?= $action['iconClass']; ?>"><?= $action['icon']; ?></em>
              <?php else: ?>
                <em class="material-icons"><?= $action['icon']; ?></em>
              <?php endif; ?>
            <?php endif; ?>
            <span class="action-link-text"><?= $action['label']; ?></span>
          </a>
        <?php else: ?>
          <?php
            // Build a link to a confirm dialog box. The confirm button of the dialog
            // will trigger the postButton built below the menu link.
            $actionUid = 'action-' . md5($action['onclick']['dg_url']);
            $dg_onclick = 'javascript:js_confirm_generic(\''
            . $action['onclick']['dg_bd_txt'] . '\',\''            // dialog body text
            . $actionUid . '\',\''                                 // ID of postButton element to click on confirm
            . $action['onclick']['dg_conf_btn'] . '\',\''          // dialog confirm button
            . $action['onclick']['dg_cancel_btn'] . '\',\''        // dialog cancel button
            . $action['onclick']['dg_title'] . '\',[\''            // dialog title
            . $action['onclick']['dg_bd_txt_repl_str']             // dialog body text replacement strings
            . '\']);';
          ?>
          <a class="<?= $actionCssClass; ?>" href="#" onclick="<?= $dg_onclick; ?>"  
             data-bs-toggle="modal" data-bs-target="#dialog">
            <?php if(!empty($action['icon'])): ?>
              <?php if(!empty($action['icon_class'])): ?>
                <em class="<?= $action['icon_class']; ?>"><?= $action['icon']; ?></em>
              <?php else: ?>
                <em class="material-icons"><?= $action['icon']; ?></em>
              <?php endif; ?>
            <?php endif; ?>
            <?= $action['label']; ?>
          </a>
          <?php // build the postButton element that will be clicked by the modal dialog: ?>
          <?= $this->Form->postButton($action['onclick']['dg_conf_btn'], 
              $action['onclick']['dg_post_btn_array'],['id' => $actionUid, 'class'   => 'hidden']); ?>
        <?php endif; ?>
      </li>
    <?php endforeach;?>
    <?php if(!empty($vv_bulk_actions) && $actionsType == 'top-links'): ?>
      <li id="bulk-edit-switch-container" class="action-list-item">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="bulk-edit-switch">
          <label class="form-check-label" for="bulk-edit-switch">Bulk edit</label>
        </div>
      </li>
    <?php endif; ?>
  </ul>
</div>