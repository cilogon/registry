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

// As a general rule, all Registry pages are post-login and so shouldn't be cached
header("Expires: Thursday, 10-Jan-69 00:00:00 GMT");
header("Cache-Control: no-store, no-cache, max-age=0, must-revalidate");
header("Pragma: no-cache");

// Add X-UA-Compatible header for IE
if(isset($_SERVER['HTTP_USER_AGENT']) && (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)) {
  header('X-UA-Compatible: IE=edge,chrome=1');
}
?>
<!DOCTYPE html>
<html lang="<?= __('registry.meta.lang'); ?>">
  <head>
    <?= $this->Html->meta('viewport', 'width=device-width, initial-scale=1, shrink-to-fit=no') . "\n"; ?>
    <?= $this->Html->charset(); ?>

    <title><?= (!empty($vv_title) ? $vv_title : __('registry.meta.registry')); ?></title>
    <!-- <?php
      // Include version number, but only if logged in
      if(!empty($vv_user)) {
        print __('registry.meta.version', [chop(file_get_contents(CONFIG . DS . "VERSION"))]);
      }
    ?> -->

    <!-- favicon.ico -->
    <?= $this->Html->meta('favicon.ico', '/favicon.ico', array('type' => 'icon')) . "\n"; ?>

    <!-- Load CSS -->
    <?= $this->Html->css([
      'fonts/Font-Awesome-4.6.3/css/font-awesome.min',
      'bootstrap/bootstrap.min',
      'co-color',
      'co-base',
      'co-responsive'
    ]) . "\n"; ?>

    <!-- Load Bootstrap and jQuery (other scripts at bottom) -->
    <?= $this->Html->script([
      'bootstrap/bootstrap.bundle.min.js',
      'jquery/jquery.min.js'
    ]) . "\n"; ?>

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

    // add further body classes as needed
    if(!empty($vv_user)) {
      $bodyClasses .= ' logged-in';
    } else {
      $bodyClasses .= ' logged-out';
    }
  ?>
  <body class="<?= $bodyClasses ?>" onload="js_onload_call_hooks()">
    <div id="skip-to-content-box">
      <a href="#content-start" id="skip-to-content" class="nospin"><?= __d('operation', 'skip_to_content') ?></a>
    </div>

    <!-- Primary layout -->
    <div id="comanage-wrapper">
      <div id="top-bar">
        <?php if(!empty($vv_user) && !empty($vv_cur_co) && !$isCoSelectView): ?>
          <div id="co-hamburger"><em class="material-icons">menu</em></div>
        <?php endif; // vv_user ?>
        <nav id="top-menu">
          <?= $this->element('menuTop'); ?>
        </nav>
      </div>

      <header id="banner">
        <div id="siteTitle">
          <!-- XXX Sanitize $vv_cur_co['name'] -->
          <?php if($isCoSelectView): // just print the name ?>
            <?= __('registry.meta.registry') ?>
          <?php elseif(!empty($vv_cur_co)): ?>
            <?= $this->Html->link(
              $vv_cur_co['name'],
              ['controller' => 'Dashboards',
               'action' => 'dashboard',
               '?' => ['co_id' => $vv_cur_co->id]],
              ['escape' => false]);
            ?>
          <?php else: ?>
            <?= $this->Html->link(__('registry.meta.registry'), '/'); ?>
          <?php endif; ?>
          <!-- XXX Insert quick CO selector here. -->
        </div>

        <div id="logo">
          <?=
            $this->Html->link(
              $this->Html->image(
                "COmanage-Logo-LG-onBlue.png",
                array(
                  'alt' => __('registry.meta.logo')
                )
              ),'/',
              array('escape' => false)
            );
          ?>
        </div>
      </header>

      <div id="main-wrapper">
        <?php if(!empty($vv_user) && !empty($vv_cur_co) && !$isCoSelectView): ?>
          <div id="navigation-drawer">
            <nav id="navigation" aria-label="main menu">
              <?= $this->element('menuMain'); ?>
            </nav>
          </div>
        <?php endif ?>

        <main id="main">
          <div id="content">
            <div id="content-inner">
              <?php if(!($isCoSelectView || $isDashboard)): ?>
                <!-- insert breadcrumbs on all but the front page ("Select Collaborations")
                     and Dashboards (including the CO landing page) -->
                <div id="breadcrumbs">
                  <?= $this->element('breadcrumbs'); ?>
                </div>
              <?php endif; ?>

              <!-- insert the anchor that is the target of accessible "skip to content" link -->
              <a id="content-start"></a>

              <!-- insert the page internal content -->
              <?= $this->fetch('content'); ?>
            </div>
          </div>
        </main>
      </div>

      <footer id="co-footer">
        <?= $this->element('footer'); ?>
      </footer>
    </div>

    <!-- loading animation -->
    <div id="co-loading"><span></span><span></span><span></span></div>
    
    <!-- informational messages -->
    <div 
      class="toast-container" 
      id="flash-messages" 
      aria-live="polite" 
      aria-atomic="true" >
      <?= $this->Flash->render() ?>
    </div>

    <!-- modal dialog box -->
    <?= $this->element('dialog'); ?>

    <!-- Get timezone detection -->
    <?php print $this->Html->script('jstimezonedetect/jstz.min.js'); ?>
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
      'comanage.js'
    ]) . "\n"; ?>

    <!-- COmanage JavaScript onload scripts -->
    <?php print $this->element('javascript'); ?>

  </body>
</html>
