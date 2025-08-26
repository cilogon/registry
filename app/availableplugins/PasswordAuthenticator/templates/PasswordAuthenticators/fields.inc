<?php
/**
 * COmanage Registry Password Authenticators Fields
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

  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state
    var source = document.getElementById('source-mode').value;

    if(source == '<?= \PasswordAuthenticator\Lib\Enum\PasswordSourceEnum::Autogenerate ?>') {
      showFields(['max-length'], isPageLoad);
      hideFields(['min-length'], isPageLoad);
      // document.getElementById('format-crypt-php').disabled = false;
    } else if(source == '<?= \PasswordAuthenticator\Lib\Enum\PasswordSourceEnum::External ?>') {
      hideFields(['max-length', 'min-length'], isPageLoad);
      // document.getElementById('format-crypt-php').disabled = false;
    } else if(source == '<?= \PasswordAuthenticator\Lib\Enum\PasswordSourceEnum::SelfSelect ?>') {
      showFields(['max-length', 'min-length'], isPageLoad);
      // PAR-PasswordAuthenticator-1 When the Password Source is Self Select,
      // the password must be stored in PHP Crypt format
      document.getElementById('format-crypt-php').checked = true;
      // We can't disable the element or Cake won't process it correctly on save
      // document.getElementById('format-crypt-php').disabled = true;
    }
  }

  function jsLocalOnLoad() {
    updateGadgets(true);
  }
</script>

<?php
// This view does currently not support read-only, and add is not used
if($vv_action == 'edit') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'source_mode',
      'fieldOptions'   => [
        'onChange' => 'updateGadgets(false)'
      ]
    ]
  ]);

  foreach(['min_length',
           'max_length'
          ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
      ]
    ]);
  }

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'format_crypt_php',
      'fieldOptions'   => [
        // When source is Self Select  we want crypt to remain checked
        // (though this will also be enforced in the backend)
        'onChange' => 'updateGadgets(false)'
      ]
    ]
  ]);

  foreach(['format_sha1_ldap',
           'format_plaintext'
          ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
      ]
    ]);
  }
}
