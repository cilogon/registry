<?php
/**
 * COmanage Registry People Confirm Delete View
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

// We basically render a confirmation form that POSTs back to the normal delete action
?>

<div class="page-title-container">
  <div class="page-title">
    <h1><?= $vv_title ?></h1>
  </div>
</div>

<?= $this->element('flash') // Flash messages ?>

<?= $this->element('notify/alert', [
  'message' => __d('operation', 'delete.confirm', [$vv_person->id]),
  'type' => 'warning'
]) ?>

<ul class="delete-info-list">
  <li>
    <em class="material-symbols-outlined" aria-hidden="true">info</em>
    <?= __d(
    'information',
    'People.delete.person', 
    [
      $this->Url->build(['action' => 'edit', $vv_person->id]),
      $vv_person->primary_name->full_name,
      $vv_person->id,
      $this->Url->build(['controller' => 'history-records', 'action' => 'index', '?' => ['person_id' => $vv_person->id]])
    ]) ?>

    <?php if(!empty($vv_person->person_roles)): ?>
    <ul>
      <?php foreach($vv_person->person_roles as $role): ?>
      <li><?= __d(
        'information',
        'People.delete.role',
        [
          $role->id,
          $role->cou->name ?? '-',
          $role->affiliation_type->display_name,
          $role->title ?: '-'
        ]
      ) ?></li>
      <?php endforeach; // role ?>
    </ul>
    <?php endif; // person_roles ?>
  
    <?php if(!empty($vv_person->external_identities)): ?>
    <ul>
      <?php foreach($vv_person->external_identities as $ei): ?>
      <li><?=
        !empty($ei->ext_identity_source_record)
        ? __d(
            'information',
            'People.delete.ei',
            [
              $ei->id,
              $ei->ext_identity_source_record->external_identity_source->description,
              $ei->ext_identity_source_record->source_key
            ]
          )
        // Legacy External Identities do not have Source Records
        : __d('information', 'People.delete.ei.legacy', [$ei->id])
        ?></li>
      <?php endforeach; // external_identities ?>
    </ul>
    <?php endif; // external_identities ?>
  
    <?php if(!empty($vv_person_cluster_status)): ?>
    <ul>
      <?php foreach($vv_person_cluster_status as $cs): ?>
      <li><?= __d('information', 'People.delete.cluster', [$cs['cluster']->description]) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; // vv_person_cluster_status ?>
  </li>

  <?php if(!empty($vv_person->sponsor_person_roles)): ?>
  <li>
    <em class="material-symbols-outlined" aria-hidden="true">info</em>
    <?= __d('information', 'People.delete.roles.sponsor', [count($vv_person->sponsor_person_roles)]) ?>
  </li>
  <?php endif; // sponsor_person_roles ?>

  <?php if(!empty($vv_person->manager_person_roles)): ?>
  <li>
    <em class="material-symbols-outlined" aria-hidden="true">info</em>
    <?= __d('information', 'People.delete.roles.manager', [count($vv_person->manager_person_roles)]) ?>
  </li>
  <?php endif; // manager_person_roles ?>

  <?php if(!empty($vv_person->actor_history_records)): ?>
  <li>
    <em class="material-symbols-outlined" aria-hidden="true">info</em>
    <?= __d('information', 'People.delete.history.actor', [count($vv_person->actor_history_records)]) ?>
  </li>
  <?php endif; // actor_history_records ?>

  <?php if(!empty($vv_person->job_history_records)): ?>
  <li>
    <em class="material-symbols-outlined" aria-hidden="true">info</em>
    <?= __d('information', 'People.delete.history.job', [count($vv_person->job_history_records)]) ?>
  </li>
  <?php endif; // actor_history_records ?>

  <?php if(!empty($vv_person->actor_notifications)): ?>
  <li>
    <em class="material-symbols-outlined" aria-hidden="true">info</em>
    <?= __d('information', 'People.delete.notifications.actor', [count($vv_person->actor_notifications)]) ?>
  </li>
  <?php endif; // actor_notifications ?>

  <?php if(!empty($vv_person->recipient_notifications)): ?>
  <li>
    <em class="material-symbols-outlined" aria-hidden="true">info</em>
    <?= __d('information', 'People.delete.notifications.recipient', [count($vv_person->recipient_notifications)]) ?>
  </li>
  <?php endif; // recipient_notifications ?>

  <?php if(!empty($vv_person->resolver_notifications)): ?>
  <li>
    <em class="material-symbols-outlined" aria-hidden="true">info</em>
    <?= __d('information', 'People.delete.notifications.resolver', [count($vv_person->resolver_notifications)]) ?>
  </li>
  <?php endif; // resolver_notifications ?>

  <?php if(!empty($vv_person->petitioner_petitions)): ?>
  <li>
    <em class="material-symbols-outlined" aria-hidden="true">info</em>
    <?= __d('information', 'People.delete.petitions.petitioner', [count($vv_person->petitioner_petitions)]) ?>
  </li>
  <?php endif; // petitioner_petitions ?>

  <?php if(!empty($vv_person->petition_history_records)): ?>
  <li>
    <em class="material-symbols-outlined" aria-hidden="true">info</em>
    <?= __d('information', 'People.delete.petitions.history.actor', [count($vv_person->petition_history_records)]) ?>
  </li>
  <?php endif; // petition_history_records ?>
</ul>

<?= // Begin the form
  $this->Form->create($vv_person, [
    'id'   => 'person-confirm-delete',
    'type' => 'post',
    'url'  => ['action' => 'delete', $vv_person->id]
  ]);
?>

<div id="danger-hard-delete" class="invisible">
  <?= $this->element('notify/alert', [
    'message' => __d('information', 'People.delete.hard'),
    'type' => 'danger'
  ]); ?>
</div>

<div class="submit-with-flags">
  <?= // This is the exposed button that the user clicks (but not the actual submit button)
    $this->Form->button(__d('operation', 'delete'),[
    'type' => 'button',
    'id' => 'person-delete-button',
    'class' => 'btn btn-primary',
    'data-bs-toggle' => 'modal',
    'data-bs-target' => '#dialog'
  ]) . "\n"; ?>

  <?= // Checkbox to switch to hard delete (instead of soft delete) 
    $this->Form->control('hard-delete', [
    'type' => 'checkbox',
    'label' => __d('operation', 'delete.hard.perform'),
    'class' => 'form-check-input',
    'id' => 'hard-delete'
  ]) . "\n"; ?>

  <?= // This is the hidden submit button that is activated by our confirmation dialog.
    $this->Form->submit(__d('operation', 'delete'), [
    'id' => 'delete-confirmed',
    'class' => 'invisible'
  ]) . "\n"; ?>
</div>

<?= $this->Form->end() . "\n"; ?>

<script nonce="<?= $vv_js_nonce ?>">
  $(function() {
    // Toggle hard and soft delete information
    $('#hard-delete').click(function() {
      if($(this).is(':checked')) {
        $('#danger-hard-delete').removeClass('invisible');
        $('#person-delete-button')
          .removeClass('btn-primary')
          .addClass('btn-secondary')
          .text('<?= __d('operation', 'delete.hard') ?>'
        );
      } else {
        $('#danger-hard-delete').addClass('invisible');
        $('#person-delete-button')
          .removeClass('btn-secondary')
          .addClass('btn-primary')
          .text('<?= __d('operation', 'delete') ?>'
        );
      }
    });
    
    // Delete confirmation
    $('#person-delete-button').click(function(e) {
      e.preventDefault();
      jsConfirmGeneric(
        '<?= __d('operation', 'delete.confirm', [$vv_person->primary_name->full_name]) ?>',
        '',
        'delete-confirmed',
        '<?= __d('operation', 'delete') ?>',
        '<?= __d('operation', 'cancel') ?>',
        '<?= __d('operation', 'delete') ?>'
      );
    });
  });
</script>

<!-- XXX clean up from here -->
<?php return; ?>

<?php if(empty($vv_search_attrs)): ?>
  <?= $this->element('notify/alert', [
// XXX consolidate language keys? (this is OrgSources, but it's the same)
    'message' => __d('information', 'ExternalIdentitySources.search.attrs.none'),
    'type' => 'information'
  ]) ?>
<?php else: // vv_search_attrs ?>
  <?php
    
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
// XXX make the same change to EIS/serach
            'placeholder' => __d('information', $label),
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
            <th><?= __d('field', 'name'); ?></th>
            <th><?= __d('field', 'source_key'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($vv_search_results as $source_key => $r): ?>
          <tr class="linked-row">
            <td>
              <?= $this->Html->link(                    
                    $r['rec']['name'],
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
            <td><?= (string)$source_key // Cast to string in case we have a numeric source key ?></td>
          </tr>
          <?php endforeach; // $vv_search_results ?>
        </tbody>
      </table>
    </div>
  <?php endif; // vv_search_results == 0 ?>
<?php endif; // vv_search_results ?>
