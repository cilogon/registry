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

use \App\Lib\Enum\StatusEnum;

print $this->element('flash', []);

// Make the Form fields editable
$this->Field->enableFormEditMode();
?>

<p><?= __d('core_enroller', 'information.ApprovalCollectors.review', [$vv_petition->id]); ?></p>

<p><?= $this->Html->link(__d('operation', 'view'), ['plugin' => null, 'controller' => 'petitions', 'action' => 'view', $vv_petition->id]); ?></p>

<?php
  // Begin the form
  print $this->Form->create(null, [
    'id'   => 'approval-form',
    'type' => 'post'
  ]);

  print $this->Form->radio(
    'approved', 
    [
      StatusEnum::Approved => __d('enumeration', 'StatusEnum.'.StatusEnum::Approved), 
      StatusEnum::Denied => __d('enumeration', 'StatusEnum.'.StatusEnum::Denied)
    ],
    [
      'value' => !empty($petition_approvals)
                 ? ($petition_approvals['approved'] === true
                   ? StatusEnum::Approved
                   : ($petition_approvals['approved'] === false
                     ? StatusEnum::Denied
                     : ""))
                 : ""
    ]
  );

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'comment',
      'fieldOptions' => [
        'default' => $petition_approvals['comment'] ?? ""
      ]
  ]]);
