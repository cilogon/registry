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
if($vv_action !== 'view' && $vv_action !== 'dispatch') {
  return;
}

$petitionObj = null;
if($vv_action == 'view') {
  $petitionObj = $vv_obj;
} else {
  // we are calling this file from approver dispatch
  $petitionObj = $vv_approval_petition;
}

// Start with the petition metadata
$statusTitle = __d('enumeration','PetitionStatusEnum.' . $petitionObj->status);
$statusCssClass = 'petition-status-title petition-status-title-' . Inflector::dasherize($statusTitle);
?>

<ul id="view_Petitions" class="fields form-list">

<li class="form-list-header <?= $statusCssClass ?>">
  <h2>
    <?= $statusTitle ?>
  </h2>
</li>
<li id="petition-information-title" class="form-list-header">
  <h2>
    <?= __d('information', 'petition.information') ?>
  </h2>
</li>
<li class="petition-enrollee-items">
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
<li class="petition-enrollee-items">
  <?php // Email Address provided at Petition creation, might be empty but the field should render anyway ?>
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('field','Petitions.enrollee_email') ?>
      </div>
    </div>
    <div class="field-info">
      <?php if(!empty($petitionObj->enrollee_email)): ?>
      <?=
        $this->Html->link(
          $petitionObj->enrollee_email,
          'mailto:' . $petitionObj->enrollee_email,
          ['class' => 'nospin']
        );
      ?>
      <?php endif; ?>
    </div>
  </div>
</li>
<li class="petition-enrollee-items">
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('field','Petitions.enrollee_identifier') ?>
      </div>
    </div>
    <div class="field-info">
      <?= $petitionObj->enrollee_identifier ?>
    </div>
  </div>
</li>
<?php
  // Petitioner
  if (!empty($petitionObj?->petitioner_person?->id)) {
    $petitionerStatus = $petitionObj?->petitioner_person?->primary_name?->full_name ?? '';
    $petitionerLink = [
      'url' => [
        'controller' => 'people',
        'action' => 'edit',
        $petitionObj->petitioner_person->id
      ]
    ];
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldLabel' => __d('field','Petitions.petitioner_person_id'),
        'fieldName' => 'petitioner_person_id',
        'status' => $petitionerStatus,
        'link' => !empty($petitionObj->petitioner_person->id) ? $petitionerLink : []
      ]
    ]);
  }
?>
  
<?php // Enrollment Flow name ?>
<?php if($vv_action != 'dispatch' || $vv_user_roles['platform'] || $vv_user_roles['co']): 
  // Show the enrollment flow name and link in petition view mode. In dispatch mode, only show this to admins:
  // it's the dispatch title, and regular approvers don't need the link back to the enrollment flow. ?>
<li>
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('controller','EnrollmentFlows', [1]) ?>
      </div>
    </div>
    <div class="field-info">
      <?=
        $this->Html->link(
          $petitionObj->enrollment_flow->name,
          [
            'controller' => 'enrollment_flows',
            'action' => 'edit',
            $petitionObj->enrollment_flow->id
          ]
        )
      ?>
    </div>
  </div>
</li>
<?php endif; ?>

<?php // Petition dates. ?>
<li> 
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('field','created') ?>
      </div>
    </div>
    <div class="field-info">
      <?= $this->Time->nice($petitionObj->created, $vv_tz) ?>
    </div>
  </div>
</li>
<li>
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('field','modified') ?>
      </div>
    </div>
    <div class="field-info">
      <?= $this->Time->nice($petitionObj->modified, $vv_tz) ?>
    </div>
  </div>
</li>

<?php // Related Petitions ?>
<?php if(!empty($petitionObj->started_from_petition_id)): ?>
<li> 
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('field','Petitions.started_from_petition_id') ?>
      </div>
    </div>
    <div class="field-info">
      <?=
        $this->Html->link(
          (string)$petitionObj->started_from_petition_id,
          [
            'controller' => 'petitions',
            'action' => 'view',
            $petitionObj->started_from_petition_id
          ]
        )
      ?>
    </div>
  </div>
</li>
<?php endif; // started_from_petition_id ?>
<?php if(!empty($petitionObj->transitioned_to_petition_id)): ?>
<li> 
  <div class="field">
    <div class="field-name ">
      <div class="field-title">
        <?= __d('field','Petitions.transitioned_to_petition_id') ?>
      </div>
    </div>
    <div class="field-info">
      <?=
        $this->Html->link(
          (string)$petitionObj->transitioned_to_petition_id,
          [
            'controller' => 'petitions',
            'action' => 'view',
            $petitionObj->transitioned_to_petition_id
          ]
        )
      ?>
    </div>
  </div>
</li>
<?php endif; // transitioned_to_petition_id ?>

<li class="form-list-header" id="enrollment-steps-header">
  <h2>
    <?= __d('information', 'enrollment.steps') ?>
  </h2>
  <div class="enrollment-steps-toggle-all">
    <?php
      $stepIds = ''; 
      foreach($petitionObj->enrollment_flow->enrollment_flow_steps as $step) {
        $stepIds .= 'collapse-box-' . $step->id . ' ';
      }
    ?>
    <button class="btn btn-sm enrollment-steps-toggle-all-button all-open" 
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
<?php foreach($petitionObj->enrollment_flow->enrollment_flow_steps as $step): ?>
  <?php // During enrollment flow Approval step, don't show the current or following steps.
        // For regular petition views, we show all steps (i.e. $vv_step_config will not exist). ?>
  <?php if(!isset($vv_step_config) || !((int)$step['ordr'] >= (int)$vv_step_config['enrollment_flow_step']['ordr'])): ?>
    <?php $result = Hash::extract($petitionObj->petition_step_results, "{n}[enrollment_flow_step_id=$step->id]"); ?>
    
    <li class="petition-ef-step">
      <div class="petition-step field field-with-toggle" 
        data-bs-toggle="collapse"
        data-bs-target="#collapse-box-<?= $step->id ?>"
        aria-expanded="true">
        <h3 class="petition-step-description">
          <!-- in time, replace with icon representing the plugin -->
          <span class="material-symbols-outlined">step</span>
          <?= $step->description ?>
        </h3>
        <div class="petition-step-meta">
          <?php if($vv_action == 'view'): ?>
            <?php if(!empty($result)): ?>
              <div class="petition-step-state petition-step-state-completed">
                <span class="material-symbols-outlined">check</span>
                <?= __d('result','completed') ?>
              </div>
            <?php else: ?>
              <div class="petition-step-state petition-step-state-pending">
                <span class="material-symbols-outlined">hourglass</span>
                <?= __d('enumeration','StatusEnum.P') ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
          <button class="cm-toggle nospin petition-step-toggle"
                  type="button"
                  data-bs-toggle="collapse"
                  aria-label="<?= __d('information','petition.step.toggle.details') ?>"      
                  aria-expanded="true"
                  aria-controls="collapse-box-<?= $step->id ?>"
                  data-bs-target="#collapse-box-<?= $step->id ?>">
            <em class="material-symbols-outlined petition-step-collapse-icon" aria-hidden="true">
              arrow_drop_up
            </em>
          </button>
        </div>
      </div>
      <div id="collapse-box-<?= $step->id ?>" class="collapse show">
        <ul class="petiton-step-plugin-meta">
          <li>
            <div class="field">
              <div class="field-name ">
                <div class="field-title">
                  <?= __d('field','plugin') ?>
                </div>
              </div>
              <div class="field-info">
                <?= $step->plugin ?>
              </div>
            </div>
          </li>
          <?php if(!empty($result[0]->modified)): ?>
            <li>
              <div class="field">
                <div class="field-name ">
                  <div class="field-title">
                    <?= __d('field','modified') ?>
                  </div>
                </div>
                <div class="field-info">
                  <?= $this->Time->nice($result[0]->modified, $vv_tz) ?>
                </div>
              </div>
            </li>
          <?php endif; ?>
          <?php if(!empty($result[0]->comment)): ?>
            <li>
              <div class="field">
                <div class="field-name ">
                  <div class="field-title">
                    <?= __d('field','comment') ?>
                  </div>
                </div>
                <div class="field-info">
                  <?= !empty($result) ? $result[0]->comment : '' ?>
                </div>
              </div>
            </li>
          <?php endif; ?>
        </ul>
        <?php
          // Store the cell output so we can test for emptiness
          $cellOutput = trim((string) $this->cell($step->plugin, [$petitionObj->id], ['vv_obj' => $petitionObj, 'vv_step' => $step, 'viewVars' => $this->viewVars])); 
        ?>
        <?php if(!empty($cellOutput)): ?>
          <div class="petition-attrs">
            <?= $cellOutput ?>
          </div>
        <?php endif; ?>
  
      </div>
    </li>
  <?php endif; ?>
<?php endforeach; ?>


<?php if($vv_action != 'dispatch'): ?>  
  <?php if(!empty($petitionObj->petition_history_records)): 
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
        <?php foreach($petitionObj->petition_history_records as $hr): ?>
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
            <td><?= $this->Time->nice($hr->created, $vv_tz) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </li>
  <?php endif; ?>
<?php endif; ?>
</ul>

<script nonce="<?= $vv_js_nonce ?>">
  // Toggle All button for Enrollment Flow Steps
  // We click all the fields that have an associated accordion just to set the arrow state. 
  $('button.enrollment-steps-toggle-all-button').click(function() {
    if($(this).hasClass('all-open')) {
      $(this).removeClass('all-open').addClass('all-closed');
      $('#view_Petitions').find('.petition-step-toggle').each(function() {
        if($(this).attr('aria-expanded') === 'true') {
          $(this).trigger('click');
          $(this).find('em').text("arrow_drop_down");
        }
      });
    } else {
      $(this).removeClass('all-closed').addClass('all-open');
      $('#view_Petitions').find('.petition-step-toggle').each(function() {
        if($(this).attr('aria-expanded') === 'false') {
          $(this).trigger('click');
          $(this).find('em').text("arrow_drop_up");
        }
      });
    }
  });
</script>