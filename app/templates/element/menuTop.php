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
?>
<?php if(!empty($vv_user)): ?>
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
          <?= $vv_user['username']; ?>
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
        <div id="logout-in-panel">
          <?= $this->Html->link('<em class="material-symbols" aria-hidden="true">logout</em> ' . __d('operation','logout'),
            '/auth/logout/logout.php',
            ['escape'     => false,
             'id'         => 'logout-in-panel-link',
             'class'      => 'btn']);
          ?>
        </div>
        <div id="user-panel-user-info">
          <em class="material-symbols" aria-hidden="true">person</em>
          <div id="user-panel-cn"><?= $vv_user['username']; ?></div>
          <div id="user-panel-id"><!-- XXX identifier goes here --></div>
        </div>
        <!-- Density and dark mode controls-->
        <div id="user-panel-user-settings" class="dropdown">
          <a class="btn btn-primary btn-sm dropdown-toggle nospin" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Settings
          </a>
          <ul class="dropdown-menu">
            <li class="menu-grouping">
              <form>
                <fieldset>
                  <legend>
                    <span class="material-symbols" aria-hidden="true">dark_mode</span>
                    <?= __d('menu','menu.darkmode'); ?>
                  </legend>
                  <div class="menu-grouping-group">
                    <div class="form-check">
                      <input type="radio" name="setting-density" class="form-check-input" id="setting-darkmode-dark">
                      <label class="form-check-label" for="setting-darkmode-dark">
                        <?= __d('menu','menu.darkmode.dark'); ?>
                      </label>
                    </div>
                    <div class="form-check">
                      <input type="radio" name="setting-density" class="form-check-input" id="setting-darkmode-light">
                      <label class="form-check-label" for="setting-darkmode-light">
                        <?= __d('menu','menu.darkmode.light'); ?>
                      </label>
                    </div>
                    <div class="form-check">
                      <input type="radio" name="setting-density" class="form-check-input" id="setting-darkmode-auto" checked>
                      <label class="form-check-label" for="setting-darkmode-auto">
                        <?= __d('menu','menu.darkmode.auto'); ?>
                      </label>
                    </div>
                  </div>
                </fieldset>
              </form>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li class="menu-grouping">
              <form>
                <fieldset>
                  <legend>
                    <span class="material-symbols" aria-hidden="true">density_small</span>
                    <?= __d('menu','menu.density'); ?>
                  </legend>
                  <div class="menu-grouping-group">
                  <div class="form-check">
                    <input type="radio" name="setting-density" class="form-check-input" id="setting-density-small">
                    <label class="form-check-label" for="setting-density-small">
                      <?= __d('menu','menu.density.small'); ?>
                    </label>
                  </div>
                  <div class="form-check">
                    <input type="radio" name="setting-density" class="form-check-input" id="setting-density-medium" checked>
                    <label class="form-check-label" for="setting-density-medium">
                      <?= __d('menu','menu.density.medium'); ?>
                    </label>
                  </div>
                  <div class="form-check">
                    <input type="radio" name="setting-density" class="form-check-input" id="setting-density-large">
                    <label class="form-check-label" for="setting-density-large">
                      <?= __d('menu','menu.density.large'); ?>
                    </label>
                  </div>
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
