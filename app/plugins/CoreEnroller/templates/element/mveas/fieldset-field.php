<?php
/**
 * COmanage Registry Fieldset Field Used for MVEAs
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

use \Cake\Utility\Inflector;

// $field: string
// $attr:  object

$field = str_replace(' ', '_', Inflector::underscore($field));
$label = Inflector::humanize($field);
$isRequiredFromValidationRule = false;
$supportedAttributes = $this->Petition->getSupportedEnrollmentAttribute($attr->attribute);

if(isset($supportedAttributes['mveaModel'])) {
  $supportedAttributes = $this->Petition->getSupportedEnrollmentAttribute($attr->attribute);
  $modelTable = $this->Petition->getTable($supportedAttributes['mveaModel']);
  $isRequiredFromValidationRule = !$modelTable->getValidator()->field($field)->isEmptyAllowed();
}

// Construct the field arguments
$formArguments = [
  // We prefix the attribute ID with a string because Cake seems to sometimes have
  // problems with field names that are purely integers (even if cast to strings)
  'fieldName'        => "field-$field-$attr->id",
  'fieldLabel'       => $attr->label,   // fieldLabel is only applicable to checkboxes
  'fieldType'        => $modelTable->getSchema()->getColumn($field)['type'],
  'fieldNameAlias'   => $attr->attribute  // the field name to its enrollment attribute field name
];
?>

<div class="fieldset-field <?= "fields-$field"?>">
  <label for="<?= $field ?>"><?= $label ?></label>
  <?= $isRequiredFromValidationRule ? $this->element('form/requiredSpan') : ''?>
  <?= $this->Field->formField(...$formArguments) ?>
</div>


