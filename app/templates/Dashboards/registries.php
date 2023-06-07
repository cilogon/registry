<?php
/**
 * COmanage Registry Dashboards Registries View
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

<div class="pageTitleContainer">
  <div class="pageTitle">
    <h1><?= $vv_title; ?></h1>
  </div>
</div>

<section class="inner-content">
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
</section>

<div class="comanage-version">
  <?php print __('registry.version', chop(file_get_contents(CONFIG . "VERSION"))); ?>
</div>