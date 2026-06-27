<?php
/**
 * COmanage Registry T&C Agreement Colectors Petition Fields
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

use \TermsAgreer\Lib\Enum\TAndCEnrollmentModeEnum;

print $this->element('flash', []);

// Make the Form fields editable
$this->Field->enableFormEditMode();
?>

<p><?= __d('terms_agreer', 'information.AgreementCollectors.review') ?></p>

<?php

// Begin the form
print $this->Form->create(null, [
  'id'   => 'agreement-form',
  'type' => 'post'
]);


?>
  
<table>
  <tr>
    <th><?= __d('controller','TermsAndConditions',[99]) ?></th>
    <th class="center"><?= __d('operation','review')  ?></th>
    <th><?= __d('operation','agreement') ?></th>
  </tr>
  
  <?php foreach($vv_tandc as $tc): ?>
    <tr class="<?= !empty($tc->url) ? 'tc-url' : 'tc-msp' ?>">
      <td>
        <?= $tc['description'] ?>
      </td>
      <td class="center">
        <button 
          data-tc-id="tc<?= $tc['id'] ?>"
          class="btn btn-primary btn-sm tc-review-button" 
          type="button">
          <?= __d('operation','review') ?>
        </button>
        <?php if(!empty($tc->url)): // We have a URL based T&C ?>
          <button
            data-tc-id="tc<?= $tc['id'] ?>"
            id="tc<?= $tc['id'] ?>-window-launcher"
            class="tc-window-launcher invisible"
            data-tc-url="<?= $tc->url ?>"
            type="button">
            <?= __d('operation','review') . ' tc' . $tc['id'] ?>
          </button>
        <?php else: // We have a Mostly Static Page based T&C ?>
          <?= $this->element('TermsAgreer.agreeDialog', ['vv_tc' => $tc]); ?>
        <?php endif; ?>
      </td>
      <td>
        <div class="form-check tc-agree">
          <?= 
            $this->Form->checkbox(
              'tc'.$tc['id'], 
              ['id' => 'tc'.$tc['id'], 'class' => 'form-check-input tc-agree-checkbox']
            ) . 
            $this->Form->label(
              'tc'.$tc['id'], 
              __d('operation','agree'),
              ['class' => 'form-check-label']
            ) 
          ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  
</table>


<script nonce="<?= $vv_js_nonce ?>">
  // Iterate over our T&Cs to see if they're all checked.
  function checkAgreements(mode) {
    let allAgreed = true;
    $('.tc-agree-checkbox').each(function() {
      if(!$(this).prop('checked')) {
        allAgreed = false;
        if(mode == 'EC') {
          // We're in Explicit Consent mode, so disable 
          // the current checkbox; its T&C must be reviewed.
          $(this).prop('disabled', true);
        }
      }
    });
    
    // Enable / disable the "Continue" button.
    if(allAgreed) {
      $('.submit input[type="submit"]').prop('disabled', false);
    } else {
      $('.submit input[type="submit"]').prop('disabled', true);
    }
  }

  // On page load: check agreement states and attach event handlers.
  $(function() {
    // Explicit Consent mode ("EC") will disable "I Agree" checkboxes until review.
    const tcmode = '<?= $vv_tandc_mode ?>';
    
    // Run on first load.
    checkAgreements(tcmode);
    
    // Handle "Review" button for URL-based terms and conditions.
    $('tr.tc-url button.tc-review-button').click(function(e) {
      e.preventDefault();
      jsConfirmGeneric(
        '<?= __d('terms_agreer', 'information.AgreementCollectors.external') ?>', 
        '', 
        $(this).attr('data-tc-id')+'-window-launcher', 
        '<?= __d('operation','continue') ?>', 
        '<?= __d('operation','cancel') ?>',
        '<?= __d('terms_agreer','information.AgreementCollectors.review.tc') ?>'
      );
    });

    // Launch the external window for URL-based terms and conditions
    $('tr.tc-url button.tc-window-launcher').click(function(e) {
      e.preventDefault();
      
      // Calculate window size based on screen size so that we 
      // can handle mobile and to center the window.
      const winWidth = window.outerWidth / 1.5;
      const winHeight = window.outerHeight / 1.5;
      const left = window.screenLeft + ((window.outerWidth - winWidth) / 2);
      const top = window.screenTop + ((window.outerHeight - winHeight) / 2);
      
      // Launch the T&C window with the external URL
      let tandcExternalWindow = window.open(
        $(this).attr('data-tc-url'),
        'tandcExternalWindow',
        'width=' + winWidth + ',height=' + winHeight + ',left=' + left + ',top=' + top + 
        ',resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,status=no'
      );
      
      // Enable the T&C checkbox
      $('#'+$(this).attr('data-tc-id')).prop('disabled', false);
    });

    // Handle "Review" button for Mostly Static Page-based terms and conditions.
    // Clicking this will reveal the T&C agree dialog.
    $('tr.tc-msp button.tc-review-button').click(function(e) {
      e.preventDefault();
      $('#'+$(this).attr('data-tc-id')+'-agree-dialog').modal('show');
    });

    // Handle "Agree" button click from MSP Dialog
    $('.tc-agree-button').click(function(e) {
      // Enable and check the T&C checkbox
      $('#'+$(this).attr('data-tc-id'))
        .prop('checked', true)
        .prop('disabled', false)
        .trigger('change');
    });

    // Check agreements when a checkbox is clicked. 
    // If all are checked, we'll enable "Continue".
    $('.tc-agree-checkbox').change(function() {
      checkAgreements(tcmode);
    });
  });
</script>