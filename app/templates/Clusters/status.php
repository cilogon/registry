<?php
/**
 * COmanage Registry Clusters Status View
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

use Cake\Utility\Inflector;
use App\Lib\Util\StringUtilities;
?>

<div class="page-title-container">
  <div class="page-title">
    <h1><?= $vv_title; ?></h1>
  </div>
</div>

<?= $this->element('flash') // Flash messages ?>

<!-- XXX this link needs to be output to look like a standard action button/link -->
<?= $this->Html->link(
  "Assign Cluster Accounts",
  [
    'action' => 'assign',
    '?' => [
      'person_id' => $vv_subject->id
    ]
  ]
) ?>

<!-- Our view is similar to index.php -->
<div class="table-container">
  <table id="provisioningstatus-table" class="index-table list-mode with-actions">
    <thead>
      <tr>
        <td class="actions"></td>
        <th><?= __d('controller', 'Clusters', [1]); ?></th>
        <th><?= __d('field', 'status'); ?></th>
        <th><?= __d('field', 'comment'); ?></th>
      </tr>
    </thead>

    </tbody>
      <?php foreach($vv_cluster_statuses as $s): ?>
      <?php
        // eg: CoreCluster
        $plugin = StringUtilities::pluginPlugin($s['cluster']->plugin);
        // eg: UnixClusters
        $pluginModel = StringUtilities::pluginModel($s['cluster']->plugin);
        // eg: unix_cluster
        $property = StringUtilities::pluginToEntityField($s['cluster']->plugin);
      ?>
      <tr>
        <td class="actions">
          <div class="field-actions">
          <?php
            // Build the row actions
            $action_args = array();
            $action_args['vv_attr_id'] = $s['cluster']->id;
            $action_args['vv_actions'] = [
              [
                'order' => $this->Menu->getMenuOrder('Default'),
                'icon' => 'manage_accounts',
                'url' => [
                  'plugin' => $plugin,
                  'controller' => $pluginModel,
                  'action' => 'manage',
                  $s['cluster']->$property->id,
                  '?' => [
                    'person_id' => $vv_subject->id
                  ]
                ],
                'label' => __d('operation', 'manage')
              ]
            ];
  
            print $this->element('menuAction', $action_args);
          ?>
          </div>
        </td>
        <td><?= filter_var($s['cluster']->description, FILTER_SANITIZE_SPECIAL_CHARS); ?></td>
        <td><?= __d('enumeration', 'ClusterStatusEnum.'.$s['status']) ?></td>
        <td><?= filter_var($s['comment'], FILTER_SANITIZE_SPECIAL_CHARS); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
