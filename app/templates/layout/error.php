<?php
/**
 * COmanage Registry Error Layout
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
    <?= $this->Html->charset() ?>
    
    <title><?= $this->fetch('title') ?></title>
    
    <!-- favicon.ico -->
    <?= $this->Html->meta('favicon.ico', '/favicon.ico', array('type' => 'icon')) . PHP_EOL ?>
    <?= $this->fetch('meta') ?>
    
    <!-- Load CSS -->
    <?= $this->Html->css([
      'bootstrap/bootstrap.min',
      'co-color',
      'co-base',
      'co-responsive'
    ]) . PHP_EOL ?>
  </head>
  <body>
    <div id="comanage-wrapper">
      <header id="banner">
        <div id="logo-title-wrapper">
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
            <?= __('registry.meta.registry') ?>
          </div>
        </div>
      </header>
      <div id="main-wrapper">
        <main id="main">
          <div id="content">
            <div id="content-inner">
              <div class="error-container">
                <?= $this->Flash->render() ?>
                <?= $this->fetch('content') ?>
              </div>
            </div>
          </div>
        </main>
      </div>  
    </div>  
  </body>
</html>
