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
?>
<div id="navigation-drawer">
  <nav id="navigation" aria-label="main menu">
    <ul id="main-menu">
      <?php
        if(!empty($vv_cur_co)) {
          // In Registry PE, there is no more Platform Administration menu, so there
          // is no menu context without a current CO. (The Platform Administration
          // menu is now part of the COmanage CO configuration.)
    
          $menuItems = [
            [
              'permission' => 'people',
              'controller' => 'people',
              'action'     => 'index',
              'icon'       => 'person',
              'dropdown'   => 'true',
              'label'      => __d('menu', 'co.people') //,
              //'panel'      => 'people' // XXX Uncomment this panel setting to enable panel menus
            ],
            [
              'permission' => 'groups',
              'icon'       => 'group',
              'dropdown'   => 'true',
              'label' => __d('menu', 'co.structure'),
              'panel'      => 'structure'
            ],
            [
              'permission' => 'configuration',
              'controller' => 'dashboards',
              'action'     => 'configuration',
              'icon'       => 'cached',
              'dropdown'   => 'true',
              'label' => __d('menu', 'co.lifecycle')
            ],
            [
              'permission' => 'configuration',
              'controller' => 'dashboards',
              'action'     => 'configuration',
              'icon'       => 'hub',
              'dropdown'   => 'true',
              'label' => __d('menu', 'co.connections')
            ],
            [
              'permission' => 'configuration',
              'controller' => 'dashboards',
              'action'     => 'configuration',
              'icon'       => 'play_circle_outline',
              'dropdown'   => 'true',
              'label' => __d('menu', 'co.operations')
            ],
            [
              'permission' => 'configuration',
              'controller' => 'dashboards',
              'action'     => 'configuration',
              'icon'       => 'settings',
              'label' => __d('menu', 'co.configuration')
            ]
          ];
    
          foreach($menuItems as $m) {
            if(!isset($m['permission']) || $vv_menu_permissions[ $m['permission'] ]) {
              $linkContent = '<em class="material-icons" aria-hidden="true">' . $m['icon'] . '</em>'
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
  <button id="co-menu-collapse">
    <em class="material-icons-outlined co-menu-collapse-icon">
      expand_circle_down
    </em>
    <div class="co-menu-collapse-text">close</div>
  </button>
</div>
