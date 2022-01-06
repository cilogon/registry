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
      <a class="dropdown-toggle nospin" href="#" role="button" id="user-panel-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <span class="top-menu-link-text">
          <?= $vv_user['username']; ?>
        </span>
        <em class="material-icons icon-adjust">person</em>
      </a>
      <!-- Account Dropdown -->
      <div id="user-panel"  class="dropdown-menu" aria-labelledby="user-panel-toggle">
        <div id="logout-in-panel">
          <?= $this->Html->link(__d('operation','logout') . ' <span class="fa fa-sign-out"></span>',
            '/auth/logout/logout.php',
            ['escape'     => false,
             'class'      => 'btn']);
          ?>
        </div>
        <div id="user-panel-user-info">
          <em class="material-icons">person</em>
          <div id="user-panel-cn"><?= $vv_user['username']; ?></div>
          <div id="user-panel-id"><!-- XXX identifier goes here --></div>
        </div>
      </div>
    </li>
  </ul>
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
