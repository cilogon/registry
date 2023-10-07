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
      <?=  $this->Alert->alert($b, 'warning') ?>
    <?php endforeach; // $banners ?>
  <?php endif; // $banners ?>
</div>

<!-- insert explainer box -->

<?= __d('information', 'ExternalIdentitySources.retrieve'); ?>

<ul>
<li><?= $this->Html->link(
      __d('operation', 'ExternalIdentitySources.sync'),
      [
        'controller'  => 'external-identity-sources',
        'action'      => 'sync',
        $vv_eis->id,
        '?'           => ['source_key' => $vv_eis_record['source_key']]
      ]
    ); ?></li>
<?php if(!empty($vv_external_identity_record)): ?>
<li><?= $this->Html->link(
      __d('operation', 'view.a', [__d('controller', 'ExternalIdentities', [1])]),
      [
        'controller'  => 'external-identities',
        'action'      => 'view',
        $vv_external_identity_record->external_identity_id
      ]
    ); ?></li>
<li><?= $this->Html->link(
      __d('operation', 'view.a', [__d('controller', 'ExtIdentitySourceRecords', [1])]),
      [
        'controller'  => 'ext-identity-source-records',
        'action'      => 'view',
        $vv_external_identity_record->id
      ]
    ); ?></li>
<?php endif; // $vv_external_identity_record ?>
</ul>

<div class="innerContent">
  <div class="table-container">
    <table id="view_external_identity_source_record">
      <tbody>
        <!-- Record metadata -->
        <tr>
          <td><?= __d('controller', 'ExternalIdentitySources', 1); ?></td>
          <td></td>
          <td><?= $vv_eis->description; ?></td>
        </tr>
        <tr>
          <td><?= __d('field', 'source_key'); ?></td>
          <td></td>
          <td><?= $vv_eis_record['source_key']; ?></td>
        </tr>
        <!-- We order attributes according to their likely importance,
             starting with names. Because $vv_eis_record is an array and
             not an entity, we can't use entity methods to get virtual fields
             like full_name. -->
        <?php foreach($vv_eis_record['entity_data']['names'] as $name): ?>
          <td><?= __d('controller', 'Names', 1); ?></td>
          <td><?= $name['type']; ?></td>
          <td>
            <ul>
            <?php
              foreach($name as $field => $value) {
                if($field == 'type') continue;

                print "<li>" . $field . ": " . $value . "</li>\n";
              }
            ?>
            </ul>
          </td>
        <?php endforeach; // $name ?>
        <!-- Date of birth, and any other External Identity single value attributes
             that may come later -->
        <tr>
          <td><?= __d('field', 'date_of_birth'); ?></td>
          <td></td>
          <td><?= $vv_eis_record['entity_data']['date_of_birth'] ?? ""; ?></td>
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
                print "<td>" . __d('controller', Inflector::camelize($model), 1) . "</td>\n";
                print "<td>" . ($m['type'] ?? "") . "</td>\n";
                print "<td><ul>\n";
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
              print "<td>" . __d('controller', 'AdHocAttributes', 1) . "</td>\n";
              print "<td>" . $aha['tag'] . "</td>\n";
              print "<td>" . $aha['value'] . "</td>\n";
              print "</tr>\n";
            }
          }
        ?>
        <!-- External Identity Roles, with their associated single value attributes -->
        <?php
          if(!empty($vv_eis_record['entity_data']['external_identity_roles'])) {
            foreach($vv_eis_record['entity_data']['external_identity_roles'] as $role) {
// In particular this should stand out somehow
              print "<tr>\n";
              print "<td colspan=3>" . __d('controller', 'ExternalIdentityRoles', 1) . "</td>\n";
              print "</tr>\n";

              print "<tr>\n";
              print "<td>" . __d('field', 'role_key') . "</td>\n";
              print "<td></td>\n";
              print "<td>" . $role['role_key'] . "</td>\n";
              print "</tr>\n";

              foreach(array_keys($role) as $field) {
                if($field == 'role_key' || is_array($role[$field])) continue;

                print "<tr>\n";
                print "<td>" . __d('field', $field) . "</td>\n";
                print "<td></td>\n";
                print "<td>" . $role[$field] . "</td>\n";
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
                print "<td>" . __d('controller', Inflector::camelize($model), 1) . "</td>\n";
                print "<td>" . ($m['type'] ?? "") . "</td>\n";
                print "<td><ul>\n";
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
              print "<td>" . __d('controller', 'AdHocAttributes', 1) . "</td>\n";
              print "<td>" . $aha['tag'] . "</td>\n";
              print "<td>" . $aha['value'] . "</td>\n";
              print "</tr>\n";
            }
          }
        ?>
        <!-- Finally the raw source record -->
        <tr>
          <td>
            <?= __d('field', 'source_record'); ?><br />
            <span class="field-desc"><?= __d('field', 'ExternalIdentitySources.source_record.desc'); ?></span>
          </td>
          <td></td>
          <td>
            <code class="source-record">
            <?php
              if(!empty($vv_eis_record['source_record'])) {
                print filter_var($vv_eis_record['source_record'], FILTER_SANITIZE_SPECIAL_CHARS);
              }; 
            ?>
            </code>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
