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
?>
<script type="text/javascript">
  // JS specific to these fields
  
  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state
    var status = document.getElementById('status').value;

    if(status == "<?= \App\Lib\Enum\ProvisionerModeEnum::Queue ?>"
       || status == "<?= \App\Lib\Enum\ProvisionerModeEnum::QueueOnError ?>") {
alert("Queue modes not yet implemented"); // XXX CFM-26
      showFields(['retry-interval'], isPageLoad);
    } else {
      hideFields(['retry-interval'], isPageLoad);
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
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'status',
      'fieldOptions' => [
        'onChange' => 'updateGadgets()',
        'default'  => \App\Lib\Enum\ProvisionerModeEnum::QueueOnError
      ]
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'retry_interval',
      'fieldOptions' => [
        'default' => 900
      ]
    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'plugin'
    ]]);

//  // todo: Not yet implemented (CFM-26)
//  print $this->element('form/listItem', [
//    'arguments' => [
//      'fieldName' => 'provisioning_group_id'
//    ]]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'ordr'
    ]]);
}
