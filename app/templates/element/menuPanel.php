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
<div class="menu-panel" id="menu-panel-<?= $panel ?>">
  <?php if($panel == 'people'): ?>
    <h2><?= __d('menu','co.people.panel.title') ?></h2>
    <p><?= __d('menu','co.people.panel.desc') ?></p>
    <div class="menu-panel-content d-flex-md">
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
            <div class="material-symbols-outlined" aria-hidden="true">person</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('menu','co.people.population') ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.people.population.desc') ?></div>
            </div>
          </a>
        </li>
        <li>
          <?php
            $menuUrl = $this->Url->build(
              ['plugin'       => null,
               'controller'   => 'petitions',
               'action'       => 'index',
               '?'            => [
                 'co_id' => $vv_cur_co->id
               ]]
            );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="material-symbols-outlined" aria-hidden="true">pending_actions</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('controller', 'Petitions', [99]) ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.people.enrollments.pending.desc') ?></div>
            </div>
          </a>
        </li>
        <li>
          <?php
            $menuUrl = $this->Url->build(
              ['plugin'       => null,
               'controller'   => 'enrollment_flows',
               'action'       => 'index',
               '?'            => [
                 'co_id' => $vv_cur_co->id
               ]]
            );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="material-symbols-outlined" aria-hidden="true">subscriptions</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('controller', 'EnrollmentFlows', [99]) ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.people.enrollment.flows.desc') ?></div>
            </div>
          </a>
        </li>
      </ul>
      
      <?php /* XXX These sidepanel links are disabled until needed, but we will leave them in the code to 
               provide hints to where they may belong. Plugins related to this panel may also appear here. */ ?>
 
      <div class="menu-panel-sidepanel">
        <?php /* XXX if we want a title for the side menu, use an h3 like so:
        <h3>
          <?= __d('menu','related.configurations') ?>
        </h3> */
        ?>
        <div class="menu-panel-sidepanel-content">
          <ul class="menu-panel-links menu-panel-links-inner">
            <?php /* Placeholders below. Replace with real links and text replacement:
            <li><a href="#"><em class="material-symbols" aria-hidden="true">lock</em> Authenticators</a></li> */ ?>
            <?php /*
            <li><a href="#"><em class="material-symbols" aria-hidden="true">access_alarm</em> Expiration Policies</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">developer_board</em> Extended Attributes</a></li>
            */ ?>
            <li>
              <?php
                $menuUrl = $this->Url->build(
                  ['plugin'       => null,
                   'controller'   => 'identifier_assignments',
                   'action'       => 'index',
                   '?'            => [
                     'co_id' => $vv_cur_co->id
                   ]]
                );
              ?>
              <a href="<?= $menuUrl ?>" class="menu-panel-secondary-link">
                <div class="material-symbols-outlined" aria-hidden="true">badge</div>
                <div class="menu-panel-secondary-link-title"><?= __d('controller', 'IdentifierAssignments', [99]) ?></div>
              </a>
            </li>
            <?php /*
            <li><a href="#"><em class="material-symbols" aria-hidden="true">check_circle</em> Identifier Validators</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">cloud_upload</em> Provisioning Targets</a></li>
            <li><!-- more links here, including plugins --></li>
            */ ?>
          </ul>
        </div>
      </div>
    </div>
  <?php endif; ?>
  <?php if($panel == 'structure'): ?>
    <h2><?= __d('menu','co.structure.panel.title') ?></h2>
    <p><?= __d('menu','co.structure.panel.desc') ?></p>
    <div class="menu-panel-content">
      <ul class="menu-panel-links">
        <li>
          <?php
            $menuUrl = $this->Url->build(
              ['plugin'       => null,
               'controller'   => 'cous',
               'action'       => 'index',
               '?'            => [
                 'co_id' => $vv_cur_co->id
               ]]
            );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="material-symbols-outlined" aria-hidden="true">groups</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('controller', 'Cous', [99]) ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.structure.cous.desc') ?></div>
            </div>
          </a>
        </li>
        <li>
          <?php
            $menuUrl = $this->Url->build(
              ['plugin'       => null,
               'controller'   => 'groups',
               'action'       => 'index',
               '?'            => [
                 'co_id' => $vv_cur_co->id
               ]]
            );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="material-symbols-outlined" aria-hidden="true">people_outline</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('controller', 'Groups', [99]) ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.structure.groups.desc') ?></div>
            </div>
          </a>
        </li>
        <?php 
          /* XXX Enable highlighted menu items as needed:
        <li>
          <a href="#" class="menu-panel-primary-link">
            <div class="material-symbols" aria-hidden="true">business</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('controller', 'Departments', [99]) ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.structure.depts.desc') ?></div>
            </div>
          </a>
        </li>
        <li>
          <a href="#" class="menu-panel-primary-link">
            <div class="material-symbols" aria-hidden="true">account_balance</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('controller', 'Organizations', [99]) ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.structure.orgs.desc') ?></div>
            </div>
          </a>
        </li>
        */ ?>
      </ul>
    </div>
  <?php endif; ?>
  <?php if($panel == 'connections'): ?>
    <h2><?= __d('menu','co.connections.panel.title') ?></h2>
    <p><?= __d('menu','co.connections.panel.desc') ?></p>
    <div class="menu-panel-content d-flex-md">
      <!-- Primary menu-panel-links -->
      <ul class="menu-panel-links">
        <li>
          <?php
            $menuUrl = $this->Url->build(
              ['plugin'       => null,
               'controller'   => 'pipelines',
               'action'       => 'index',
               '?'            => [
                 'co_id' => $vv_cur_co->id
               ]]
            );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="material-symbols" aria-hidden="true">cable</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('controller','Pipelines', [99]) ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.connections.pipelines.desc') ?></div>
            </div>
          </a>
        </li>
        <li>
          <?php
            $menuUrl = $this->Url->build(
              ['plugin'       => null,
               'controller'   => 'external_identity_sources',
               'action'       => 'index',
               '?'            => [
                 'co_id' => $vv_cur_co->id
               ]]
            );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="material-symbols-outlined" aria-hidden="true">cloud_download</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('controller','ExternalIdentitySources', [99]) ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.connections.external_identity_sources.desc') ?></div>
            </div>
          </a>
        </li>
        <li>
          <?php
            $menuUrl = $this->Url->build(
              ['plugin'       => null,
               'controller'   => 'provisioning_targets',
               '?'            => [
                 'co_id' => $vv_cur_co->id
               ]]
            );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="material-symbols-outlined" aria-hidden="true">cloud_upload</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('controller','ProvisioningTargets', [2]) ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.connections.provisioning_targets.desc') ?></div>
            </div>
          </a>
        </li>
      </ul>  
      <!-- menu-panel-sidepanel for plugins and other links -->
      <div class="menu-panel-sidepanel">
        <div class="menu-panel-sidepanel-content">
          <ul class="menu-panel-links menu-panel-links-inner">
            <li>
              <?php
                $menuUrl = $this->Url->build(
                  ['plugin'       => null,
                   'controller'   => 'ext_identity_source_records',
                   'action'       => 'index',
                   '?'            => [
                     'co_id' => $vv_cur_co->id
                   ]]
                );
              ?>
              <a href="<?= $menuUrl ?>" class="menu-panel-secondary-link">
                <div class="material-symbols-outlined" aria-hidden="true">assignment</div>
                <div class="menu-panel-secondary-link-title"><?= __d('controller','ExtIdentitySourceRecords', [99]) ?></div>
              </a>
            </li>
            <li>
              <?php
                $menuUrl = $this->Url->build(
                  ['plugin'       => null,
                   'controller'   => 'servers',
                   'action'       => 'index',
                   '?'            => [
                     'co_id' => $vv_cur_co->id
                   ]]
                );
              ?>
              <a href="<?= $menuUrl ?>" class="menu-panel-secondary-link">
                <div class="material-symbols-outlined" aria-hidden="true">computer</div>
                <div class="menu-panel-secondary-link-title"><?= __d('controller','Servers', [2]) ?></div>
              </a>
            </li>
          </ul>
      </div>
    </div>
  <?php endif; ?>
  <?php if($panel == 'operations'): ?>
    <h2><?= __d('menu','co.operations.panel.title') ?></h2>
    <p><?= __d('menu','co.operations.panel.desc') ?></p>
    <div class="menu-panel-content">
      <!-- Primary menu-panel-links -->
      <ul class="menu-panel-links">
        <li>
          <?php
            $menuUrl = $this->Url->build(
              ['plugin'       => null,
               'controller'   => 'jobs',
               'action'       => 'index',
               '?'            => [
                 'co_id' => $vv_cur_co->id
               ]]
            );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="material-symbols-outlined" aria-hidden="true">assignment</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('controller', 'Jobs', [99]) ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.operations.jobs.desc') ?></div>
            </div>
          </a>
        </li>
        <?php
          /* XXX Enable highlighted menu items as needed:
        <li>
          <?php
            $menuUrl = $this->Url->build(
              ['plugin'       => null,
               'controller'   => 'reports',
               'action'       => 'index',
               '?'            => [
                 'co_id' => $vv_cur_co->id
               ]]
            );
          ?>
          <a href="<?= $menuUrl ?>" class="menu-panel-primary-link">
            <div class="material-symbols-outlined" aria-hidden="true">summarize</div>
            <div class="menu-panel-primary-link-text">
              <h3><?= __d('controller', 'Reports', [99]) ?></h3>
              <div class="menu-panel-link-desc"><?= __d('menu','co.operations.reports.desc') ?></div>
            </div>
          </a>
        </li>
        */ ?>
      </ul>
    </div>
  <?php endif; ?>
  <?php if($panel == 'config'): ?>
    <h2><?= __d('menu','co.configuration.panel.title') ?></h2>
    <div class="menu-panel-content">
      <?php if($vv_cur_co->id == 1): ?>
        <?php
          $platformMenuItems = [
            __d('controller', 'Cos', [99]) => [
              'icon'          => 'home',
              'controller'    => 'cos',
              'action'        => 'index'
            ],
            __d('controller', 'Plugins', [99]) => [
              'icon'          => 'electrical_services',
              'controller'    => 'plugins',
              'action'        => 'index'
            ]
          ];
        ?>
        <ul id="config-panel-platform-menu" class="menu-panel-links">
          <li>
            <h3><?= __d('menu','co.configuration.panel.platform') ?></h3>
            <p class="menu-panel-links-desc"><?= __d('menu','co.configuration.panel.platform.desc') ?></p>
            <ul class="menu-panel-links-inner">
              <?php foreach($platformMenuItems as $label => $cfg): ?>
                <li>
                  <?php
                    $linkContent =  '<em class="material-symbols" aria-hidden="true">' . $cfg['icon'] . '</em>'
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
          </li>
        </ul>
      <?php endif; // $vv_platform_menu_items ?>
      <ul class="menu-panel-links">
        <li>
          <h3><?= __d('menu','co.configuration.title') ?></h3>
          <p class="menu-panel-links-desc"><?= __d('menu','co.configuration.desc') ?></p>
          <ul class="menu-panel-links-inner">
            <li>
              <?php
                $menuUrl = $this->Url->build(
                  ['plugin'       => null,
                   'controller'   => 'co_settings',
                   'action'       => 'manage',
                   '?'            => [
                     'co_id' => $vv_cur_co->id
                   ]]
                );
              ?>
              <a href="<?= $menuUrl ?>">
                <em class="material-symbols" aria-hidden="true">settings</em> 
                <span class="menu-panel-link-text"><?= __d('controller', 'CoSettings', [99]) ?></span>
              </a>
            </li>
            <li>
              <?php
                $menuUrl = $this->Url->build(
                  ['plugin'       => null,
                   'controller'   => 'api_users',
                   'action'       => 'index',
                   '?'            => [
                     'co_id' => $vv_cur_co->id
                   ]]
                );
              ?>
              <a href="<?= $menuUrl ?>">
                <em class="material-symbols" aria-hidden="true">vpn_key</em>
                <span class="menu-panel-link-text"><?= __d('controller', 'ApiUsers', [99]) ?></span>
              </a>
            </li>
            <?php /* Placeholders below. Replace with real links and text replacement:
            <li><a href="#"><em class="material-symbols" aria-hidden="true">filter_list</em> Data Filters</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">sync</em> External Identity Sources</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">input</em> Pipelines</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">extension</em> Plugins</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">apps</em> Services</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">assignment_late</em> Terms and Conditions</a></li>
            */ ?>
            <li>
              <?php
                $menuUrl = $this->Url->build(
                  ['plugin'       => null,
                   'controller'   => 'types',
                   'action'       => 'index',
                   '?'            => [
                     'co_id' => $vv_cur_co->id
                   ]]
                );
              ?>
              <a href="<?= $menuUrl ?>">
                <em class="material-symbols" aria-hidden="true">widgets</em>
                <span class="menu-panel-link-text"><?= __d('controller', 'Types', [99]) ?></span>
              </a>
            </li>
          </ul>
        </li>
      </ul>

      <?php /* XXX Most "Personalization" links are disabled until needed, but we will leave them in the code to 
               provide hints to where they belong. The description ('co.configuration.panel.personalization.desc')
               reads "Dashboards, custom text, and theming". When such things are ready, place them here. */ ?>
 
      <ul class="menu-panel-links">
        <li>
          <h3><?= __d('menu','co.configuration.panel.personalization') ?></h3>
          <p class="menu-panel-links-desc"><?= __d('menu','co.configuration.panel.personalization.desc') ?></p>
          <ul class="menu-panel-links-inner">
            <?php /* Placeholders below. Replace with real links and text replacement:
            <li><a href="#"><em class="material-symbols" aria-hidden="true">format_list_numbered</em> Attribute Enumerations</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">navigation</em> CO Navigation Links</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">dashboard</em> Dashboards</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">book</em> Dictionaries</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">translate</em> Localizations</a></li> */ ?>
            <li>
              <?php
                $menuUrl = $this->Url->build(
                  ['plugin'       => null,
                   'controller'   => 'message_templates',
                   'action'       => 'index',
                   '?'            => [
                     'co_id' => $vv_cur_co->id
                   ]]
                );
              ?>
              <a href="<?= $menuUrl ?>">
                <em class="material-symbols-outlined" aria-hidden="true">email</em>
                <span class="menu-panel-link-text"><?= __d('controller', 'MessageTemplates', [99]) ?></span>
              </a>
              <?php
                $menuUrl = $this->Url->build(
                  ['plugin'       => null,
                   'controller'   => 'mostly_static_pages',
                   'action'       => 'index',
                   '?'            => [
                     'co_id' => $vv_cur_co->id
                   ]]
                );
              ?>
              <a href="<?= $menuUrl ?>">
                <em class="material-symbols-outlined" aria-hidden="true">article</em>
                <span class="menu-panel-link-text"><?= __d('controller', 'MostlyStaticPages', [99]) ?></span>
              </a>
            </li>
            <?php /* More placeholders:
            <li><a href="#"><em class="material-symbols" aria-hidden="true">room_service</em> Self Service Permissions</a></li>
            <li><a href="#"><em class="material-symbols" aria-hidden="true">wallpaper</em> Themes</a></li>
            * / ? >
          </ul>
        </li>
      </ul>
      */ ?>
    </div>
    <?php if($vv_cur_co->id != 1 && $vv_user_roles['platform']): ?>
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
          'type' => 'information',
          'dismissible' => true
          ]) ?>
      </div>
    <?php endif; ?>
    <div class="comanage-version">
      <?php print __('registry.version', chop(file_get_contents(CONFIG . "VERSION"))); ?>
    </div>
  <?php endif; ?>
  <button type="button" class="menu-panel-close btn"><span class="material-symbols-outlined">close</span></button>
</div>

