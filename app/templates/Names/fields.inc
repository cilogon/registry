<?php
/**
 * COmanage Registry Name Fields
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

if($vv_action == 'add' || $vv_action == 'edit' || $vv_action == 'view') {
  // Dynamic required fields is automatically handled by FormHelper via the
  // validation rules, but we need to manually check permitted fields.

  foreach(['honorific', 'given', 'middle', 'family', 'suffix'] as $f) {
    if(in_array($f, $vv_permitted_fields)) {
      print $this->element('form/listItem', [
        'arguments' => [
          'fieldName' => $f
        ]]);
    }
  }

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'type_id',
      'fieldOptions' => [
        'default' => $vv_default_type
      ]
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'language'
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'display_name'
    ]]);

  
  // We don't allow unsetting of primary_name here because we need to know what
  // the new primary_name is, but we do allow this name to become primary
  // because afterSave will unset the old one.
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'description',
      'fieldOptions' => [
        'readonly' => $vv_obj->primary_name
      ]
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'frozen'
    ]]);

}

if($vv_action == 'edit' || $vv_action == 'view') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'source',
      'entity' => $vv_obj
    ]]);
}