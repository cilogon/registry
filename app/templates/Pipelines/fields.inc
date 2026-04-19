<?php
/**
 * COmanage Registry Pipelines Fields
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

use App\Lib\Enum\StatusEnum;

$fields = [
  'description',
  'status',
  'match_strategy',
  'match_email_address_type_id',
  'match_identifier_type_id',
  'match_server_id',
  'sync_status_on_create',
  'sync_status_on_delete',
  'sync_cou_id',
  'sync_replace_cou_id',
  'sync_affiliation_type_id',
  'sync_identifier_type_id',
  'sync_verify_email_addresses'
  // XXX Connections
];

// Top Links
$topLinks[] = [
  'icon'  => 'nest_thermostat_gen_3',
  'order' => 'Default',
  'label' => __d('controller', 'Flanges', [99]),
  'link'  => [
    'controller'  => 'flanges',
    'action'      => 'index',
    'pipeline_id'      => $vv_obj->id
  ],
  'class' => ''
];
?>

<script>
  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state
    var strategy = document.getElementById('match-strategy').value;

    if(strategy == '<?= \App\Lib\Enum\MatchStrategyEnum::EmailAddress ?>') {
      hideFields([
        'match-identifier-type-id',
        'match-server-id'
      ], isPageLoad);
      showFields(['match-email-address-type-id'], isPageLoad);
    } else if(strategy == '<?= \App\Lib\Enum\MatchStrategyEnum::External ?>') {
      hideFields([
        'match-email-address-type-id',
        'match-identifier-type-id'
      ], isPageLoad);
      showFields(['match-server-id'], isPageLoad);
    } else if(strategy == '<?= \App\Lib\Enum\MatchStrategyEnum::Identifier ?>') {
      hideFields([
        'match-email-address-type-id',
        'match-server-id'
      ], isPageLoad);
      showFields(['match-identifier-type-id'], isPageLoad);
    } else {
      hideFields([
        'match-email-address-type-id',
        'match-identifier-type-id',
        'match-server-id'
      ], isPageLoad);
    }
  }

  $(function() {
    // run on first load
    updateGadgets(true);

    // register onchange events
    // note that underscores in field names above must be represented with hyphens here.
    $('#match-strategy').change(function() {
      updateGadgets();
    });
  });
</script>
