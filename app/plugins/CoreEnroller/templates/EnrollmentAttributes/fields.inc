<?php
/**
 * COmanage Registry Enrollment Attribute Fields
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

// This view does currently not support read-only
$attribute_type = null;
if($vv_action == 'add') {
  $attribute_type = $this->request->getQuery('attribute_type');
} else if($vv_action == 'edit' && !empty($vv_obj)) {
  $attribute_type = $vv_obj->attribute;
}

if (empty($attribute_type)) {
  return;
}

/*
 *  Set the attribute hidden field
 */

$hidden = [
  'attribute' => $attribute_type
];
  
/*
 *  Include a "cancel" button next to the "save" button
 */

$this->set('vv_include_cancel', true);

/*
 * Common Fields for all attributes
 * - label
 * - description
 * - status
 * - order
 * - required
 */
foreach ( ['label',
           'description',
           'status',
           'ordr'
          ] as $field) {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => $field
    ]]);
}

print $this->element('form/listItem', [
  'arguments' => [
    'fieldName' => 'required',
    'fieldOptions' => [
      'default' => true
    ]
  ]]);


/*
 *
 * Optional/Per Attribute supported fields
 *
 */


/*
 * MVEAS which are allowed to attach to a Person or PersonRole
 */
if (
  !empty($vv_supported_attributes[$attribute_type]['mveaParents'])
  && count($vv_supported_attributes[$attribute_type]['mveaParents']) > 0
) {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'attribute_mvea_parent',
      'fieldOptions' => [
        'empty' => false
      ],
      'fieldType' => 'select',
      'fieldSelectOptions' => array_combine(
        $vv_supported_attributes[$attribute_type]['mveaParents'],
        $vv_supported_attributes[$attribute_type]['mveaParents']
      )
    ]
  ]);


  // This field is called attribute_type and not attribute_type_id because we want this
  // to behave as a hidden value populated by the appropriate select, and we don't want
  // Cake to implement foreign key automagic.
  $mveaAutoPopulatedVariable = $attribute_type . 'Types';
  // Check if this mvea model supports types, if it does then render the attribute type
  // dropdown list
  if ($this->get($mveaAutoPopulatedVariable) !== null) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'attribute_type',
        'fieldLabel' => $attributes[$attribute_type] . ' Type',
        'fieldOptions' => [
          'empty' => false
        ],
        'fieldType' => 'select',
        'fieldSelectOptions' => $this->get($mveaAutoPopulatedVariable)
      ]
    ]);
  }
}

/*
 * Default Values field
 * Supported for attributes: group_id, cou_id, affiliation_type_id
 */
if(str_ends_with($attribute_type, '_id')) {
  $suffix = substr($attribute_type, 0, -3);
  $suffix = Inflector::pluralize(Inflector::camelize($suffix)) ;
  $defaultValuesPopulated = 'defaultValue' . $suffix;
  if ($this->get($defaultValuesPopulated) !== null) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName'          => 'default_value',
        'fieldOptions'       => [
          'empty' => true
        ],
        'fieldType'          => 'select',
        'fieldSelectOptions' => $this->get($defaultValuesPopulated)
      ]
    ]);
  }
}

/*
 * Attribute Tag field
 *
 * Supported for attributes: adHocAttribute
 */
if (\in_array($attribute_type, ['adHocAttribute'], true)) {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'attribute_tag',
    ]]);
}

/*
 * Attribute Language field
 */
if(\in_array($attribute_type, ['name', 'address', 'pronoun'], true)) {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'attribute_language',
    ]]);
}


/*
 * Default Environmental Variable Name field
 */
$defaultValueEnvNameSupported = [
  'address', 'adHocAttribute', 'emailAddress', 'identifier', 'name' ,'pronoun', 'telephoneNumber', 'url'
];
$personRoleExclude = [
  'affiliation_type_id', 'cou_id', 'valid_from', 'valid_through'
];
if (
  \in_array($attribute_type, $defaultValueEnvNameSupported, true)
  || (
    isset($vv_supported_attributes[$attribute_type]['model'])
    && $vv_supported_attributes[$attribute_type]['model'] !== 'PersonRole'
    && !\in_array($attribute_type, $personRoleExclude, true)
    )
) {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'default_value_env_name',
    ]]);
}

/*
 * The valid from attribute is a dateTime field
 *
 */
if($attribute_type === 'valid_from') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'default_value_datetime',
      'fieldType' => 'datetime'
    ]]);
}

/*
 * The valid through attribute is a textbox/number field
 */
if($attribute_type === 'valid_through') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'default_value',
      'fieldDescription' => __d('core_enroller', 'field.AttributeCollectors.valid_through.default.after.desc'),
      'fieldOptions' => [
        'type' => 'number'
      ]
    ]]);
}

/*
 * Fields modifiable, hidden
 * Supported for Models: PersonRole, Person, Group
 */
if (
  isset($vv_supported_attributes[$attribute_type]['model'])
  && \in_array($vv_supported_attributes[$attribute_type]['model'], ['PersonRole', 'Person', 'Group'], true)
) {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName'    => 'modifiable',
      'fieldOptions' => [
        'default' => true
      ]
    ]
  ]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'hidden'
    ]]);
}
