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
    <?= $this->Html->meta('viewport', 'width=device-width, initial-scale=1.0') . "\n"; ?>
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
      'jquery/jquery-ui-1.12.1.custom/jquery-ui.min',
      'mdl/mdl-1.3.0/material.min.css',
      'jquery/metisMenu/metisMenu.min.css',
      'fonts/Font-Awesome-4.6.3/css/font-awesome.min',
      'co-base',
      'co-responsive'
    ]) . "\n"; ?>

    <!-- Load JavaScript (only JQuery here - other scripts at bottom) -->
    <?= $this->Html->script([
      'jquery/jquery-3.2.1.min.js',
      'jquery/jquery-ui-1.12.1.custom/jquery-ui.min.js'
    ]) . "\n"; ?>

    <!-- Include external files and scripts -->
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
  </head>

  <?php
    // cleanse the controller and action strings and insert them into the body classes
    $controller_stripped = preg_replace('/[^a-zA-Z0-9\-_]/', '', $this->request->getParam('controller'));
    $action_stripped = preg_replace('/[^a-zA-Z0-9\-_]/', '', $this->request->getParam('action'));
    $bodyClasses = $controller_stripped . ' ' .$action_stripped;

    // add further body classes as needed
// XXX Session Helper deprecated, use request->session() and backport to Match
    if(0 && $this->Session->check('Auth.User') != NULL) {
      $bodyClasses .= ' logged-in';
    } else {
      $bodyClasses .= ' logged-out';
    }
  ?>
  <body class="<?= $bodyClasses ?>" onload="js_onload_call_hooks()">
    <div id="skip-to-content-box">
      <a href="#content-start" id="skip-to-content"><?= __d('operation', 'skip_to_content') ?></a>
    </div>
    
    <!-- Primary layout -->
    <div id="comanage-wrapper" class="mdl-layout mdl-js-layout mdl-layout--fixed-drawer">
      <div id="top-menu">
        <?php if(!empty($vv_user)): ?>
          <div id="desktop-hamburger"><em class="material-icons">menu</em></div>
        <?php endif; // vv_user ?>
        <nav id="user-menu">
          <?php print $this->element('menuUser'); ?>
        </nav>
      </div>

      <header id="banner" class="mdl-layout__header mdl-layout__header--scroll">
        <div class="mdl-layout__header-row">
          <div id="collaborationTitle">
            <?= __('registry.meta.registry'); ?>
          </div>
          
          <div id="logo">
            <?=
              $this->Html->link(
                $this->Html->image(
// XXX background needs to be on green (and primary gear should be green)
                  "COmanage-Logo-LG-onBlue.png",
                  array(
                    'alt' => __('registry.meta.logo')
                  )
                ),'/',
                array('escape' => false)
              );
            ?>
          </div>
        </div>
      </header>
      
      <?php if(!empty($vv_user)): ?>
        <div id="navigation-drawer" class="mdl-layout__drawer">
          <nav id="navigation" aria-label="main menu" class="mdl-navigation">
            <?= $this->element('menuMain'); ?>
          </nav>
        </div>
      <?php endif ?>
      
      <main id="main" class="mdl-layout__content">
        <div id="content" class="mdl-grid">
          <div id="content-inner" class="mdl-cell mdl-cell--12-col">
            <!-- insert breadcrumbs -->
            <div id="breadcrumbs">
              <?= $this->element('breadcrumbs'); ?>
            </div>
            
            <!-- insert the anchor that is the target of accessible "skip to content" link -->
            <a id="content-start"></a>
            
            <!-- insert the page internal content -->
            <?= $this->fetch('content'); ?>
          </div>
        </div>
      </main>
      
      <footer id="co-footer">
<!-- XXX we appear to need the footer (or the error) to get everything else to render? -->
        <?= $this->element('footer'); ?>
      </footer>
    </div>
    
    <!-- Get timezone detection -->
    <?php print $this->Html->script('jstimezonedetect/jstz.min.js'); ?>
    <script type="text/javascript">
      // Determines the time zone of the browser client
      var tz = jstz.determine();
      // This won't be available for the first delivered page, but after that the
      // server side should see it and process it
      document.cookie = "cm_registry_tz_auto=" + tz.name() + "; path=/";
    </script>
    
    <!-- Load Javascript -->
    <?= $this->Html->script([
      'mdl/mdl-1.3.0/material.min.js',
      'jquery/metisMenu/metisMenu.min.js',
      'js-cookie/js.cookie-2.1.3.min.js',
      'jquery/spin.min.js',
      'jquery/noty/jquery.noty.js',
      'jquery/noty/layouts/topCenter.js',
      'jquery/noty/themes/comanage.js',
      'comanage.js'
    ]) . "\n"; ?>
    
    <!-- COmanage JavaScript onload scripts -->
    <?php print $this->element('javascript'); ?>
    
    <!-- XXX where does Flash->render go? -->
    <?= $this->Flash->render() ?>
  </body>
</html>
