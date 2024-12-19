<?php
/**
 * COmanage Registry Attribute Field
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

// $attr: Obj

// Field Options array
$options = [];

// Get the static configuration of my attribute
$supportedAttributes = $this->Petition->getSupportedEnrollmentAttribute($attr->attribute);

// Do we have a default value configured?
// Either a value or an Environmental Variable,
// Each default value is mutually exclusive to the rest. We do not have to worry about a conflict.
$options['default'] = match(true) {
  isset($attr->default_value)                          => $attr->default_value,
  isset($attr->default_value_env_name)
  && getenv($attr->default_value_env_name) !== false   => getenv($attr->default_value_env_name),
  isset($attr->default_value_datetime)                 => $attr->default_value_datetime,
  default                                              => ''
};

// If we are re-rendering the Petition, override the default value with whatever
// was previously saved
if(!empty($vv_petition_attributes)) {
  $curEntity = $vv_petition_attributes->firstMatch(['enrollment_attribute_id' => $attr->id]);

  if(!empty($curEntity->value)) {
    $options['default'] = $curEntity->value;
  }
}

// Construct the field arguments
$formArguments = [
  // We prefix the attribute ID with a string because Cake seems to sometimes have
  // problems with field names that are purely integers (even if cast to strings)
  'fieldName'        => 'field-' . $attr->id,
  'fieldLabel'       => $attr->label,   // fieldLabel is only applicable to checkboxes
  'fieldType'        => $supportedAttributes['fieldType'],
  'fieldDescription' => $attr->description,
  'fieldNameAlias'   => $attr->attribute  // the field name to its enrollment attribute field name
];


/*
 * Get the values for the attributes ending with _id
 * Supported for attributes: group_id, cou_id, affiliation_type_id
 */
if(str_ends_with($attr->attribute, '_id')) {
  $suffix = substr($attr->attribute, 0, -3);
  $suffix = Inflector::pluralize(Inflector::camelize($suffix)) ;
  $defaultValuesPopulated = 'defaultValue' . $suffix;
  if ($this->get($defaultValuesPopulated) !== null) {
    $formArguments['fieldType'] = 'select';
    $formArguments['fieldSelectOptions'] = $this->get($defaultValuesPopulated);
  }
}

// READ-ONLY
if (isset($attr->modifiable) && !$attr->modifiable) {
  $options['readonly'] = true;
}

// REQUIRED
if (isset($attr->required) && $attr->required) {
  $options['required'] = true;
}

$hidden = true;
// XXX We need to render a field that is required and expects a value from the environment but
//     the env value is empty.
if (
  isset($attr->default_value_env_name, $attr->required)
  && empty($options['default'])
  && $attr->required
) {
  $hidden = false;
}

// Set the final fieldOptions
$formArguments['fieldOptions'] = $options;

print match(true) {
  // HIDDEN Field
  // We print directly, we do not delegate to the element for further processing
  // In case this is a hidden field, we need to get only the value
  $attr->hidden && $hidden => $this->Form->hidden($formArguments['fieldName'], ['value' => $options['default']]),
  // For the case of xxx_person_id fields, we will render the People a Picker element.
  str_ends_with($attr->attribute, 'person_id') =>  $this->element('CoreEnroller.spa-field', [
    'vueElementName' => 'peopleAutocomplete',
    'formArguments' => $formArguments
  ]),
// Default use case
  default                  => $this->element('form/listItem', ['arguments' => $formArguments])
};
