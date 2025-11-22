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

$fields = [
  'description',
  'status',
  'plugin',
  'context',
  'identifier_type_id',
  'login',
  'email_address_type_id',
  'group_id',
  'allow_empty',
  'ordr'
];

$fields = array_merge($fields, include(ROOT . DS . 'templates' . DS . 'Standard/metadata.inc'));
?>

<script>
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

  $(function() {
    // run on first load
    updateGadgets(true);

    // register onchange events
    // note that underscores in field names above must be represented with hyphens here.
    $('#context').change(function() {
      updateGadgets();
    });
    $('#identifier-type-id').change(function() {
      resetType("email-address-type-id");
    });
    $('#email-address-type-id').change(function() {
      resetType("identifier_type_id");
    });
  });
</script>
