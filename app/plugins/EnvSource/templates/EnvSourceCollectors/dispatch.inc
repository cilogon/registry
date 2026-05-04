<?php
/**
 * COmanage Registry Env Source Collector Petition Fields
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

// This view is intended to work with dispatch
if($vv_action == 'dispatch') {
  // This form isn't editable, but we need the continue button to render
  $this->Field->enableFormEditMode();
  ksort($vv_env_source_vars);
  $previousKey = '';
  // Render the parsed variables
?>  
  <p><?= __d('env_source', 'information.review') ?> </p>
  <ul>
    <?php foreach($vv_env_source_vars as $k => $v): ?>
      <?php
        $liClass = 'petition-key-value';
        if(substr($previousKey,0,7) != substr($k, 0, 7)) {
          $liClass .= ' env-source-key-value-newgroup';
        }
      ?>
      <li class="<?= $liClass ?>">
        <div class="env-source-key">
          <?= __d('env_source', 'field.EnvSources.'.$k) ?>
        </div>
        <div class="env-source-value">
          <?= $v ?>
        </div>
      </li>
      <?php $previousKey = $k ?>
    <?php endforeach; ?>
  </ul>
<?php
}