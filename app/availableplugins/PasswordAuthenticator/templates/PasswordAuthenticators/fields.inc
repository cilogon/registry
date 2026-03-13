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

$fields = [
  'source_mode',
  'min_length',
  'max_length',
  'format_crypt_php',
  'format_sha1_ldap',
  'format_plaintext'
];

$subnav = [
  'tabs' => ['Authenticators', 'PasswordAuthenticator.PasswordAuthenticators'],
  'action' => [
    'Authenticators' => ['edit'],
    'PasswordAuthenticator.PasswordAuthenticators' => ['edit']
  ]
]
  
?>
  
<script>
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

  $(function() {
    // run on first load
    updateGadgets(true);

    // register onchange events
    // note that underscores in field names above must be represented with hyphens here.
    $('#source-mode, #format_crypt_php').change(function() {
      updateGadgets();
    });
  });
</script>