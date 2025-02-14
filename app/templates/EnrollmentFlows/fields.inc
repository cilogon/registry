<?php
/**
 * COmanage Registry Enrollment Flow Fields
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

declare(strict_types = 1);

?>
<script type="text/javascript">
  // JS specific to these fields

  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state
    var authz = document.getElementById('authz-type').value;

    // Handle page interaction
    if(authz == '<?= \App\Lib\Enum\EnrollmentAuthzEnum::CouAdmin ?>'
       || authz == '<?= \App\Lib\Enum\EnrollmentAuthzEnum::CouPerson ?>') {
      hideFields(['authz-group-id'], isPageLoad);
      showFields(['authz-cou-id'], isPageLoad);
    } else if(authz == '<?= \App\Lib\Enum\EnrollmentAuthzEnum::GroupMember ?>') {
      hideFields(['authz-cou-id'], isPageLoad);
      showFields(['authz-group-id'], isPageLoad);
    } else {
      hideFields(['authz-cou-id', 'authz-group-id'], isPageLoad);
    }
  }

  function jsLocalOnLoad() {
    updateGadgets(true);
  }
</script>
<?php
// This view does not support read-only
if($vv_action == 'add' || $vv_action == 'edit') {

  foreach ( ['name',
             'description',
             'status',
//             'sor_label',
           ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field
      ]]);
  }

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'authz_type',
      'fieldOptions' => [
        'onChange' => 'updateGadgets()'
      ]
    ]
  ]);

  foreach (['authz_cou_id',
            'authz_group_id',
            'collect_enrollee_email',
            'redirect_on_duplicate',
            'redirect_on_finalize',
           ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field
      ]]);
  }
}
