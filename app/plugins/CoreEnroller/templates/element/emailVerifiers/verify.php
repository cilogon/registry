<?php
/**
 * COmanage Registry Verify Email
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

use App\Lib\Util\StringUtilities;

if(empty($vv_verify_address)) {
  print __d('core_enroller', 'information.EmailVerifiers.done');
  return;
}

// Render a form prompting for the code that was sent to the Enrollee

print __d('core_enroller', 'information.EmailVerifiers.code_sent', [$vv_verify_address]);

$this->Field->enableFormEditMode();

$m = StringUtilities::urlbase64encode($vv_verify_address);

print $this->Form->hidden('op', ['default' => 'verify']);
print $this->Form->hidden('co_id', ['default' => $vv_cur_co->id]);
print $this->Form->hidden('m', ['default' => $m]);

print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'code',
    'fieldLabel' => __d('field', 'code'),
    'fieldOptions' => [
      'required' => true
    ]
  ]]);

$resendLink = $this->Html->link(
  __d('core_enroller', 'Resend'),
  ['controller' => 'email_verifiers', 'action' => 'resend', $vv_verify_address],
  ['class' => 'text-primary']
);

?>
<?php if($this->Field->isEditable()): ?>
  <li class="fields-submit">
    <div class="field">
      <div class="field-name">
        <span class="required">* <?= __d('field', 'required') ?></span>
      </div>
      <div class="field-info">
        <?= $this->Form->submit($vv_submit_button_label) ?>
        <?php if(!empty($vv_include_cancel)): ?>
          <button type="button" onclick="history.back()" class="btn btn-cancel">
            <?= __d('operation','cancel') ?>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </li>
<?php endif; ?>

<!-- Resend Link - SPA module -->
<?= $this->element('CoreEnroller.emailVerifiers/resendLinkSpa', [
  'htmlId' => 'resend-link',
  'petitionId' => $vv_petition->id,
  'containerClasses' => 'border-top border-1 pt-2 text-center text-muted',
  'emailAddress' => $m,
  'vv_config' => $vv_config,
]) ?>

