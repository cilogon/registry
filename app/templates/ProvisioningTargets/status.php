<?php
/**
 * COmanage Registry Provisioning Targets Status View
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

use App\Lib\Util\StringUtilities;
?>

<div class="pageTitleContainer">
  <div class="pageTitle">
    <h1><?= $vv_title; ?></h1>
  </div>
</div>

<!-- Flash Messages and defined Info Banners -->
<div class="alert-container" id="flash-messages">
  <?= $this->Flash->render() ?>

  <?php if(!empty($indexBanners)): ?>
    <?php foreach($indexBanners as $b): ?>
      <?=  $this->Alert->alert($b, 'warning') ?>
    <?php endforeach; // $indexBanners ?>
  <?php endif; // $indexBanners ?>

  <?php if(!empty($banners)): ?>
    <?php foreach($banners as $b): ?>
      <?= $this->Alert->alert($b, 'warning') ?>
    <?php endforeach; // $banners ?>
  <?php endif; // $banners ?>
</div>

<!-- Our view is similar to index.php -->
<div class="table-container">
  <?php
    $indexTableClasses = 'index-table list-mode';
    if (!empty($rowActions)) {
      $indexTableClasses .= ' with-actions';
    }
  ?>
  <table id="<?= 'provisioningstatus-table'; ?>" class="<?= $indexTableClasses; ?>">
    <thead>
      <tr>
        <th class="actions"></th>
        <th><?= __d('controller', 'ProvisioningTargets', [1]); ?></th>
        <th><?= __d('field', 'status'); ?></th>
        <th><?= __d('field', 'comment'); ?></th>
        <th><?= __d('controller', 'Identifiers', [1]); ?></th>
        <th><?= __d('field', 'timestamp'); ?></th>
      </tr>
    </thead>

    </tbody>
      <?php foreach($vv_provisioning_statuses as $p): ?>
      <tr>
        <td>
<!-- JIRA to merge this with menuActions, also to throw up "Are you sure?" -->
          <a class="field-actions-menu" href="<?= 
            $this->Url->build([
              'controller' => $vv_primary_link_model,
              'action' => 'provision',
              $vv_primary_link_obj->id,
              '?' => [
                'provisioning_target_id' => $p['target']->id
              ]
            ]);
          ?>">
            <em class="material-icons">start</em>
          <?= __d('operation', 'provision'); ?>
          </a>
          <a class="field-actions-menu" href="<?= 
            $this->Url->build([
              'controller' => 'provisioning_history_records',
              'action' => 'index',
              '?' => [
                'provisioning_target_id' => $p['target']->id,
                StringUtilities::entityToForeignKey($vv_primary_link_obj) => $vv_primary_link_obj->id
              ]
            ]);
          ?>">
            <em class="material-icons">history</em>
          <?= __d('controller', 'ProvisioningHistoryRecords', [99]); ?>
          </a>
        </td>
        <td><?= $p['target']->description; ?></td>
        <td><?= __d('enumeration', 'ProvisioningStatusEnum.'.$p['status']); ?></td>
        <td><?= $p['comment']; ?></td>
        <td><?= $p['identifier'] ?? ""; ?></td>
        <td><?= $p['timestamp'] ?? ""; // $this->Time->nice and $vv_tz ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

