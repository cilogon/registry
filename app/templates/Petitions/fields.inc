<?php
/**
 * COmanage Registry Petitions Fields
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

use \Cake\Utility\Hash;
use Cake\Utility\Inflector;

// Petitions are read-only, though they can link to other actions
if ($vv_action !== 'view') {
  return;
}

// Start with the petition metadata
$statusTitle = __d('enumeration','PetitionStatusEnum.' . $vv_obj->status);
$statusCssClass = 'petition-status-title petition-status-title-' . Inflector::dasherize($statusTitle);
?>

<li class="form-list-header <?= $statusCssClass ?>">
  <h2>
    <?= $statusTitle ?>
  </h2>
</li>
<li class="form-list-header">
  <h2>
    <?= __d('information', 'petition.information') ?>
  </h2>
</li>
<?php if(!empty($vv_obj?->enrollee_person?->id)): ?>
  <li class="petition-enrollee-items">
    <div class="field">
      <div class="field-name ">
        <div class="field-title">
          <div class="petition-inline-item">
            <span class="petition-inline-title">
              <?= __d('enumeration','EnrollmentActorEnum.E') ?>:
            </span>
            <span class="petition-inline-value">
              <?=
                $this->Html->link(
                  $vv_obj->enrollee_person->primary_name->full_name ?? __d('field', 'Petitions.enrollee.new'),
                  [
                    'controller' => 'people',
                    'action' => 'edit',
                    $vv_obj->enrollee_person_id
                  ]
                );
              ?>
            </span>
          </div>
        </div>
      </div>
      <div class="field-info">
        <?php if(!empty($vv_obj->enrollee_email)): ?>
          <div class="petition-inline-item">
            <span class="petition-inline-title">
              <?= __d('field','email') ?>:
            </span>
            <span class="petition-inline-value">
              <?=
                $this->Html->link(
                  $vv_obj->enrollee_email,
                  'mailto:' . $vv_obj->enrollee_email,
                  ['class' => 'nospin']
                );
              ?>
            </span>
          </div>
        <?php endif; ?>
        <?php if(!empty($vv_obj->enrollee_identifier)): ?>
          <div class="petition-inline-item">
            <span class="petition-inline-title">
              <?= __d('field','identifier') ?>:
            </span>
            <span class="petition-inline-value">
              <?= $vv_obj->enrollee_identifier ?>
            </span>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </li> 
  <?php /*
  // XXX The following is an example of placing all enrollee information on a separate line. These rows are still 
  // rendered with a different background color. Keep this for now so we can do A/B testing. Note: if identifiers are 
  // very long, the following may be the better choice, even if less compact. 
  <li class="petition-enrollee-items">
    <div class="field">
      <div class="field-name ">
        <div class="field-title">
          <?= __d('enumeration','EnrollmentActorEnum.E') ?>
        </div>
      </div>
      <div class="field-info">
        <?=
          $this->Html->link(
            $vv_obj->enrollee_person->primary_name->full_name ?? __d('field', 'Petitions.enrollee.new'),
            [
              'controller' => 'people',
              'action' => 'edit',
              $vv_obj->enrollee_person_id
            ]
          );
        ?>
      </div>
    </div>
  </li>
  <?php if(!empty($vv_obj->enrollee_email)): ?>
    <li class="petition-enrollee-items">
      <div class="field">
        <div class="field-name ">
          <div class="field-title">
            <?= __d('field','enrollee_email') ?>
          </div>
        </div>
        <div class="field-info">
          <?=
            $this->Html->link(
              $vv_obj->enrollee_email,
              'mailto:' . $vv_obj->enrollee_email,
              ['class' => 'nospin']
            );
          ?>
        </div>
      </div>
    </li>
  <?php endif; ?>
  <?php if(!empty($vv_obj->enrollee_identifier)): ?>
    <li class="petition-enrollee-items">
      <div class="field">
        <div class="field-name ">
          <div class="field-title">
            <?= __d('field','enrollee_identifier') ?>
          </div>
        </div>
        <div class="field-info">
          <?= $vv_obj->enrollee_identifier ?>
        </div>
      </div>
    </li>
  <?php endif; ?>
 */ ?>
<?php endif; ?>

<?php
// Petitioner
if (!empty($vv_obj?->petitioner_person?->id)) {
  $petitionerStatus = $vv_obj?->petitioner_person?->primary_name?->full_name ?? '';
  $petitionerLink = [
    'url' => [
      'controller' => 'people',
      'action' => 'edit',
      $vv_obj->petitioner_person->id
    ]
  ];
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'petitioner_person_id',
      'status' => $petitionerStatus,
      'link' => !empty($vv_obj->petitioner_person->id) ? $petitionerLink : []
    ]
  ]);
}
?>

<li>
  <?php // Enrollment Flow name ?>
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('controller','EnrollmentFlows', [1]) ?>
      </div>
    </div>
    <div class="field-info">
      <?= 
        $this->Html->link(
          $vv_obj->enrollment_flow->name,
          [
            'controller' => 'enrollment_flows',
            'action' => 'edit',
            $vv_obj->enrollment_flow->id
          ]
        ) 
      ?>
    </div>
  </div>
</li>
<li>
  <?php // Email Address provided at Petition creation, might be empty but the field should render anyway ?>
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('field','Petitions.enrollee_name') ?>
      </div>
    </div>
    <div class="field-info">
      <?= $vv_enrollee_name ?? __d('field', 'Petitions.enrollee.new') ?>
    </div>
  </div>
</li>
<li>
  <?php // Email Address provided at Petition creation, might be empty but the field should render anyway ?>
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('field','Petitions.enrollee_email') ?>
      </div>
    </div>
    <div class="field-info">
      <?= $vv_obj->enrollee_email ?>
    </div>
  </div>
</li>
<li>
  <?php // Email Address provided at Petition creation, might be empty but the field should render anyway ?>
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('field','Petitions.enrollee_identifier') ?>
      </div>
    </div>
    <div class="field-info">
      <?= $vv_obj->enrollee_identifier ?>
    </div>
  </div>
</li>
<li>
  <?php // Petition dates. XXX The DOM structure is being overloaded here, which is probably ok. But revisit if needed ?> 
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('field','created') ?>: <?= $vv_obj->created ?> 
      </div>
    </div>
    <div class="field-info">
      <?= __d('field','modified') ?>: <?= $vv_obj->modified ?> 
    </div>
  </div>
</li>

<li class="form-list-header" id="enrollment-steps-header">
  <h2>
    <?= __d('information', 'enrollment.steps') ?>
  </h2>
  <div class="enrollment-steps-toggle-all">
    <?php
      $stepIds = ''; 
      foreach($vv_obj->enrollment_flow->enrollment_flow_steps as $step) {
        $stepIds .= 'collapse-box-' . $step->id . ' ';
      }
    ?>
    <button class="btn btn-sm enrollment-steps-toggle-all-button" 
            type="button"
            data-bs-toggle="collapse"
            aria-expanded="false"
            aria-label="<?= __d('information','petition.step.toggle.details.all') ?>"
            aria-controls="<?= $stepIds ?>"
            data-bs-target=".petition-attrs.collapse">
      <em class="material-symbols-outlined petition-step-collapse-icon" aria-hidden="true">
        expand_all
      </em>
      <span class="button-text"><?= __d('operation','toggle.all') ?></span>
    </button>
  </div>
</li>


<?php foreach($vv_obj->enrollment_flow->enrollment_flow_steps as $step): ?>
  <?php $result = Hash::extract($vv_obj->petition_step_results, "{n}[enrollment_flow_step_id=$step->id]"); ?>
  <li>
    <div class="field field-with-toggle" data-bs-toggle="collapse" data-bs-target="#collapse-box-<?= $step->id ?>">
      <div class="field-name">
        <div class="field-title">
          <?= $step->description ?>
        </div>
      </div>
      <div class="field-info">
        <div class="petition-step-info">
          <?= !empty($result) ? $result[0]->comment : '' ?>
        </div>
        <div class="petition-step-meta">
          <span class="petition-step-modified">
            <?= $step->modified ?>
          </span>
          <button class="cm-toggle nospin petition-step-toggle collapsed"
                  type="button"
                  data-bs-toggle="collapse"
                  aria-label="<?= __d('information','petition.step.toggle.details') ?>"      
                  aria-expanded="false"
                  aria-controls="collapse-box-<?= $step->id ?>"
                  data-bs-target="#collapse-box-<?= $step->id ?>">
            <em class="material-symbols-outlined petition-step-collapse-icon" aria-hidden="true">
              arrow_drop_down
            </em>
          </button>
        </div>
      </div>
    </div>
    <div id="collapse-box-<?= $step->id ?>" class="petition-attrs collapse">
      <h3><?= $step->plugin ?></h3>
      <?= $this->cell($step->plugin, [$vv_obj->id], ['vv_obj' => $vv_obj, 'vv_step' => $step, 'viewVars' => $this->viewVars]) ?>
    </div>
  </li>
<?php endforeach; ?>


<?php if(!empty($vv_obj->petition_history_records)): 
  // XXX Generalize this into an element for all history records if we use this output elsewhere
?>
<li class="form-list-header">
  <h2>
    <?= __d('information', 'petition.history') ?>
  </h2>
</li>
<li class="petition-history">
  <div class="table-container">
    <table id="petition-history-table" class="history-table list-mode">
      <thead>
      <tr>
        <th><?= __d('field', 'action'); ?></th>
        <th><?= __d('field', 'actor'); ?></th>
        <th><?= __d('field', 'comment'); ?></th>
        <th><?= __d('field', 'created'); ?></th>
      </tr>
      </thead>

      <tbody>
      <?php foreach($vv_obj->petition_history_records as $hr): ?>
        <tr>
          <td>
            <?= 
              // We only render the code here (not the localization) partly because some
              // codes may not be localized (especially local plugins) but also because
              // it's noisy to render the full string when mostly 'comment' has the content
              // of actual interest
              $hr->action //__d('enumeration','PetitionActionEnum.' . $hr['action']) 
            ?>
          </td>
          <td>
            <?= 
              !empty($hr->actor_person_id) 
              ? $this->Html->link(
                  $hr->actor_person->primary_name->full_name,
                  [
                    'controller' => 'people',
                    'action' => 'edit',
                    $hr->actor_person_id
                  ]
                )
              : ""          
            ?>
          </td>
          <td><?= $hr->comment ?></td>
          <td><?= $hr->created ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</li>
<?php endif; ?>
