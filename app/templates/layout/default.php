<?php
/**
 * COmanage Registry Default Layout
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

// As a general rule, all Registry pages are post-login and so shouldn't be cached
header("Expires: Thursday, 10-Jan-69 00:00:00 GMT");
header("Cache-Control: no-store, no-cache, max-age=0, must-revalidate");
header("Pragma: no-cache");
header("Content-Security-Policy: frame-ancestors 'self'");

// Add X-UA-Compatible header for IE
if(isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)) {
  header('X-UA-Compatible: IE=edge,chrome=1');
}

// Theme Dark mode state
$darkModeState = $this->ApplicationState->getValue(ApplicationStateEnum::ProfileDarkMode, 'auto');
// Density State
$densityState = $this->ApplicationState->getValue(ApplicationStateEnum::ProfileDensity, 'medium');

?>
<!DOCTYPE html>
<html lang="<?= __('registry.meta.lang'); ?>" class="<?= $darkModeState ?>-mode density-<?= $densityState ?>">
  <head>
    <?= $this->Html->meta('viewport', 'width=device-width, initial-scale=1.0') . PHP_EOL ?>
    <?= $this->Html->meta('color-scheme', 'light dark') . PHP_EOL ?>
    <?= $this->Html->charset(); ?>

    <title><?= (!empty($vv_title) ? $vv_title : __('registry.meta.registry')); ?></title>
    <!-- <?php
      // Include version number, but only if logged in
      if(!empty($vv_user)) {
        print __('registry.meta.version', [chop(file_get_contents(CONFIG . DS . "VERSION"))]);
      }
    ?> -->

    <!-- favicon.ico -->
    <?= $this->Html->meta('favicon.ico', '/favicon.ico', array('type' => 'icon')) . PHP_EOL ?>

    <!-- Load CSS -->
    <?= $this->Html->css([
      'bootstrap/bootstrap.min',
      'datatables/datatables-2.0.7.net.min',
      'datatables/dataTables.bootstrap5',
      'co-color',
      'co-base',
      'co-responsive',
    ]) . PHP_EOL ?>

    <?php
    // Set the token in a global JavaScript variable.
    // This will be very handy when dealing with AJAX requests
    try {
      print $this->Html->scriptBlock(
        sprintf(
          'var csrfToken = %s;',
          json_encode($this->request->getAttribute('csrfToken'), JSON_THROW_ON_ERROR)
        )
      );
    } catch (JsonException $e) {
      // do nothing
    }
    ?>

    <!-- Load Bootstrap, jQuery, and Vue (other scripts at bottom) -->
    <!-- https://unpkg.com/primevue@3.52.0  -->
    <?= $this->Html->script([
      'bootstrap/bootstrap.bundle.min.js',
      'jquery/jquery.min.js',
      'vue/vue-3.2.31.global.prod.js',
      'vue/primevue-3.53.0.core.min.js',
      'vue/primevue-3.53.0.autocomplete.min.js',
      'datatables/datatables-2.0.7.net.min.js',
    ]) . PHP_EOL ?>

    <!-- Include external files and scripts -->
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
  </head>

  <?php
    // cleanse the controller and action strings and insert them into the body classes
    $controller_stripped = preg_replace('/[^a-zA-Z0-9\-_]/', '', strtolower($this->request->getParam('controller')));
    $action_stripped = preg_replace('/[^a-zA-Z0-9\-_]/', '', strtolower($this->request->getParam('action')));
    $bodyClasses = $controller_stripped . ' ' .$action_stripped;
    $isCoSelectView = $controller_stripped == 'cos' && $action_stripped == 'select';
    $isDashboard = $controller_stripped == 'dashboards' && $action_stripped == 'dashboard';
    $isActivePetition = $vv_action == 'start' || $vv_action == 'dispatch' || $vv_action == 'resume';
    $generateHomeLink = (!$isCoSelectView && !empty($vv_cur_co));

    // add further body classes as needed
    if(!empty($vv_user)) {
      $bodyClasses .= ' logged-in';
    } else {
      $bodyClasses .= ' logged-out';
    }
    
    // add hints that we're in the platform-level (COmanage) CO
    $isPlatformCO = false;
    if(!empty($vv_cur_co) && ($vv_cur_co->id == 1) && !($vv_controller == 'Cos' && $vv_action == 'select')) {
      $isPlatformCO = true;
      $bodyClasses .= ' platform-co';
    }
  ?>
  <body class="<?= $bodyClasses ?>" onload="jsOnLoadCallHooks()">
    <div id="skip-to-content-box">
      <a href="#content-start" id="skip-to-content" class="visually-hidden-focusable nospin"><?= __d('operation', 'skip_to_content') ?></a>
    </div>

    <!-- Primary layout -->
    <div id="comanage-wrapper">
      <!-- Include custom header -->
      <?php if(!empty($vv_theme_header)): ?>
        <header id="customHeader">
          <div class="contentWidth">
            <?php print $vv_theme_header ?>
          </div>
        </header>
      <?php endif; ?>

      <header id="banner">
        <div id="logo-title-wrapper">
          <?php if($generateHomeLink): ?> 
            <?php
              // wrap the logo and the title in the home link
              $cmHomeLink = $this->Url->build([
                'plugin'       => null,
                'controller'   => 'Dashboards',
                'action'       => 'dashboard',
                '?' => ['co_id' => $vv_cur_co->id]],
                ['escape' => false]
              );
            ?>
            <a href="<?= $cmHomeLink ?>">
          <?php endif; ?>
          <div id="logo">
            <?=
              $this->Html->image(
                "COmanage-Gears.svg",
                array(
                  'alt' => __('registry.meta.logo')
                )
              );
            ?>
          </div>
          <div id="siteTitle">
            <?php if($generateHomeLink): ?>
              <?= h($vv_cur_co['name']) ?>
            <?php else: ?>
              <?= __('registry.meta.registry') ?>
            <?php endif; ?>          
          </div>
          <?php if($generateHomeLink): ?>
            </a>
          <?php endif; ?>
        </div>
        <!-- Custom Navigation Links -->
        <?php if(!empty($vv_NavLinks) || !empty($vv_CoNavLinks)): ?>
          <div id="user-defined-links-top">
            <?php print $this->element('links') // XXX allow user to set this location (e.g. top or side) ?>
          </div>
        <?php endif ?>
      </header>
      
      <?php if(!$isActivePetition): ?>
        <div id="top-bar">
          <?php if(!empty($vv_user) && !empty($vv_cur_co) && !$isCoSelectView): ?>
            <div id="top-controls">
              <div id="co-hamburger"><em class="material-symbols">menu</em></div>
              <?= $this->element('searchGlobal') ?>
            </div>
          <?php endif; // vv_user ?>
          <div id="top-menu">
            <?= $this->element('menuTop') ?>
          </div>
        </div>
      <?php endif; ?>
      
      <?php if($isPlatformCO): ?>
        <?php
        $platformConfigUrl = $this->Url->build([
          'plugin'       => null,
          'controller'   => 'dashboards',
          'action'       => 'configuration',
          '?'            => [
            'co_id' => 1
          ]]);
        ?>
        <div id="platform-notice">
          <?= __d('information','cmp.co.notice', [$platformConfigUrl]) ?>
        </div>
      <?php endif; ?>

      <div id="main-wrapper">
        <?php if(!empty($vv_user) && !empty($vv_cur_co) && !$isCoSelectView && !$isActivePetition): ?>
          <?= $this->element('menuMain') ?>
        <?php endif ?>

        <main id="main">
          <div id="content">
            <div id="content-inner">
              <?php if(!$isActivePetition): ?>
                <div id="breadcrumbs">
                  <?= $this->element('breadcrumbs') ?>
                </div>
              <?php endif; ?>

              <!-- insert the anchor that is the target of accessible "skip to content" link -->
              <a id="content-start"></a>

              <!-- insert the page internal content -->
              <?= $this->fetch('content') ?>
            </div>
          </div>
        </main>
      </div>

      <footer id="co-footer">
        <?= $this->element('footer') ?>
      </footer>
    </div>

    <!-- loading animation -->
    <div id="co-loading"><span></span><span></span><span></span></div>

    <!-- modal dialog boxes -->
    <?= $this->element('dialog') // used for confirmations ?>
    <?= $this->element('modal')  // used for standard lightbox modals ?>

    <!-- Get timezone detection -->
    <?= $this->Html->script('jstimezonedetect/jstz.min.js') ?>
    <script>
      // Determines the time zone of the browser client
      var tz = jstz.determine();
      // This won't be available for the first delivered page, but after that the
      // server side should see it and process it
      document.cookie = "cm_registry_tz_auto=" + tz.name() + "; path=/";
    </script>

    <!-- Load Javascript -->
    <!-- XXX js-cookie should be deprecated -->
    <?= $this->Html->script([
      'js-cookie/js.cookie-2.1.3.min.js',
      'comanage/comanage.js'
    ]) . PHP_EOL ?>

    <!-- Duet Datepicker should be loaded as a module -->
    <?= $this->Html->script('duet-datepicker/duet/duet.esm.js',['type' => 'module']) ?>
    <?= $this->Html->script('duet-datepicker/duet/duet.js',['nomodule' => '']) ?>

    <!-- COmanage JavaScript onload scripts -->
    <?php print $this->element('javascript'); ?>

  </body>
</html>
