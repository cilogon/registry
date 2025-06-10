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

// This view only supports edit
if($vv_action !== 'edit') {
  return;
}

$defaultValues = array_combine(
  array_values($defaults),
  array_keys($defaults)
);

foreach(['mode',
          'message_template_id'
        ] as $field) {
  print $this->element('form/listItem', [
    'arguments' => ['fieldName' => $field]
  ]);
}

print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'request_validity',
    'fieldOptions' => [
      'placeholder' => $defaultValues['DefaultVerificationValidity']
    ]]
]);

// Textbox for "Other"
$otherField = $this->Form->control('verification_code_charset', [
  'type' => 'text',
  'label' => false,
  'id' => 'verification-code-charset',
  'style' => !empty($vv_obj?->verification_code_charset) ? '' : 'display: none;',
  'required' => false,
]);

$checked = !empty($vv_obj?->verification_code_charset) ? 'checked' : '';
$otherLabel = __d('field','other.value');
$beforeField = <<<OTHER
<div class="field-suppliment">
  <div class="form-check">
    <input class="form-check-input" type="checkbox" value="" id="other-toggle" $checked/>
    <label class="form-check-label" for="other-toggle">$otherLabel</label>
  </div>
</div>
$otherField
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
print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'verification_code_regex',
      'fieldLabel' => __d('core_enroller','field.EmailVerifiers.verification_code_charset'),
      'fieldOptions' => [
        'id' => 'verification-code-regex',
        'required' => false,
        'empty' => true,
        'style' => $regexDisplayFirstTime || !empty($vv_obj?->verification_code_regex) ? '' : 'display: none;',
      ],
      'fieldType' => 'select',
      'fieldSelectOptions' => $permittedCharacters,
    ],
    'beforeField' => $beforeField,
    'afterField' => $afterField,
  ]
);

print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'verification_code_length',
    'fieldOptions' => [
      'required' => false,
      'placeholder' => $defaultValues['DefaultCodeLength']
    ]]
]);

print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'enable_blockonfailure',
    'fieldOptions' => [
      'default' => true,
    ]]
]);
