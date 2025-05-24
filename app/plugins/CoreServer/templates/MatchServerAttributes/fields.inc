<?php
/**
 * COmanage Registry Match Server Attributes Fields
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
?>
<script type="text/javascript">
  // JS specific to these fields

  function copyType(field) {
    // Copy the selected type ID to our storage field
    var curtypeid = document.getElementsByName(field);
    var typeid = document.getElementById('type-id');

    typeid.value = curtypeid[0].value;
  }

  function resetType() {
    // Clear the type field, but also all of the Type selectors

    document.getElementById('type-id').value = "";
    document.getElementById('address-type-id').value = "";
    document.getElementById('email-address-type-id').value = "";
    document.getElementById('identifier-type-id').value = "";
    document.getElementById('name-type-id').value = "";
    document.getElementById('telephone-number-type-id').value = "";

    updateGadgets(false);
  }

  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state

    var attribute = document.getElementById('attribute').value;

    hideFields([
      'type-id',
      'address-type-id',
      'email-address-type-id',
      'identifier-type-id',
      'name-type-id',
      'telephone-number-type-id'
    ],
    isPageLoad);

    switch(attribute) {
      case 'addresses':
        showFields(['address-type-id'], isPageLoad);
        break;
      case 'emailAddresses':
        showFields(['email-address-type-id'], isPageLoad);
        break;
      case 'identifiers':
        showFields(['identifier-type-id'], isPageLoad);
        break;
      case 'names':
        showFields(['name-type-id'], isPageLoad);
        break;
      case 'telephoneNumbers':
        showFields(['telephone-number-type-id'], isPageLoad);
        break;
    }
  }

  function jsLocalOnLoad() {
    updateGadgets(true);

    // On page load, set the appropriate widget to the current type (if set)
    var curattr = "<?= \Cake\Utility\Inflector::Singularize(\Cake\Utility\Inflector::dasherize($vv_obj->attribute ?? '')) ?>";
    var curtype = "<?= $vv_obj->type_id ?? ''?>";

    if(curattr != "") {
      curattr = curattr + "-type-id";

      document.getElementById(curattr).value = curtype;
    }
  }
</script>
<?php
// This view does currently not support read-only, and add is not used
if($vv_action == 'add' || $vv_action == 'edit') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'attribute',
      'fieldOptions' => [
        'onChange' => 'resetType()'
      ]
    ]]);

  // type_id is a hidden field used to persist the type
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'type_id',
      'fieldOptions' => [
        'type' => 'integer'
      ]
    ]]);

  // These are display widgets that are filtered to the correct available types
  foreach ([
             'address_type_id',
             'email_address_type_id',
             'identifier_type_id',
             'name_type_id',
             'telephone_number_type_id'
           ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
        'fieldLabel' => __d('field', 'type'),
        'fieldOptions' => [
          'onChange' => 'copyType("' . $field . '")'
        ]
      ]
    ]);
  }

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'required'
    ]]);
}