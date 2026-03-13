<?php
/**
 * COmanage Registry Enrollment Flow Steps Fields
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

use \App\Lib\Enum\EnrollmentActorEnum;

$fields = [
  'description',
  'status',
  'plugin',
  'ordr',
  'actor_type',
  'approver_group_id',
  'message_template_id',
  'redirect_on_handoff',
  'notification_group_id',
  'notification_message_template_id'
];

$subnav = 'steps';

?>

<script>
  // JS specific to these fields

  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state

    var actorType = document.getElementById('actor-type').value;

    if(actorType == "<?= EnrollmentActorEnum::Approver ?>") {
      showFields(['approver-group-id'], isPageLoad);
    } else {
      hideFields(['approver-group-id'], isPageLoad);
    }

    var notificationGroup = document.getElementById('notification-group-id').value;

    if(notificationGroup) {
      showFields(['notification-message-template-id'], isPageLoad);
    } else {
      hideFields(['notification-message-template-id'], isPageLoad);
    }
  }

  $(function() {
    // run on first load
    updateGadgets(true);

    // register onchange events
    // note that underscores in field names above must be represented with hyphens here.
    $('#actor_type, #notification-group-id').change(function() {
      updateGadgets();
    });
  });
</script>
