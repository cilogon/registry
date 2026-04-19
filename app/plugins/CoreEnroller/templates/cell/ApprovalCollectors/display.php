<?php
/*
 * COmanage Registry Approval Collectors Cell
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 *
 * This generic modal dialog stub is used for confirmations, e.g. when deleting a record.
 * The text of the box is overridden with JavaScript, and the confirm button is intended to
 * click a CakePHP postLink or postButton in the DOM. Use jsConfirmGeneric() to call it.
 */

use \App\Lib\Enum\PetitionStatusEnum;

$status = null;
$approver = null;

if(!empty($vv_pa->approver_person_id)) {
  $enum = ($vv_pa->approved ? PetitionStatusEnum::Approved : PetitionStatusEnum::Denied);

  $approver = $this->Html->link(
    $vv_pa->approver_person->primary_name->full_name,
    [
      'plugin' => null,
      'controller' => 'people',
      'action' => 'edit',
      $vv_pa->approver_person_id
    ]
  );

  $status = __d('core_enroller', 'result.ApprovalCollectors.status', [
    __d('enumeration', 'PetitionStatusEnum.'.$enum),
    $approver,
    $vv_pa->modified,
    $vv_pa->comment
  ]); 
}
?>
<?php if(!empty($status)): ?>
  <ul>
    <li>
      <?= $status; ?>
    </li>
  </ul>
<?php endif; ?>