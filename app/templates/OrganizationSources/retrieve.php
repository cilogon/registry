<?php
/**
 * COmanage Registry Organization Sources Retrieve View
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

declare(strict_types = 1);

use \Cake\Utility\Inflector;
?>

<div class="page-title-container">
  <div class="page-title">
    <h2><?= $vv_title ?></h2>
  </div>
  <?php
    // Action list for top menu dropdown / button listing
    $action_args = array();
    $action_args['vv_attr_id'] =  $vv_os->id;

    $action_args['vv_actions'][] = [
      'order' => 1,
      'icon' => 'sync',
      'url' => [
        'controller'  => 'organization-sources',
        'action'      => 'sync',
        $vv_os->id,
        '?'           => ['source_key' => $vv_os_record['source_key']]
      ],
// XXX do we need our own language keys?
      'label' => __d('operation', 'ExternalIdentitySources.sync')
    ];
    
    if(!empty($vv_organization_record)) {
      if(!empty($vv_organization_record->organization_id)) {
        $action_args['vv_actions'][] = [
          'order' => 2,
          'icon' => 'visibility',
          'url' => [
            'controller'  => 'organizations',
            'action'      => 'view',
            $vv_organization_record->organization_id
          ],
          'label' => __d('operation', 'view.a', [__d('controller', 'Organizations', [1])])
        ];
      }

      $action_args['vv_actions'][] = [
        'order' => 3,
        'icon' => 'visibility',
        'class' => 'cm-modal-link nospin', // launch this in a modal
        'dataAttrs' => [
          ['data-cm-modal-title',__d('controller', 'OrganizationSourceRecords', 1)]
        ],
        'url' => [
          'controller'  => 'organization-source-records',
          'action'      => 'view',
          $vv_organization_record->id
        ],
        'label' => __d('operation', 'view.a', [__d('controller', 'OrganizationSourceRecords', [1])])
      ];
    }
    
    print '<div class="field-actions top-links">';
    print $this->element('menuAction', $action_args);
    print '</div>';
  ?>
</div>

<!-- insert explainer box -->
<?php
  // Default notice is "not synced"
// XXX again, do we need our own key?
  $noticeText = __d('information', 'ExternalIdentitySources.retrieve.notSynced');

  if(!empty($vv_organization_record->organization_id)) {
    // There is an OrganizationSourceRecord for this source key, which corresponds
    // to an Organization.

    // Construct the link
    $link = $this->Html->link(
// XXX update key?
      __d('information', 'ExternalIdentitySources.cached'),
      [
        'controller' => 'organization-source-records',
        'action' => 'view',
        $vv_organization_record->id
      ],
      [
        'class' => 'cm-modal-link nospin',
        'target' => '_top',
        'data-cm-modal-title' => __d('controller', 'OrganizationSourceRecords', 1)
      ],
    );

    // Construct the message
    $noticeText = __d(
      'information',
// XXX update key?
      'ExternalIdentitySources.retrieve',
      $link
    );
  }
?>
<?= $this->element('notify/alert', ['message' => $noticeText,'type' => 'information']) ?>

<?= $this->element('flash') // Flash messages ?>

<div class="innerContent">
  <div class="table-container">
    <h3><?= __d('information','ExternalIdentitySourceRecords.metadata') ?></h3>
    <table id="view-external-identity-source-record-metadata" class="eis-table">
      <thead>
        <tr>
          <th><?= __d('field','item') ?></th>
          <th><?= __d('field','value') ?></th>
        </tr>
      </thead>
      <tbody>
        <!-- Record metadata -->
        <tr>
          <td class="eis-meta eis-item"><?= __d('controller', 'OrganizationSources', 1); ?></td>
          <td class="eis-meta eis-value"><?= $vv_os->description; ?></td>
        </tr>
        <tr>
          <td class="eis-meta eis-item"><?= __d('field', 'source_key'); ?></td>
          <td class="eis-meta eis-value"><?= $vv_os_record['source_key']; ?></td>
        </tr>
      </tbody>
    </table>

    <h3><?= __d('controller', 'Organizations', 1) ?></h3>
    <?php if($vv_os_record['entity_data'] != null): ?>
    <table id="view-external-identity-source-record" class="eis-table">
      <thead>
        <tr>
          <th><?= __d('field','item') ?></th>
          <th><?= __d('field','type') ?></th>
          <th><?= __d('field','value') ?></th>
        </tr>
      </thead>
      <tbody>
        <!-- We order attributes according to their likely importance,
             starting with names. Because $vv_eis_record is an array and
             not an entity, we can't use entity methods to get virtual fields
             like full_name. -->
        <!-- Organization single value attributes -->
        <tr>
          <td class="eis-item"><?= __d('field', 'name'); ?></td>
          <td class="eis-type">
            <?php
              // The logo isn't exactly a type but it seems like a good place to render it
              // since we don't need this column for anything else

              if(!empty($vv_os_record['entity_data']['logo_url'])) {
                print $this->Html->image($vv_os_record['entity_data']['logo_url'], ['alt' => 'logo']) . "\n";
              }
            ?>
          </td>
          <td class="eis-value"><?= $vv_os_record['entity_data']['name'] ?? ""; ?></td>
        </tr>

        <?php
          foreach([
            'description',
            'saml_scope',
            'type'
          ] as $field): 
        ?>
        <tr>
          <td class="eis-item"><?= __d('field', $field); ?></td>
          <td class="eis-type"></td>
          <td class="eis-value"><?= $vv_os_record['entity_data'][$field] ?? ""; ?></td>
        </tr>
        <?php endforeach; // $field ?>
        <!-- MVEAs associated with the Organization -->
        <?php
          foreach([
            'identifiers',
            'urls',
            'contacts'
          ] as $model) {
            if(!empty($vv_os_record['entity_data'][$model])) {
              foreach($vv_os_record['entity_data'][$model] as $m) {
                print "<tr>\n";
                print "<td class=\"eis-item\">" . __d('controller', Inflector::camelize($model), 1) . "</td>\n";
                print "<td class=\"eis-type\">" . ($m['type'] ?? "") . "</td>\n";
                print "<td class=\"eis-value\"><ul>\n";
                foreach($m as $field => $value) {
                  if($field == 'type') continue;
                  
                  print "<li>" . __d('field', 'listpair', [$field, $value]) . "</li>\n";
                }
                print "</ul></td>\n";
                print "</tr>\n";
              }
            }
          }
        ?>
      </tbody>
    </table>
    <?php endif; // !empty(entity_data) ?>

    <!-- Finally the raw source record -->
    <h3><?= __d('field', 'source_record'); ?></h3>
    <div id="source-record-raw">
      <?php if(!empty($vv_os_record['source_record'])): ?>
        <code class="source-record">
          <?= filter_var($vv_os_record['source_record'], FILTER_SANITIZE_SPECIAL_CHARS) ?>
        </code>
      <?php else: ?>
        <div class="alert alert-info co-alert" role="alert">
          <div class="alert-body d-flex align-items-center">
                <span class="alert-title d-flex align-items-center">
                  <span class="material-symbols-outlined alert-icon">report_problem</span>
                </span>
            <span class="alert-message">        
                  <?= __d('field', 'ExternalIdentitySources.source_record.empty') ?>
                </span>
          </div>
        </div>
      <?php endif; ?>
    </div>
    
  </div>
</div>
