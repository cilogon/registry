<?php
/**
 * COmanage Registry External Identity Sources Retrieve View
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

use \Cake\Utility\Inflector;

  // Include the plugin subnavigation
  $subnav = [
    'name' => 'plugin',
    'active' => 'search'
  ];

  // Generate the subnavigation title and tabs
  print $this->element('subnavigation', $subnav);
?>

<div class="pageTitleContainer">
  <div class="pageTitle">
    <h2><?= $vv_title ?></h2>
  </div>
  <?php
    // Action list for top menu dropdown / button listing
    $action_args = array();
    $action_args['vv_attr_id'] =  $vv_eis->id;
    $action_args['vv_actions'][] = [
      'order' => 1,
      'icon' => 'sync',
      'url' => [
        'controller'  => 'external-identity-sources',
        'action'      => 'sync',
        $vv_eis->id,
        '?'           => ['source_key' => $vv_eis_record['source_key']]
      ],
      'label' => __d('operation', 'ExternalIdentitySources.sync')
    ];
    
    if(!empty($vv_external_identity_record)) {
      $action_args['vv_actions'][] = [
        'order' => 2,
        'icon' => 'visibility',
        'url' => [
          'controller'  => 'external-identities',
          'action'      => 'view',
          $vv_external_identity_record->external_identity_id
        ],
        'label' => __d('operation', 'view.a', [__d('controller', 'ExternalIdentities', [1])])
      ];
      $action_args['vv_actions'][] = [
        'order' => 3,
        'icon' => 'visibility',
        'url' => [
          'controller'  => 'ext-identity-source-records',
          'action'      => 'view',
          $vv_external_identity_record->id
        ],
        'label' => __d('operation', 'view.a', [__d('controller', 'ExtIdentitySourceRecords', [1])])
      ];
    }
    
    print '<div class="field-actions top-links">';
    print $this->element('menuAction', $action_args);
    print '</div>';
  ?>
</div>

<!-- insert explainer box -->
<div class="alert alert-warning co-alert" role="alert">
  <div class="alert-body d-flex align-items-center">
    <span class="alert-title d-flex align-items-center">
      <span class="material-icons-outlined alert-icon">report_problem</span>
    </span>
    <span class="alert-message">        
      <?= __d('information', 'ExternalIdentitySources.retrieve'); ?>
    </span>
  </div>
</div>

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
          <td class="eis-meta eis-item"><?= __d('controller', 'ExternalIdentitySources', 1); ?></td>
          <td class="eis-meta eis-value"><?= $vv_eis->description; ?></td>
        </tr>
        <tr>
          <td class="eis-meta eis-item"><?= __d('field', 'source_key'); ?></td>
          <td class="eis-meta eis-value"><?= $vv_eis_record['source_key']; ?></td>
        </tr>
      </tbody>
    </table>

    <h3><?= __d('controller', 'ExternalIdentities', 1) ?></h3>
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
        <?php foreach($vv_eis_record['entity_data']['names'] as $name): ?>
          <tr>
            <td class="eis-item"><?= __d('controller', 'Names', 1); ?></td>
            <td class="eis-type"><?= $name['type'] ??  '' ?></td>
            <td class="eis-value">
              <ul>
              <?php
                foreach($name as $field => $value) {
                  if($field == 'type') continue;
  
                  print "<li>" . $field . ": " . $value . "</li>\n";
                }
              ?>
              </ul>
            </td>
          </tr>
        <?php endforeach; // $name ?>
        <!-- Date of birth, and any other External Identity single value attributes
             that may come later -->
        <tr>
          <td class="eis-item"><?= __d('field', 'date_of_birth'); ?></td>
          <td class="eis-type"></td>
          <td class="eis-value"><?= $vv_eis_record['entity_data']['date_of_birth'] ?? ""; ?></td>
        </tr>
        <!-- MVEAs associated with the External Identity -->
        <?php
          foreach([
            'pronouns',
            'identifiers',
            'email_addresses',
            'addresses',
            'telephone_numbers',
            'urls'
          ] as $model) {
            if(!empty($vv_eis_record['entity_data'][$model])) {
              foreach($vv_eis_record['entity_data'][$model] as $m) {
                print "<tr>\n";
                print "<td class=\"eis-item\">" . __d('controller', Inflector::camelize($model), 1) . "</td>\n";
                print "<td class=\"eis-type\">" . ($m['type'] ?? "") . "</td>\n";
                print "<td class=\"eis-value\"><ul>\n";
                foreach($m as $field => $value) {
                  if($field == 'type') continue;

                  print "<li>" . $field . ": " . $value . "</li>\n";
                }
                print "</ul></td>\n";
                print "</tr>\n";
              }
            }
          }
        ?>
        <!-- Ad Hoc Attributes associated with the External Identity -->
        <?php
          if(!empty($vv_eis_record['entity_data']['ad_hoc_attributes'])) {
            foreach($vv_eis_record['entity_data']['ad_hoc_attributes'] as $aha) {
              print "<tr>\n";
              print "<td class=\"eis-item\">" . __d('controller', 'AdHocAttributes', 1) . "</td>\n";
              print "<td class=\"eis-type\">" . $aha['tag'] . "</td>\n";
              print "<td class=\"eis-value\">" . $aha['value'] . "</td>\n";
              print "</tr>\n";
            }
          }
        ?>
      </tbody>
    </table>
    
    <?php if(!empty($vv_eis_record['entity_data']['external_identity_roles'])): ?>
      <h3><?= __d('controller', 'ExternalIdentityRoles', 1) ?></h3>
      <table id="view-external-identity-source-record" class="eis-table">
        <thead>
          <tr>
            <th><?= __d('field','item') ?></th>
            <th><?= __d('field','type') ?></th>
            <th><?= __d('field','value') ?></th>
          </tr>
        </thead>
        <tbody>
        <!-- External Identity Roles, with their associated single value attributes -->
        <?php
          if(!empty($vv_eis_record['entity_data']['external_identity_roles'])) {
            foreach($vv_eis_record['entity_data']['external_identity_roles'] as $role) {

              print "<tr>\n";
              print "<td class=\"eis-eir eis-item\">" . __d('field', 'role_key') . "</td>\n";
              print "<td class=\"eis-eir eis-type\"></td>\n";
              print "<td class=\"eis-eir eis-value\">" . $role['role_key'] . "</td>\n";
              print "</tr>\n";

              foreach(array_keys($role) as $field) {
                if($field == 'role_key' || is_array($role[$field])) continue;

                print "<tr>\n";
                print "<td class=\"eis-eir eis-item\">" . __d('field', $field) . "</td>\n";
                print "<td class=\"eis-eir eis-type\"></td>\n";
                print "<td class=\"eis-eir eis-value\">" . $role[$field] . "</td>\n";
                print "</tr>\n";
              }
            }
          }
        ?>
        <!-- MVEAs associated with the External Identity Role -->
        <?php
          foreach([
            'addresses',
            'telephone_numbers',
            'urls'
          ] as $model) {
            if(!empty($role[$model])) {
              foreach($role[$model] as $m) {
                print "<tr>\n";
                print "<td class=\"eis-eir eis-item\">" . __d('controller', Inflector::camelize($model), 1) . "</td>\n";
                print "<td class=\"eis-eir eis-type\">" . ($m['type'] ?? "") . "</td>\n";
                print "<td class=\"eis-eir eis-value\"><ul>\n";
                foreach($m as $field => $value) {
                  if($field == 'type') continue;

                  print "<li>" . $field . ": " . $value . "</li>\n";
                }
                print "</ul></td>\n";
                print "</tr>\n";
              }
            }
          }
        ?>
        <!-- Ad Hoc Attributes associated with the External Identity Role -->
        <?php
          if(!empty($role['ad_hoc_attributes'])) {
            foreach($role['ad_hoc_attributes'] as $aha) {
              print "<tr>\n";
              print "<td class=\"eis-eir eis-item\">" . __d('controller', 'AdHocAttributes', 1) . "</td>\n";
              print "<td class=\"eis-eir eis-type\">" . $aha['tag'] . "</td>\n";
              print "<td class=\"eis-eir eis-value\">" . $aha['value'] . "</td>\n";
              print "</tr>\n";
            }
          }
        ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Finally the raw source record -->
    <h3><?= __d('field', 'source_record'); ?></h3>
    <div id="source-record-raw">
      <?php if(!empty($vv_eis_record['source_record'])): ?>
        <code class="source-record">
          <?= filter_var($vv_eis_record['source_record'], FILTER_SANITIZE_SPECIAL_CHARS) ?>
        </code>
      <?php else: ?>
        <div class="alert alert-info co-alert" role="alert">
          <div class="alert-body d-flex align-items-center">
                <span class="alert-title d-flex align-items-center">
                  <span class="material-icons-outlined alert-icon">report_problem</span>
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
