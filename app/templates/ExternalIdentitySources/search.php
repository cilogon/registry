<?php
/**
 * COmanage Registry External Identity Sources Search View
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

<?php if(empty($vv_search_attrs)): ?>
<!-- XXX this needs updating -->
  <div class="co-info-topbox">
    <em class="material-icons">info</em>
    <div class="co-info-topbox-text">
      <?php print __d('information', 'ExternalIdentitySources.search.attrs.none'); ?>
    </div>
  </div>
<?php else: // vv_search_attrs ?>
<ul id="eis_search_fields" class="fields form-list">
<?php
  // Begin the form
  print $this->Form->create(null, [
    'id'   => 'eis-search-form',
    'type' => 'post'
  ]);

  foreach($vv_search_attrs as $field => $label) {
    $key = "search." . $field;

    print "<li>" . $this->Form->control($key,
                               [
                                 'label' => $label
                               ])
                               . "</li>\n";

  }
?>
  <li class="fields-submit">
    <div class="field-name">
    </div>
    <div class="field-info">
      <?php
        print $this->Form->submit(__d('operation', 'search'));
        print $this->Form->end();
      ?>
    </div>
  </li>
</ul>
<?php endif; // vv_search_attrs ?>

<?php if(isset($vv_search_results)): ?>
  <?php if(count($vv_search_results) == 0): ?>
    <p>
      <?= __d('result','search.none'); ?>
    </p>
  <?php else: // vv_search_results == 0 ?>
    <div class="table-container">
      <?php
        $indexTableClasses = 'index-table list-mode';
        if (!empty($rowActions)) {
          $indexTableClasses .= ' with-actions';
        }
      ?>
      <table id="<?= 'eis-search-table'; ?>" class="<?= $indexTableClasses; ?>">
        <thead>
          <tr>
            <th><?= __d('field', 'sorid'); ?></th>
            <!-- Because we get array data rather than entities, we can't construct
                 a full name using the entity virtual field -->
            <th><?= __d('field', 'given'); ?></th>
            <th><?= __d('field', 'family'); ?></th>
            <th><?= __d('field', 'mail'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($vv_search_results as $r): ?>
          <tr>
            <td>
              <?= $this->Html->link(
                    $r['source_key'],
                    [
                      'action' => 'retrieve',
                      $this->request->getParam('pass')[0],
                      '?' => ['source_key' => $r['source_key']]
                    ]
                  ); ?>
            </td>
            <td><?= $r['names'][0]['given']; ?></td>
            <td><?= $r['names'][0]['family']; ?></td>
            <td>
              <?php
                if(!empty($r['email_addresses'][0]['mail'])) {
                  print $r['email_addresses'][0]['mail'];
                }
              ?>
            </td>
          </tr>
          <?php endforeach; // $vv_search_results ?>
        </tbody>
      </table>
    </div>
  <?php endif; // vv_search_results == 0 ?>
<?php endif; // vv_search_results ?>
