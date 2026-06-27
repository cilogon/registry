<?php
/**
 * COmanage Registry Approval Colectors Petition Fields
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
 */

declare(strict_types = 1);

use \App\Lib\Enum\PetitionStatusEnum;

// Make the Form fields editable
$this->Field->enableFormEditMode();
?>

<li id="approver-petition-info">
  <?php if($vv_user_roles['platform'] || $vv_user_roles['co']): ?>
    <?= $this->Html->link(
      '<span class="material-symbols">open_in_new</span> ' .
      __d('operation', 'view.Petitions.a',[$vv_petition->id]),
      [
        'plugin' => null,
        'controller' => 'petitions',
        'action' => 'view',
        $vv_petition->id
      ],
      [
        'id' => 'view-petition-button',
        'class' => 'btn btn-sm btn-primary',
        'target' => 'co-petition-window',
        'escape' => false
      ]);
    ?>
  <?php endif; ?>
  <div id="approver-petition-view">
    <?php include ROOT . DS . "templates" . DS . 'Petitions/petition.inc'; ?>
  </div>
</li>

<?php
  print $this->element('CoreEnroller.listItem', [
  'arguments' => [
    'fieldName' => 'approved',
    'fieldLabel' => __d('core_enroller', 'information.ApprovalCollectors.review', [$vv_petition->id]),
    'fieldOptions' => [
      'type' => 'radio',
      'options' => [
        PetitionStatusEnum::Approved => __d('enumeration', 'PetitionStatusEnum.'.PetitionStatusEnum::Approved),
        PetitionStatusEnum::Denied => __d('enumeration', 'PetitionStatusEnum.'.PetitionStatusEnum::Denied)
      ],
      'value' => !empty($petition_approvals)
                 ? ($petition_approvals['approved'] === true
                   ? PetitionStatusEnum::Approved
                   : ($petition_approvals['approved'] === false
                     ? PetitionStatusEnum::Denied
                     : ""))
                 : "",
      'empty' => false,
      'required' => true,
      'class' => 'form-check-input'
    ]
  ]]);

  print $this->element('CoreEnroller.listItem', [
    'arguments' => [
      'fieldName' => 'comment',
      'fieldOptions' => [
        'default' => $petition_approvals['comment'] ?? "",
        'required' => $vv_step_config['require_comment'] ?? false
      ],
  ]]);
?>

<script nonce="<?= $vv_js_nonce ?>">
  $('#approved-y').click(function() {
    $(this).closest('label').addClass('is-selected');
    $("label[for='approved-n']").removeClass('is-selected');
  });
  $('#approved-n').click(function() {
    $(this).closest('label').addClass('is-selected');
    $("label[for='approved-y']").removeClass('is-selected');
  });
</script>
