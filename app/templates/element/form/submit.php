<?php
/**
 * COmanage Registry Submit Element
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
?>

<?php if($this->Field->isEditable()): ?>
  <li class="fields-submit">
    <div class="field">
      <div class="field-name">
        <span class="required">* <?= __d('field', 'required') ?></span>
      </div>
      <div class="field-info">
        <?= $this->Form->submit($label) ?>
        <?php if(!empty($vv_include_cancel)): ?>
          <button type="button" onclick="history.back()" class="btn btn-cancel">
            <?= __d('operation','cancel') ?>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </li>
<?php endif; ?>