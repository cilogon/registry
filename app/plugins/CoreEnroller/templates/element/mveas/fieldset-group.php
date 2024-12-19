<?php
/**
 * COmanage Registry Fields Set Group
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

// $attr: object


// Grouped fields
$groupedFieldsVar = $attr->attribute . 'GroupedFields';
$groupedFieldsArray = [];
if(!empty($$groupedFieldsVar)) {
  $groupedFieldsArray = collection(array_keys($$groupedFieldsVar))->map(static fn($fields) => explode(',', $fields))->toArray();
}

$permitted_fields_list = [];
$permitted_fields_variable_name = 'permitted_fields_' . Inflector::underscore($attr->attribute);
if (!empty($cosettings[0][$permitted_fields_variable_name])) {
  $permitted_fields_list = explode(',', $cosettings[0][$permitted_fields_variable_name]);
}
// Address has no permitted fields configuration at CO level. We will get them from
// the model configuration
$supportedAttributes = $this->Petition->getSupportedEnrollmentAttribute($attr->attribute);
$modelTable = $this->Petition->getTable($supportedAttributes['mveaModel']);
if(empty($permitted_fields_list) && !empty($modelTable?->getPermittedFields())) {
  $permitted_fields_list = $modelTable->getPermittedFields();
}

$permitted_fields_list_flipped = array_flip($permitted_fields_list);
?>

<?php foreach($groupedFieldsArray as $idx => $fields): ?>
  <div class="fieldset-subgroup">
    <?php
    foreach($fields as $field) {
      if(isset($permitted_fields_list_flipped[$field])) {
        // Print the element
        print $this->element('CoreEnroller.mveas/fieldset-field', compact('field', 'attr'));
      }
      // Remove the field we rendered from the permitted list.
      unset($permitted_fields_list_flipped[$field]);
    }
    ?>
  </div>
<?php endforeach; ?>

<?php

// For all the remaining fields we do not need a group
foreach (array_flip($permitted_fields_list_flipped) as $field) {
  print $this->element('CoreEnroller.mveas/fieldset-field', compact('field', 'attr'));
}
?>


