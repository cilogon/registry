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
        <th><?= __d('field', 'status'); ?></th>
        <th><?= __d('field', 'comment'); ?></th>
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
            $action_args['vv_attr_id'] =  $p['target']->id;
            $action_args['vv_actions'] = [
              [
                'order' => $this->Menu->getMenuOrder('Default'),
                'icon' => 'start',
                'url' => [
                  'controller' => $vv_primary_link_model,
                  'action' => 'provision',
                  $vv_primary_link_obj->id,
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
                    StringUtilities::entityToForeignKey($vv_primary_link_obj) => $vv_primary_link_obj->id
                  ]
                ],
                'label' => __d('controller', 'ProvisioningHistoryRecords', [99])
              ]
            ];
  
            print $this->element('menuAction', $action_args);
          ?>
          </div>
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

