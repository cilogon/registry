<?php
/**
 * COmanage Registry Iframe Content Layout
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

print $this->element('httpHeaders');

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
      'co-responsive'
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
    $bodyClasses = $controller_stripped . ' ' . $action_stripped . ' in-iframe';
    $isCoSelectView = $controller_stripped == 'cos' && $action_stripped == 'select';
    $isDashboard = $controller_stripped == 'dashboards' && $action_stripped == 'dashboard';

    // add further body classes as needed
    if(!empty($vv_user)) {
      $bodyClasses .= ' logged-in';
    } else {
      $bodyClasses .= ' logged-out';
    }
  ?>
  <body class="<?= $bodyClasses ?>" onload="jsOnLoadCallHooks()">
    <!-- Iframe layout -->
    <div id="comanage-iframe-wrapper">
      <main id="main">
        <div id="content">
          <!-- insert the page internal content -->
          <?= $this->fetch('content') ?>
        </div>
      </main>
    </div>

    <!-- loading animation -->
    <div id="co-loading"><span></span><span></span><span></span></div>

    <!-- modal dialog box -->
    <?= $this->element('dialog') ?>

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
  
    <!-- COmanage iframe-specific JavaScript -->
    <script>
      $(document).keyup(function(e) {
        if (e.key === "Escape") {
          // If we're in a modal, dismiss it when the escape key is pressed
          if(window.parent === top) {
            // Test for the MVEA modal window
            if(typeof window.parent.cmMveaModal !== 'undefined'
              && typeof window.parent.cmMveaModal.hide === 'function') {
              window.parent.cmMveaModal.hide();
            } 
            // Test for the generic modal window
            if(typeof window.parent.cmModal !== 'undefined'
              && typeof window.parent.cmModal.hide === 'function') {
              window.parent.cmModal.hide();
            }
          }
        }
      });
    </script>

  </body>
</html>
