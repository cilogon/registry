<?php
/**
 * COmanage Registry Types Fields
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
  'attribute' => [
    'readonly' => $vv_action == 'edit'
  ],
  'display_name',
  'value',
  'status',
  'case_insensitive',
  'edupersonaffiliation'
];

$fields = array_merge($fields, include(ROOT . DS . 'templates' . DS . 'Standard/metadata.inc'));
?>

<script>
  // JS specific to these fields

  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state
    var attr = document.getElementById('attribute').value;

    // Handle page interaction
    if(attr == 'Identifiers.type') {
      hideFields(['edupersonaffiliation'], isPageLoad);
      showFields(['case-insensitive'], isPageLoad);
    } else if(attr == 'PersonRoles.affiliation_type') {
      hideFields(['case-insensitive'], isPageLoad);
      showFields(['edupersonaffiliation'], isPageLoad);
    } else {
      hideFields(['case-insensitive', 'edupersonaffiliation'], isPageLoad);
    }
  }

  $(function() {
    // run on first load
    updateGadgets(true);

    // register onchange events
    // note that underscores in field names above must be represented with hyphens here.
    $('#attribute').change(function() {
      updateGadgets();
    });
  });
</script>