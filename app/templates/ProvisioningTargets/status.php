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

<div class="page-title-container">
  <div class="page-title">
    <h1><?= $vv_title; ?></h1>
  </div>
</div>

<?= $this->element('flash') // Flash messages ?>

<!-- Our view is similar to index.php -->
<div class="table-container">
  <table id="provisioningstatus-table" class="index-table list-mode with-actions">
    <thead>
      <tr>
        <td class="actions"></td>
        <th><?= __d('controller', 'ProvisioningTargets', [1]); ?></th>
        <th><?= __d('field', 'ProvisioningTargets.status.live'); ?></th>
        <th><?= __d('field', 'ProvisioningTargets.comment.live'); ?></th>
        <th><?= __d('field', 'ProvisioningTargets.status.last'); ?></th>
        <th><?= __d('field', 'ProvisioningTargets.comment.last'); ?></th>
        <th><?= __d('controller', 'Identifiers', [1]); ?></th>
        <th><?= __d('field', 'timestamp'); ?></th>
      </tr>
    </thead>

    </tbody>
      <?php foreach($vv_provisioning_statuses as $p): ?>
      <tr>
        <td class="actions">
          <div class="field-actions">
<!-- CFM-305 -- JIRA to throw up "Are you sure?" - also to simplify all the below -->
          <?php
            // Build the row actions
            $action_args = array();
            $action_args['vv_attr_id'] = $p['target']->id;
            $action_args['vv_actions'] = [
              [
                'order' => $this->Menu->getMenuOrder('Default'),
                'icon' => 'start',
                'url' => [
                  'controller' => StringUtilities::foreignKeyToController($vv_target_fk),
                  'action' => 'provision',
                  $vv_target_id,
                  '?' => [
                    'provisioning_target_id' => $p['target']->id
                  ]
                ],
                'label' => __d('operation', 'provision'),
                'confirm' => [
                  'dg_body_txt' => __d('operation', 'provision.confirm'),
                  'dg_confirm_btn' => __d('operation', 'provision')
                ]
              ],
              [
                'order' => $this->Menu->getMenuOrder('Default'),
                'icon' => 'history',
                'url' => [
                  'controller' => 'provisioning_history_records',
                  'action' => 'index',
                  '?' => [
                    'provisioning_target_id' => $p['target']->id,
                    $vv_target_fk => $vv_target_id
                  ]
                ],
                'label' => __d('controller', 'ProvisioningHistoryRecords', [99])
              ]
            ];
  
            print $this->element('menuAction', $action_args);
          ?>
          </div>
        </td>
        <td><?= filter_var($p['target']->description, FILTER_SANITIZE_SPECIAL_CHARS); ?></td>
        <td><?= __d('enumeration', 'ProvisioningStatusEnum.'.$p['status']); ?></td>
        <td><?= filter_var($p['comment'], FILTER_SANITIZE_SPECIAL_CHARS); ?></td>
        <td><?= __d('enumeration', 'ProvisioningStatusEnum.'.$p['laststatus']); ?></td>
        <td><?= filter_var($p['lastcomment'], FILTER_SANITIZE_SPECIAL_CHARS); ?></td>
        <td><?= filter_var($p['identifier'] ?? "", FILTER_SANITIZE_SPECIAL_CHARS); ?></td>
        <td><?= !empty($p['timestamp']) ? $this->Time->nice($p['timestamp'], $vv_tz) : ""; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

