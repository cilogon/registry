<?php
/**
 * COmanage Registry Deleted View Template to represent an empty view. For example, 
 * this is used after the delete action is called in the iframe layout.
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

$modelsName = $this->name;
$tableName = \Cake\Utility\Inflector::tableize(\Cake\Utility\Inflector::singularize($this->name));

// $vv_template_path will be set for plugins
$templatePath = $vv_template_path ?? ROOT . DS . "templates" . DS . $modelsName;

// The target window is set at the controller in $vv_target_window.
// The values should be 'self' or 'top'. It is defined here for clarity.
// If the value is set to 'top', this window will self-close and flash messages
// will be displayed in the main browser window.  
$targetWindow = $vv_target_window ?? 'self';
?>

<?php if($targetWindow == 'top'): // reload the top window. ?>
  
  <script>
    window.parent.hideCmModal();
  </script>

  <div class="page-title-container">
    <div class="page-title">
      <h1><?= __d('information','processing') ?></h1>
    </div>
  </div>
  
<?php else: ?>
  
  <div class="page-title-container">
    <div class="page-title">
        <h1><?= $vv_title ?></h1>
    </div>
  </div>

  <?php
    // $flashArgs pass banner messages to the flash element container
    $flashArgs = [];
    if(!empty($banners)) {
      $flashArgs['vv_banners'] = $banners;
    }
    print $this->element('flash', $flashArgs);
  ?>
  
  <button 
    class="btn btn-primary cm-deleted-close-button" 
    onclick="window.parent.hideCmModal()">
    <em class="material-icons" aria-hidden="true">cancel</em>
    <?= __d('operation','close') ?>
  </button>

<?php endif; ?>

