<?php
/**
 * COmanage Registry Cos Select View
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

// XXX See registry/app/View/Pages/home.ctp for various error messages we should
//     render based on the user's state, include op.home.no.collabs if empty
?>
<div id="select-co" class="page-title-container">
  <div class="page-title">
    <h1><?= __('registry.home.collab'); ?></h1>
  </div>
</div>

<?= $this->element('flash') // Flash messages ?>

<?php if(count($vv_available_cos ?? []) == 0): ?>
  <?= $this->element('notify/alert', ['message' => __d('information','cos.none')]) ?>
<?php else: // vv_available_cos ?>
  <p><?= __d('information', 'cos.select'); ?></p>
  <div id="fpList" class="co-grid co-grid-with-header container">
    <div class="co-grid-header row">
      <div class="col"><?= __d('field', 'name'); ?></div>
      <div class="col"><?= __d('field', 'description'); ?></div>
    </div>

    <?php foreach($vv_available_cos as $co): ?>
    <div class="row co-row linked-row spin">
      <div class=col collab-name">
        <?= $this->Html->link(
              $co->name,
              ['plugin'     => null,
               'controller' => 'dashboards',
               'action'     => 'dashboard',
               '?'          => [
                 'co_id'      => $co->id
               ]],
              ['class' => 'row-link']
            ); ?>
      </div>
      <div class="col collab-desc">
  <!-- XXX need to add "Not a Member" tag, maybe as a separate column instead of part of the link -->
        <?= filter_var($co->description, FILTER_SANITIZE_SPECIAL_CHARS); ?>
      </div>
    </div>
    <?php endforeach; // vv_available_cos ?>
  </div>
<?php endif; ?>
