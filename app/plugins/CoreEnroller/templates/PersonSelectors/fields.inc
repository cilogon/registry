<?php
/**
 * COmanage Registry Person Selectors Fields
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

use \CoreEnroller\Lib\Enum\PersonSelectorModeEnum;

$fields = [
  'mode',
  'confirmation_page_mode',
  'roe_enrollment_flow_id'
];

$subnav = [
  'tabs' => ['EnrollmentFlowSteps', 'CoreEnroller.PersonSelectors'],
  'action' => [
    'EnrollmentFlowSteps' => ['edit', 'view'],
    'CoreEnroller.PersonSelectors' => ['edit']
  ],
];
?>

<script nonce="<?= $vv_js_nonce ?>">
  // JS specific to these fields

  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state
    var mode = document.getElementById('mode').value;

    if(mode == "<?= PersonSelectorModeEnum::Select ?>") {
      showFields(['confirmation-page-mode'], isPageLoad);
    } else {
      hideFields(['confirmation-page-mode'], isPageLoad);
    }
  }

  $(function() {
    // run on first load
    updateGadgets(true);

    // register onchange events
    // note that underscores in field names above must be represented with hyphens here.
    $('#mode').change(function() {
      updateGadgets();
    });
  });
</script>