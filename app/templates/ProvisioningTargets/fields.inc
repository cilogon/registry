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

  function fields_update_gadgets() {
    // Hide or show gadgets according to current state

    var attr = document.getElementById('status').value;

    if(attr == "<?= \App\Lib\Enum\ProvisionerModeEnum::Queue ?>"
       || attr == "<?= \App\Lib\Enum\ProvisionerModeEnum::QueueOnError ?>") {
alert("Queue modes not yet implemented"); // XXX CFM-26
      $("#retry-interval").closest('li').show('fade');
    } else {
      $("#retry-interval").closest('li').hide('fade');
    }
  }

  function js_local_onload() {
    fields_update_gadgets();
  }
</script>
<?php
// This view does not support read-only
if($vv_action == 'add' || $vv_action == 'edit') {
  print $this->Field->control('description');

  print $this->Field->control('status', 
                              ['onChange' => 'fields_update_gadgets();',
                               'default'  => \App\Lib\Enum\ProvisionerModeEnum::QueueOnError]);
  
  print $this->Field->control('retry_interval', ['default' => 900]);

  print $this->Field->control('plugin');

// Not yet implemented (CFM-26)
//  print $this->Field->control('provisioning_group_id');
  
  print $this->Field->control('ordr');
}
