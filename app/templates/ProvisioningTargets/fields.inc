<?php
/**
 * COmanage Registry Provisioning Targets Fields
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
  'status' => [
    'default'  => \App\Lib\Enum\ProvisionerModeEnum::QueueOnError
  ],
  'retry_interval' => [
    'default' => 900
  ],
  'max_retry',
  'plugin',
  'provisioning_group_id',
  'ordr'
];

$fields = array_merge($fields, include(ROOT . DS . 'templates' . DS . 'Standard/metadata.inc'));

$subnav = 'standard';

?>

<script>
  // JS specific to these fields

  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state
    var status = document.getElementById('status').value;

    if(status == "<?= \App\Lib\Enum\ProvisionerModeEnum::Queue ?>"
      || status == "<?= \App\Lib\Enum\ProvisionerModeEnum::QueueOnError ?>") {
      showFields(['retry-interval'], isPageLoad);
    } else {
      hideFields(['retry-interval'], isPageLoad);
    }
  }
  
  $(function() {
    // run on first load
    updateGadgets(true);

    // register onchange events
    // note that underscores in field names above must be represented with hyphens here.
    $('#status').change(function() {
      updateGadgets();
    });
  });
</script>
  
