<?php
/**
 * COmanage Registry Identifier Assignment Fields
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

  function fields_update_gadgets() {
    // Hide or show gadgets according to current state

    var context = document.getElementById('context').value;

    if(context == '<?= \App\Lib\Enum\IdentifierAssignmentContextEnum::Person ?>') {
      $("#group-id").closest('li').hide('fade');
// Group ID restriction is not yet implemented (CFM-57)
//      $("#group-id").closest('li').show('fade');
      $("#email-address-type-id").closest('li').show('fade');

      var identifierType = document.getElementById('identifier-type-id').value;

      if(!identifierType) {
        $("#login").closest('li').hide('fade');
      } else {
        $("#login").closest('li').show('fade');
      }
    } else {
      $("#group-id").closest('li').hide('fade');
      $("#email-address-type-id").closest('li').hide('fade');
      $("#login").closest('li').hide('fade');
    }
  }

  function reset_type(id) {
    document.getElementById(id).value = "";
    fields_update_gadgets();
  }

  function js_local_onload() {
    fields_update_gadgets();
  }
</script>
<?php
// This view does not support read-only
if($vv_action == 'add' || $vv_action == 'edit') {
  print $this->Field->control('description');

  print $this->Field->control('status');
  
  print $this->Field->control('plugin');
  
  print $this->Field->control(
    fieldName: 'context',
    options: [
      'onChange' => 'javascript:fields_update_gadgets();'
    ]
  );
  
  print $this->Field->control('group_id');
  
  print $this->Field->control(
    fieldName: 'identifier_type_id',
    options: [
      'onChange' => 'javascript:reset_type("email-address-type-id")'
    ]
  );
  
  print $this->Field->control('login');

  print $this->Field->control(
    fieldName: 'email_address_type_id',
    options: [
      'onChange' => 'javascript:reset_type("identifier-type-id")'
    ]
  );

  print $this->Field->control('ordr');
}