<?php
/*
 * COmanage Registry Main Menu Panels
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
<div class="menu-panel">
  <?php if($panel == 'people'): ?>
    <h2><?= __d('menu','co.people.panel.title') ?></h2>
    <p><?= __d('menu','co.people.panel.desc') ?></p>
    <div class="menu-panel-content">
      <ul class="menu-panel-links">
        <li>
          <?php
          $menuUrl = $this->Url->build(
            ['plugin'       => null,
             'controller'   => 'people',
             'action'       => 'index',
             '?'            => [
               'co_id' => $vv_cur_co->id
             ]]
          );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('menu','co.population') ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.population.desc') ?></div>
            </div>
          </a>
        </li>
        <?php /* XXX Enable menu items as needed; plugins should fall below these */
        /*
        <li>
          <?php
          $menuUrl = $this->Url->build(
            ['plugin'       => null,
             'controller'   => 'people',
             'action'       => 'index',
             '?'            => [
               'co_id' => $vv_cur_co->id
             ]]
          );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('menu','co.pending.enrollments') ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.pending.enrollments.desc') ?></div>
            </div>
          </a>
        </li>
        <li>
          <?php
          $menuUrl = $this->Url->build(
            ['plugin'       => null,
             'controller'   => 'people',
             'action'       => 'index',
             '?'            => [
               'co_id' => $vv_cur_co->id
             ]]
          );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('menu','co.external.source.records') ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.external.source.records.desc') ?></div>
            </div>
          </a>
        </li>
        */ ?>
      </ul>
    </div>
  <?php endif; ?>
  <button type="button" class="menu-panel-close btn"><span class="material-icons-outlined">close</span></button>
</div>

