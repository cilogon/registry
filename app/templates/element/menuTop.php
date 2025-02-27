<?php
/*
 * COmanage Registry Secondary Menu Bar
 * Displayed above all pages when logged in
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

declare(strict_types = 1);

use App\Lib\Enum\ApplicationStateEnum;
use App\Lib\Enum\DarkModesEnum;
use App\Lib\Enum\DensityStatesEnum;

$densityState = $this->ApplicationState->getValue(ApplicationStateEnum::ProfileDensity, 'medium');
$densityStateId = $this->ApplicationState->getId(ApplicationStateEnum::ProfileDensity);
$darkModeState = $this->ApplicationState->getValue(ApplicationStateEnum::ProfileDarkMode, 'auto');
$darkModeStateId = $this->ApplicationState->getId(ApplicationStateEnum::ProfileDarkMode);
?>
<?php if(!empty($vv_user) && !empty($vv_user_roles)): ?>
  <ul>
    <li id="top-menu-user">
      <button type="button" 
              class="dropdown-toggle top-menu-button" 
              id="user-panel-toggle" 
              data-bs-toggle="dropdown" 
              aria-haspopup="true" 
              aria-expanded="false"
              aria-label="<?= __d('menu','menu.user') ?>">
        <span class="top-menu-link-text">
          <?= $vv_user_roles['person_fullname'] ?? $vv_user_roles['person_identifier'] ?>
        </span>
        <em class="material-symbols icon-adjust" aria-hidden="true">person</em>
      </button>
      <?php 
        // If you're debugging $vv_available_cos being null, you probably forgot
        // to call parent::beforeFilter in your controller's beforeFilter()
        if(!empty($vv_available_cos)): 
      ?>
      <!-- Account Dropdown -->
      <div id="user-panel"  class="dropdown-menu <?= (count($vv_available_cos) > 1) ? ' with-co-switcher' : ''; ?>" aria-labelledby="user-panel-toggle">
        <div id="user-panel-user">
          <div id="user-panel-user-info">
            <?php if (!empty($vv_user_roles['person_id'])): ?>
              <?php
                // Generate the link to the Person canvas
                $canvasUrl = $this->Url->build(
                  ['plugin' => null,
                   'controller' => 'people',
                   'action' => $vv_user_roles['co'] || $vv_user_roles['platform'] ? 'edit' : 'view',
                   $vv_user_roles['person_id']
                  ]
                );
              ?>
              <a href="<?= $canvasUrl ?>" aria-label="<?= __d('menu', 'my.canvas') ?>">
            <?php endif; ?>
            <div id="user-panel-user-container">
              <div id="user-panel-user-icon">
                <em class="material-symbols" aria-hidden="true">person</em>
              </div>
              <div id="user-panel-user-labels">
                <?php if (!empty($vv_user_roles['person_fullname'])): ?>
                  <div id="user-panel-cn"><?= $vv_user_roles['person_fullname'] ?></div>
                <?php endif; ?>
                <div id="user-panel-id"><?= $vv_user_roles['person_identifier'] ?></div>
              </div>
            </div>
            <?php if (!empty($vv_user_roles['person_id'])): ?>
              <div id="user-panel-canvas-link">
                <?= __d('menu', 'my.canvas') ?>
              </div>
              </a>
            <?php endif; ?>
          </div>
          <div id="logout-in-panel">
            <?= $this->Html->link('<em class="material-symbols" aria-hidden="true">logout</em> ' . __d('operation','logout'),
              '/auth/logout/logout.php',
              ['escape'     => false,
               'id'         => 'logout-in-panel-link',
               'class'      => 'btn']);
            ?>
          </div>
        </div>
        <!-- Density and dark mode controls-->
        <div id="user-panel-user-settings" class="dropdown">
          <a class="btn btn-primary btn-sm dropdown-toggle nospin" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Settings
          </a>
          <ul class="dropdown-menu">
            <li class="menu-grouping">
              <form id="form-profile-menu-dark"
                    data-coid="<?= $vv_cur_co->id ?? '' ?>"
                    data-appstateid="<?= $darkModeStateId ?>"
                    data-stateattr="<?= ApplicationStateEnum::ProfileDarkMode?>"
                    data-webroot="<?= $this->request->getAttribute('webroot') ?>"
                    data-username="<?= $vv_user['username'] ?? '' ?>"
                    data-personid="<?= $vv_person_id ?? '' ?>">
                <fieldset>
                  <legend>
                    <span class="material-symbols" aria-hidden="true">dark_mode</span>
                    <?= __d('menu','menu.darkmode'); ?>
                  </legend>
                  <div class="menu-grouping-group">
                    <?php foreach(DarkModesEnum::getConstHumanized() as $mode): ?>
                      <?php $modeToLower = strtolower($mode); ?>
                      <div class="form-check">
                        <input type="radio"
                               id="setting-darkmode-<?= $modeToLower ?>"
                               name="setting-dark-mode"
                               data-mode="<?= __d('menu', "menu.darkmode.{$modeToLower}") ?>"
                               class="form-check-input"
                          <?= $darkModeState === $modeToLower ? 'checked' : '' ?>>
                        <label class="form-check-label" for="setting-darkmode-<?= $modeToLower ?>">
                          <?= __d('menu', "menu.darkmode.{$modeToLower}") ?>
                        </label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </fieldset>
              </form>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li class="menu-grouping">
              <form id="form-profile-menu-density"
                    data-coid="<?= $vv_cur_co->id ?? '' ?>"
                    data-appstateid="<?= $densityStateId ?>"
                    data-stateattr="<?= ApplicationStateEnum::ProfileDensity?>"
                    data-webroot="<?= $this->request->getAttribute('webroot') ?>"
                    data-username="<?= $vv_user['username'] ?? '' ?>"
                    data-personid="<?= $vv_person_id ?? '' ?>"
              >
                <fieldset>
                  <legend>
                    <span class="material-symbols" aria-hidden="true">density_small</span>
                    <?= __d('menu','menu.density') ?>
                  </legend>
                  <div class="menu-grouping-group">
                  <?php foreach(DensityStatesEnum::getConstHumanized() as $densityStateMode): ?>
                  <?php $densityStateModeToLower = strtolower($densityStateMode); ?>
                  <div class="form-check">
                    <input type="radio"
                           id="setting-density-<?= $densityStateModeToLower ?>"
                           name="setting-density"
                           data-mode="<?= __d('menu', "menu.density.{$densityStateModeToLower}") ?>"
                           class="form-check-input"
                           <?= $densityState === $densityStateModeToLower ? 'checked' : '' ?>>
                    <label class="form-check-label" for="setting-density-<?= $densityStateModeToLower ?>">
                      <?= __d('menu', "menu.density.{$densityStateModeToLower}") ?>
                    </label>
                  </div>
                  <?php endforeach; ?>
                </div>
              </form>
            </li>
          </ul>
        </div>
        <?php if(count($vv_available_cos) > 1): // More than one CO is available, so present the switch button ?>
          <div id="user-panel-switch-co">
            <?= $this->Html->link('<em class="material-symbols" aria-hidden="true">transfer_within_a_station</em> ' . __d('menu','co.switch'),
              '/cos/select',
              ['escape'     => false,
               'id'         => 'co-switch-link',
               'class'      => 'btn']);
            ?>
          </div>  
        <?php endif; ?>
      </div>
      <?php endif; // vv_available_cos ?>
    </li>
  </ul>
<?php endif; // vv_user ?>

<?php if(!isset($noLoginLogout) || !$noLoginLogout) : ?>
  <?php
    if(empty($vv_user)) {
      print $this->Html->link('<em class="material-symbols" aria-hidden="true">login</em> '. __d('operation', 'login'),
                              ['controller' => 'cos',
                               'action'     => 'select',
                               'plugin'     => false],
                              ['escape'     => false,
                               'id'         => 'login-button',
                               'class'      => 'btn btn-small']);
    }
  ?>
<?php endif; ?>
