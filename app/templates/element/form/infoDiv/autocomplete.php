<?php
/**
 * COmanage Registry Autocomplete Element
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

// Create a field name for the autocomplete input
$autoCompleteFieldName = 'cm_autocomplete_' . $fieldName;

// Because we use JavaScript to set the value of the hidden field,
// disable form-tamper checking for the autocomplete fields.
// XXX We ought not have to do this for the hidden field ($fieldName) at least
$this->Form->unlockField($fieldName);
$this->Form->unlockField($autoCompleteFieldName);

$autocompleteArgs = [
  'type' => 'field',
  'fieldName' => $fieldName,
  'personType' => 'person',
  'htmlId' => $autoCompleteFieldName,
  'viewConfigParameters' => $vv_field_arguments['autocomplete']['configuration']
];

?>


<?php
  // Create a hidden field to hold our value and emit the autocomplete widget
  print $this->Form->hidden($fieldName, $vv_field_arguments['fieldOptions']) . $this->element('peopleAutocomplete', $autocompleteArgs);
?>
<div class="field-desc field-autocomplete-desc">
  <span class="material-symbols-outlined">info</span>
  <span><?= __d('operation','autocomplete.people.desc',['2']) ?></span>
</div>
