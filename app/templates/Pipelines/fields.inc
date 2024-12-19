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
?>
<script type="text/javascript">
  // JS specific to these fields

  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state
    var strategy = document.getElementById('match-strategy').value;
    
    if(strategy == '<?= \App\Lib\Enum\MatchStrategyEnum::EmailAddress ?>') {
      hideFields(['match-identifier-type-id'], isPageLoad);
      showFields(['match-email-address-type-id'], isPageLoad);
    } else if(strategy == '<?= \App\Lib\Enum\MatchStrategyEnum::Identifier ?>') {
      hideFields(['match-email-address-type-id'], isPageLoad);
      showFields(['match-identifier-type-id'], isPageLoad);
    } else {
      hideFields(['match-email-address-type-id', 'match-identifier-type-id'], isPageLoad);
    }
  }

  function jsLocalOnLoad() {
    updateGadgets(true);
  }
</script>
<?php
// This view does not support read-only
if($vv_action == 'add' || $vv_action == 'edit') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'description'
    ]
  ]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'status'
    ]
  ]);

  // Match Strategy
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'match_strategy',
      'fieldOptions' => [
        'onChange' => 'updateGadgets()'
      ]
    ]
  ]);

  foreach([
            'match_email_address_type_id',
            'match_identifier_type_id',
//            'sync_on_update',
//            'sync_on_delete',
            'sync_status_on_delete',
            'sync_cou_id',
            'sync_replace_cou_id',
            'sync_affiliation_type_id',
            'sync_identifier_type_id',
          ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
      ]
    ]);
  }

  // Connections
//XXX
}
