<?php
/**
 * COmanage Registry Name Div Element
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


/*
 * Parameters
 * string $fieldName
 * string $fieldLabel
 * bool   $labelIsTextOnly
 */

declare(strict_types = 1);

use Cake\Utility\Inflector;


$classes = '';

// We'll accept a fieldName of the form other_models.0.foo for forms that
// request associated data. Note, however, that model names for language
// keys are OtherModel, so we'll need to inflect.

$mn = $this->Field->getModelName();
$fn = $fieldName;

if(str_contains($fieldName, '.')) {
  // othermodels.0.field

  $bits = explode('.', $fieldName, 3);
  $mn = Inflector::classify($bits[0]);
  $fn = $bits[2];
}

[$label, $desc] = $this->Field->calculateLabelAndDescription($fn);
$label = $vv_field_arguments['fieldLabel'] ?? $label;

// Override the default required behavior if the field has the required
// option set
$optionsRequired = isset($vv_field_arguments['fieldOptions']['required'])
                   && $vv_field_arguments['fieldOptions']['required'];

// Extra class required for the grouped controls elements
if(isset($groupedControls)) {
  $classes .= 'align-top ';
}
?>

<div class="field-name <?= $classes ?>">
  <div class="field-title">
    <!-- Will this work for accessibility? -->
    <?php if(
             (isset($vv_field_arguments['labelIsTextOnly']) && !$vv_field_arguments['labelIsTextOnly'])
             && $this->Field->getFieldType($fieldName) !== 'boolean'
           ):
      ?>
      <?= $this->Form->label($fn, $label) ?>
    <!-- We print the login checkbox along with the Identifier type. -->
    <!-- As a result, the label is redundant -->
    <?php elseif($fieldName != 'login'): ?>
      <?= $label ?>
    <?php endif; ?>
    <?php

    /*
     * Required Span
     */
    if($this->Field->isEditable()
       &&
       ($this->Field->isReqField($fn) || $optionsRequired)
    ) {
      print $this->element('form/requiredSpan', [], [
        'cache' => '_html_elements',
      ]);
    }
    ?>
    </div>
  <?php if(isset($desc)): ?>
  <div class="field-desc">
    <?= $desc ?>
  </div>
  <?php endif; // description?>
</div>