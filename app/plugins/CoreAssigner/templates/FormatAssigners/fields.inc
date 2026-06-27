<?php
/**
 * COmanage Registry Format Assigners Fields
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
  'format',
  'collision_mode',
  'permitted_characters',
  'minimum_length',
  'minimum',
  'maximum',
  'enable_transliteration'
];

$subnav = [
  'tabs' => ['IdentifierAssignments', 'CoreAssigner.FormatAssigners'],
  'action' => [
    'IdentifierAssignments' => ['edit'],
    'CoreAssigner.FormatAssigners' => ['edit']
  ]
];

?>

<script nonce="<?= $vv_js_nonce ?>">
  // JS specific to these fields

  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state
    var mode = document.getElementById('collision-mode').value;

    if(mode == '<?= \CoreAssigner\Lib\Enum\CollisionModeEnum::Sequential ?>') {
      hideFields(['maximum'], isPageLoad);
    } else {
      showFields(['maximum'], isPageLoad);
    }
  }

  $(function() {
    // run on first load
    updateGadgets(true);

    // register onchange events
    $('#collision-mode').change(function() {
      updateGadgets();
    });
  });
</script>
