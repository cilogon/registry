<?php
/**
 * COmanage Registry Identifier Assignments Fields
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
?>
<script type="text/javascript">
  // JS specific to these fields

  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state
    var context = document.getElementById('context').value;

    if(context == '') {
      // Collapse all gadgets when context is empty.
      hideFields(['email-address-type-id','group-id','login'], isPageLoad);
    } else if(context == '<?= \App\Lib\Enum\IdentifierAssignmentContextEnum::Person ?>') {
      // Show/Hide for Person context
// XXX Group ID restriction is not yet implemented (CFM-57)
// TODO: When it is, add 'group-id' to the showFields() array.
      showFields(['email-address-type-id'], isPageLoad);
      hideFields(['group-id'], isPageLoad);

      var identifierType = document.getElementById('identifier-type-id').value;
      if(!identifierType) {
        hideFields(['login'], isPageLoad);
      } else {
        showFields(['login'], isPageLoad);
      }
    } else {
      // Show/Hide for Group context
      showFields(['group-id'], isPageLoad);
      hideFields(['email-address-type-id','login'], isPageLoad);
    }
  }

  function resetType(id) {
    const dropDown = document.getElementById(id);
    dropDown.value = "";
    dropDown.selectedIndex = 0;

    updateGadgets();
  }

  function jsLocalOnLoad() {
    updateGadgets(true);
  }
</script>
  
<?php
// This view does not support read-only
if($vv_action == 'add' || $vv_action == 'edit') {
  foreach([
            'description',
            'status',
            'plugin'
          ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
      ]]);
  }

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'context',
      'fieldOptions' => [
        'onChange' => 'updateGadgets()'
      ]
    ]
  ]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'identifier_type_id',
      'fieldOptions' => [
        'onChange' => 'resetType("email-address-type-id")'
      ]
    ]
  ]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'login'
    ]
  ]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'email_address_type_id',
      'fieldOptions' => [
        'onChange' => 'resetType("identifier-type-id")'
      ]
    ]
  ]);

  foreach(['group_id',
           'allow_empty',
           'ordr'
          ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
      ]]);
  }
}