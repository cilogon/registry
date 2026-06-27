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

use App\Lib\Enum\ApplicationStateEnum;
use App\Lib\Util\StringUtilities;
use Cake\Routing\Router;


$session = $this->getRequest()->getSession();
$hasVerificationError = $session->read('verification_error') ?? 0; // Replace 'keyName' with the actual session key you want to access
if (
  filter_var($vv_config->enable_blockonfailure, FILTER_VALIDATE_BOOLEAN)
  && filter_var($hasVerificationError, FILTER_VALIDATE_BOOLEAN)
) {
  $session->delete('verification_error');
  $stateAttr = ApplicationStateEnum::VerifyEmailBlocked;
  $appStateValue = $this->ApplicationState->getValue($stateAttr, 'lock', true);
  $appStateId = $this->ApplicationState->getId($stateAttr, true);

  if ($vv_attempts_count > 0 && $appStateValue !== 'unlock') {
    $currentUrl = Router::url(null, true);
    print $this->element('notify/blockUser', compact(
      'vv_attempts_count',
      'currentUrl',
      'stateAttr',
      'appStateId',
    ));
    return;
  }
}

$this->Field->enableFormEditMode();

if(empty($vv_verify_address)) {
  print __d('core_enroller', 'information.EmailVerifiers.done');
  return;
}

// Render a form prompting for the code that was sent to the Enrollee

$m = StringUtilities::urlbase64encode($vv_verify_address);

print $this->Form->hidden('op', ['default' => 'verify']);
print $this->Form->hidden('co_id', ['default' => $vv_cur_co->id]);
print $this->Form->hidden('m', ['default' => $m]);

print __d('core_enroller', 'information.EmailVerifiers.code_sent', [$vv_verify_address]);

print $this->element('CoreEnroller.listItem', [
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

<script nonce="<?= $vv_js_nonce ?>">
  $(document).ready(function() {
    // See https://stackoverflow.com/a/25665232 , 'Update' version
    history.pushState(null, null, document.URL);
    window.addEventListener('popstate', function () {
      history.pushState(null, null, document.URL);
    });

  //  XXX Keep for now. This is the old way to handle the enter key
  //   $('#code').bind('keypress', function (event) {
  //     if (event.charCode === 13) {
  //       $("#verification-code-form").submit();
  //     } else {
  //       // Allow for regular characters and include these special few:
  //       // comma, period, explanation point, new line
  //       var regex = new RegExp("^[a-zA-Z0-9\-]+$");
  //       var key = String.fromCharCode(!event.charCode ? event.which : event.charCode);
  //       if (!regex.test(key)) {
  //         event.preventDefault();
  //         return false;
  //       }
  //     }
  //   });
  //
  //   $('#code').on('keyup', function() {
  //     $(this).val (function () {
  //       return this.value.toUpperCase();
  //     }).trigger('change');
  //   })
  // });

</script>
