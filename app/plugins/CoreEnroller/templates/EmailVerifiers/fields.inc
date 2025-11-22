<?php
/**
 * COmanage Registry Email Verifiers Fields
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

// Used for placeholders
$defaultValues = array_combine(
  array_values($defaults),
  array_keys($defaults)
);

// Establish the before and after field-supplements for the 'verification_code_regex'
$checked = !empty($vv_obj?->verification_code_charset) ? 'checked' : '';
$otherLabel = __d('core_enroller','field.EmailVerifiers.verification_code_charset.other_value');
$beforeField = <<<OTHER
<div class="field-supplement">
  <div class="form-check">
    <input class="form-check-input" type="checkbox" value="" id="other-toggle" $checked/>
    <label class="form-check-label" for="other-toggle">$otherLabel</label>
  </div>
</div>
OTHER;

$afterField = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function() {
    var checkbox = document.getElementById('other-toggle');
    var select = document.getElementById('verification-code-regex');
    var textbox = document.getElementById('verification-code-charset');
    // Variables to hold previous values
    var previousSelectValue = select.value;
    var previousTextboxValue = textbox.value;

    checkbox.addEventListener('change', function() {
        if (checkbox.checked) {
            select.style.display = 'none';
            select.value = '';
            textbox.style.display = '';
            
            // Restore previous textbox value if it exists
            if (previousTextboxValue !== undefined && previousTextboxValue !== '') {
                textbox.value = previousTextboxValue;
            }
        } else {
            select.style.display = '';
            textbox.style.display = 'none';
            textbox.value = '';
            // Restore previous select value if it exists
            if (previousSelectValue !== undefined && previousSelectValue !== '') {
                select.value = previousSelectValue;
            }
        }
    });
});
</script>
JS;

$regexDisplayFirstTime = empty($vv_obj?->verification_code_charset) && empty($vv_obj?->verification_code_regex);

// List the fields
$fields = [
  'mode',
  'message_template_id',
  'request_validity' => [
    'placeholder' => $defaultValues['DefaultVerificationValidity']
  ],
  'verification_code_regex' => [
    'fieldLabel' => __d('core_enroller','field.EmailVerifiers.verification_code_charset'),
    'groupedControls' =>  [
      'verification_code_regex' => [
        'id' => 'verification-code-regex',
        'required' => false,
        'empty' => true,
        'style' => $regexDisplayFirstTime || !empty($vv_obj?->verification_code_regex) ? '' : 'display: none;',
        'type' => 'select',
        'options' => $permittedCharacters
      ],
      'verification_code_charset' => [
        'type' => 'text',
        'label' => false,
        'id' => 'verification-code-charset',
        'style' => !empty($vv_obj?->verification_code_charset) ? '' : 'display: none;',
        'required' => false,
        'checked' => !empty($vv_obj?->verification_code_charset) ? 'checked' : ''
      ]
    ],
    'beforeField' => $beforeField,
    'afterField' => $afterField
  ],
  'verification_code_length' => [
    'required' => false,
    'placeholder' => $defaultValues['DefaultCodeLength']
  ],
  'enable_blockonfailure' => [
    'default' => true
  ]
];
