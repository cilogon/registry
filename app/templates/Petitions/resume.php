<?php
/**
 * COmanage Registry Petitions Resume View
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

// Track when we've rendered the current/next step so we know when to stop rendering links
$seenNextStep = false;

$action_args = [];
if(!empty($vv_cur_co) && $vv_user_roles['authuser']) {
  $action_args['vv_attr_id'] = $vv_primary_link_obj->name;
  if($vv_user_roles['co'] || $vv_user_roles['platform']) {
    $action_args['vv_actions'][] = [
      'order' => $this->Menu->getMenuOrder('Default'),
      'icon' => 'pending_actions',
      'url' => [
        'plugin' => null,
        'controller' => 'Petitions',
        'action' => 'index',
        '?' => ['enrollment_flow_id' => $vv_petition->enrollment_flow_id]
      ],
      'label' => __d('controller', 'Petitions', 99)
    ];
  }
  $action_args['vv_actions'][] = [
    'order' => $this->Menu->getMenuOrder('Default'),
    'icon' => 'home',
    'url' => [
      'plugin' => null,
      'controller' => 'Dashboards',
      'action' => 'dashboard',
      '?' => ['co_id' => $vv_cur_co->id]
    ],
    'label' => __d('menu', 'menu.home')
  ];
}
?>

<div class="page-title-container">
  <div class="page-title">
    <h1><?= $vv_primary_link_obj->name; // this is the Enrollment Flow name ?></h1>
  </div>
  <?php if(!empty($action_args)): ?>
    <div class="field-actions top-links">
      <?= $this->element('menuAction', $action_args) ?>
    </div>
  <?php endif; ?>
</div>

<?= $this->element('flash') // Flash messages ?>

<!-- Our view is similar to index.php -->
<div class="table-container">
  <table id="petition-resume" class="index-table list-mode with-actions">
  <tr>
    <th><?= __d('field', 'ordr') ?></th>
    <th><?= __d('controller', 'EnrollmentFlowSteps', [1]) ?></th>
    <th><?= __d('result', 'result') ?></th>
    <th><?= __d('field', 'EnrollmentFlows.authz_type') ?></th>
    <th><?= __d('field', 'action') ?></th>
  </tr>
  <?php foreach($vv_steps as $step): ?>
  <tr>
    <td><?= $step->ordr ?? "" ?></td>
    <td><?= $step->description ?></td>
  <!-- this could be a link to the detailed step result -->
    <td><?= $step->petition_step_results[0]->comment ?? "" ?></td>
    <td><?= __d('enumeration', 'EnrollmentActorEnum.'.$step->actor_type) ?></td>
    <td><?php
      if(!$seenNextStep && !empty($vv_dispatch_urls[ $step->id ])) {
        print $this->Html->link(
          ($step->id == $vv_next_step_id) ? 
            __d('operation', 'continue') . ' <span class="material-symbols-outlined">arrow_forward</span>' : 
            __d('operation', 'Petitions.rerun') . ' <span class="material-symbols-outlined">restart_alt</span>',
          $vv_dispatch_urls[ $step->id ],
          [
            'class' => 'btn btn-sm ' . (($step->id == $vv_next_step_id) ? 'btn-tertiary ef-continue-step' : 'btn-default ef-rerun-step'),
            'escape' => false
          ]
        );
  
        if($step->id == $vv_next_step_id) {
          $seenNextStep = true;
        }
      }
    ?></td>
  </tr>
  <?php endforeach; // $vv_steps ?>
  </table>
</div>
