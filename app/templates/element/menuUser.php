s<?php
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
  <div id="user">
    <a href="#" class="topMenu" id="user-links">
      <span id="user-common-name">
        <?= $vv_user['username']; ?>
      </span>
      <em class="material-icons icon-adjust">person</em>
      <em class="material-icons drop-arrow">arrow_drop_down</em>
    </a>
    <ul id="user-links-menu" class="mdl-menu mdl-menu--bottom-right mdl-js-menu mdl-js-ripple-effect" for="user-links">
      <li id="user-links-cn">XXX Put something here</li>
      <li id="logout-in-menu" class="co-menu-button">
        <?php
          print $this->Html->link(__d('operation', 'logout') . ' <span class="fa fa-sign-out"></span>',
                                  '/auth/logout/logout.php',
                                  ['escape'     => false,
                                   'class'      => 'mdl-button mdl-js-button mdl-js-ripple-effect']);
        ?>
      </li>
    </ul>
  </div>
<?php endif; ?>

<?php if(!isset($noLoginLogout) || !$noLoginLogout) : ?>
  <?php
    if(empty($vv_user)) {
      print $this->Html->link(__d('operation', 'login') . ' <span class="fa fa-sign-in"></span>',
                              ['controller' => 'cos',
                               'action'     => 'select',
                               'plugin'     => false],
                              ['escape'     => false, 
                               'id'         => 'login',
                               'class'      => '']);
    }
  ?>
<?php endif; ?>