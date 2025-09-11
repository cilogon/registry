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

declare(strict_types = 1);

// Required by the subnavigation partial
$modelsName = $this->getName();
// Subnavigation calculations
if(file_exists(ROOT . DS . 'templates' . DS . 'Standard/subnavigation.inc')) {
  include(ROOT . DS . 'templates' . DS . 'Standard/subnavigation.inc');
}
?>

<div class="page-title-container">
  <div class="page-title">
    <h2><?= $vv_title ?></h2>
  </div>
</div>

<?= $this->element('flash') // Flash messages ?>

<?php if(empty($vv_search_attrs)): ?>
  <?= $this->element('notify/alert', [
    'message' => __d('information', 'ExternalIdentitySources.search.attrs.none'),
    'type' => 'information'
  ]) ?>
<?php else: // vv_search_attrs ?>
  <?php
    // Begin the form
    print $this->Form->create(null, [
      'id'   => 'eis-search-form',
      'type' => 'post'
    ]);
  ?>
  <?php if(count($vv_search_attrs) == 1): ?>
    <?php // We have only a single search query, so render it gracefully. Currently that's true for FileSourceTable and ApiSourceTable. ?>
    <?php  foreach($vv_search_attrs as $field => $label): ?>
      <?php  $key = "search." . $field; ?>
      <div class="eis-single-search">
        <?= $this->Form->label($key, $label, ['class' => 'visually-hidden']) ?>
        <?= $this->Form->control($key, 
          [
            'class' => 'form-control', 
            'label' => false,
            'placeholder' => __d('information','ExternalIdentitySources.search.single.placeholder'),
            'onfocus' => 'this.select()'
          ]) 
        ?>
        <?= $this->Form->submit(__d('operation', 'search'), ['class' => 'btn-sm']); ?>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <ul id="eis-search-fields" class="fields form-list">  
      <?php  foreach($vv_search_attrs as $field => $label): ?>
        <?php  $key = "search." . $field; ?>
        <li>
          <div class="field">
            <div class="field-name">
              <div class="field-title">
                <?= $this->Form->label($key, $label) ?>
              </div>
            </div>
            <div class="field-info">
              <?= $this->Form->control($key, ['label' => false]) ?>
            </div>
          </div>
        </li>
      <?php endforeach; ?> 
      <li class="fields-submit">
        <div class="field-name">
        </div>
        <div class="field-info">
          <?php
            print $this->Form->submit(__d('operation', 'search'));
          ?>
        </div>
      </li>
    </ul>
  <?php endif; ?>
  <?= $this->Form->end() ?>
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
            <th><?= __d('field', 'source_key'); ?></th>
            <!-- Because we get array data rather than entities, we can't construct
                 a full name using the entity virtual field -->
            <th><?= __d('field', 'given'); ?></th>
            <th><?= __d('field', 'family'); ?></th>
            <th><?= __d('field', 'mail'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($vv_search_results as $source_key => $r): ?>
          <tr class="linked-row">
            <td>
              <?= $this->Html->link(
                    $source_key,
                    [
                      'action' => 'retrieve',
                      $this->request->getParam('pass')[0],
                      '?' => ['source_key' => $source_key]
                    ],
                    [
                      'class' => 'row-link row-link-retrieve'
                    ]
                  ); ?>
            </td>
            <td><?= $r['names'][0]['given'] ?? '' ?></td>
            <td><?= $r['names'][0]['family'] ?? '' ?></td>
            <td><?= $r['email_addresses'][0]['mail'] ?? '' ?></td>
          </tr>
          <?php endforeach; // $vv_search_results ?>
        </tbody>
      </table>
    </div>
  <?php endif; // vv_search_results == 0 ?>
<?php endif; // vv_search_results ?>
