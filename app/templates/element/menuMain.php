<?php
/*
 * COmanage Registry Main Menu Bar
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

// The following menu will only render if we have a user and CO (see default.ctp)
// and if the user has any menu to render.   

use App\Lib\Enum\ApplicationStateEnum;

$userHasMenu = in_array(true, $vv_menu_permissions, true );
$drawerState = $this->ApplicationState->getValue(ApplicationStateEnum::UiDrawerState, 'open');
$appStateId = $this->ApplicationState->getId(ApplicationStateEnum::UiDrawerState);

?>
<?php if($userHasMenu): ?>
<div id="navigation-drawer"
     data-coid="<?= $vv_cur_co->id ?? '' ?>"
     data-appstateid="<?= $appStateId ?>"
     data-stateattr="<?= ApplicationStateEnum::UiDrawerState?>"
     data-webroot="<?= $this->request->getAttribute('webroot') ?>"
     data-username="<?= $vv_user['username'] ?? '' ?>"
     data-personid="<?= $vv_person_id ?? '' ?>"
     class="<?= $drawerState ?>">
  <nav id="navigation" aria-label="<?= __d('menu','menu.main') ?>">
    <ul id="main-menu">
      <?php
        if(!empty($vv_cur_co)) {
          // In Registry PE, there is no more Platform Administration menu, so there
          // is no menu context without a current CO. (The Platform Administration
          // menu is now part of the COmanage CO configuration.)
    
          $menuItems = [
            [
              'permission' => 'people',
              'icon'       => 'person',
              'label'      => __d('menu', 'co.people'),
              'panel'      => 'people'
            ],
            [
              'permission' => 'groups',
              'icon'       => 'group',
              'label' => __d('menu', 'co.structure'),
              'panel'      => 'structure'
            ],
            [
              'permission' => 'configuration',
              'icon'       => 'cached',
              'label' => __d('menu', 'co.connections'),
              'panel'      => 'connections'
            ],
            [
              'permission' => 'configuration',
              'icon'       => 'play_circle',
              'iconClass' => 'material-symbols-outlined',
              'label' => __d('menu', 'co.operations'),
              'panel'      => 'operations'
            ],
            [
              'permission' => 'configuration',
              'icon'       => 'settings',
              'label' => __d('menu', 'co.configuration'),
              'panel'      => 'config'
            ]
          ];
    
          foreach($menuItems as $m) {
            if(!isset($m['permission']) || $vv_menu_permissions[ $m['permission'] ]) {
              $iconClass = !empty($m['iconClass']) ? $m['iconClass'] : 'material-symbols';
              $linkContent = '<em class="' . $iconClass . '" aria-hidden="true">' . $m['icon'] . '</em>'
                . '<span class="menu-title">' . $m['label'] . '</span>';
    
              print '<li>';
              if(empty($m['panel'])) {
                print $this->Html->link(
                  $linkContent,
                  ['plugin'       => null,
                   'controller'   => $m['controller'],
                   'action'       => $m['action'],
                   '?'            => [
                     'co_id' => $vv_cur_co->id
                   ]],
                  ['escape' => false, 'title' => $m['label']]
                );  
              } else {
                // include the menu panel
                print $this->Html->link(
                  $linkContent, '#',
                  ['escape' => false, 'title' => $m['label'], 'class' => 'menu-panel-toggle nospin']
                );
                print $this->element('menuPanel', ['panel' => $m['panel']]);
              }
              print '</li>';
            }
          }
        }
      ?>
    </ul>
  </nav>
  <nav id="navigation-bottom" aria-label="<?= __d('menu','menu.advanced') ?>">
    <?php if(!empty($vv_cur_co) && $vv_menu_permissions['configuration']): ?>
      <div id="all-button-container">
        <?= $this->Html->link(
          __d('menu', 'co.all'),
          ['plugin'       => null,
           'controller'   => 'dashboards',
           'action'       => 'configuration',
           '?'            => ['co_id' => $vv_cur_co->id]],
          [
            'id' => 'all-button',
            'class' => 'btn btn-primary btn-sm'
          ]
        );?>
      </div>
    <?php endif; ?>
    <button id="co-menu-collapse" aria-label="<?= __d('menu','menu.toggle') ?>">
      <em class="material-symbols-outlined co-menu-collapse-icon" aria-hidden="true">
        expand_circle_down
      </em>
      <div class="co-menu-collapse-text">close</div>
    </button>
  </nav>
</div>
<?php endif; ?>