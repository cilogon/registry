<?php
/**
 * COmanage Registry Dashboards Configuration View
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
?>

<div class="page-title-container">
  <div class="page-title page-title-features">
    <h1><?= $vv_title ?></h1>
    <div class="comanage-version">
      <?php print __('registry.version', chop(file_get_contents(CONFIG . "VERSION"))); ?>
    </div>
  </div>
</div>

<section class="inner-content">
  <?php if(!empty($vv_platform_menu_items)): ?>
    <h2 class="config-subtitle"><?= __d('menu','co.configuration.panel.platform') ?></h2>
    <p class="menu-panel-links-desc"><?= __d('menu','co.configuration.panel.platform.desc') ?></p>
    <ul id="platform-menu" class="config-menu">
      <?php foreach($vv_platform_menu_items as $label => $cfg): ?>
        <li>
          <?php 
            $linkContent =  '<em class="material-icons" aria-hidden="true">' . $cfg['icon'] . '</em>'
              . '<span class="menu-title">' . $label . '</span>';
            print $this->Html->link(
              $linkContent,
              ['plugin'     => null,
               'controller' => $cfg['controller'],
               'action'     => $cfg['action']],
              ['escape' => false]
            ); 
          ?>
        </li>
      <?php endforeach; // $vv_configuration_menu_items ?>
    </ul>

    <h2 class="config-subtitle mb-2"><?= __d('menu','co.configuration') ?></h2>
    <p class="menu-panel-links-desc"><?= __d('menu','co.configuration.desc') ?></p>
  <?php endif; // $vv_platform_menu_items ?>
  
  <?php if(empty($vv_platform_menu_items)): ?>
    <h2 class="config-subtitle mb-2"><?= __d('menu','co.configuration') ?></h2>
  <?php endif; ?>
  
  <ul id="configuration-menu" class="config-menu">
    <?php foreach($vv_configuration_menu_items as $label => $cfg): ?>
      <li>
        <?php 
          $linkContent =  '<em class="material-icons" aria-hidden="true">' . $cfg['icon'] . '</em>'
            . '<span class="menu-title">' . $label . '</span>';
          print $this->Html->link(
            $linkContent,
            ['plugin'     => null,
             'controller' => $cfg['controller'],
             'action'     => $cfg['action'],
             '?'          => ['co_id' => $vv_cur_co->id]],
             ['escape' => false]
            ); 
        ?>
      </li>
    <?php endforeach; // $vv_configuration_menu_items ?>
  </ul>
  
  <h2 class="config-subtitle mb-2"><?= __d('menu','co.registries') ?></h2>
  <ul id="registries-menu" class="config-menu">
    <?php foreach($vv_registries_menu_items as $label => $cfg): ?>
      <li>
        <?php
          $linkContent =  '<em class="material-icons" aria-hidden="true">' . $cfg['icon'] . '</em>'
            . '<span class="menu-title">' . $label . '</span>';
          print $this->Html->link(
            $linkContent,
            ['plugin'     => null,
             'controller' => $cfg['controller'],
             'action'     => $cfg['action'],
             '?'          => ['co_id' => $vv_cur_co->id]],
            ['escape' => false]
          );
        ?>
      </li>
    <?php endforeach; // $vv_registries_menu_items ?>
  </ul>

  <h2 class="config-subtitle mb-2"><?= __d('menu','co.artifacts') ?></h2>
  <ul id="artifacts-menu" class="config-menu">
    <?php foreach($vv_artifacts_menu_items as $label => $cfg): ?>
      <li>
        <?php
          $linkContent =  '<em class="material-icons" aria-hidden="true">' . $cfg['icon'] . '</em>'
            . '<span class="menu-title">' . $label . '</span>';
          print $this->Html->link(
            $linkContent,
            ['plugin'     => null,
             'controller' => $cfg['controller'],
             'action'     => $cfg['action'],
             '?'          => ['co_id' => $vv_cur_co->id]],
            ['escape' => false]
          );
        ?>
      </li>
    <?php endforeach; // $vv_artifacts_menu_items ?>
  </ul>
</section>

<?php if(empty($vv_platform_menu_items) && $vv_user_roles['platform']): ?>
  <?php
    $noticeUrls = [    
      $this->Url->build([
        'plugin'       => null,
        'controller'   => 'dashboards',
        'action'       => 'configuration',
        '?'            => [
          'co_id' => 1
      ]]),
      $this->Url->build([
      'plugin'       => null,
      'controller'   => 'dashboards',
      'action'       => 'dashboard',
      '?'            => [
        'co_id' => 1
      ]])
    ];
  ?>
  <div class="config-platform-notice">
    <?= $this->element('notify/alert', [
      'message' => __d('information','cmp.config.notice', $noticeUrls),
      'type'    => 'information',
      'dismissible' => true
    ]) ?>
  </div>
<?php endif; ?>