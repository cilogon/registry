<?php
  /*
   * COmanage Registry Changelog Element
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
   * @since         COmanage Registry v5.2.0
   * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
   */
  
  use App\Lib\Enum\ApplicationStateEnum;
  
  if(empty($vv_obj) || empty($vv_archives)) {
    return;
  }

  // If this is an archive record, include a link to the parent (active) record
  $clAttr = $vv_obj->changelogAttributeName();
  $parentUrl = null;

  if(!empty($vv_obj->$clAttr)) {
    $parentUrl = $this->Url->build(
      ['action' => 'edit', $vv_obj->$clAttr]
    );
  }

  // Get the changelog open/closed state
  $changeLogState = $this->ApplicationState->getValue(ApplicationStateEnum::ChangeLogState, '');
  $changeLogStateId = $this->ApplicationState->getId(ApplicationStateEnum::ChangeLogState);

  // We'll render an index of all archive records if we are the current active record,
  // or just the current metadata if we are an archive record
?>

<div id="changelog-container" class="accordion">
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button<?= $changeLogState !== 'show' ? ' collapsed' : ''?>" 
              type="button"
              data-coid="<?= $vv_cur_co->id ?? '' ?>"
              data-appstateid="<?= $changeLogStateId ?>"
              data-stateattr="<?= ApplicationStateEnum::ChangeLogState?>"
              data-webroot="<?= $this->request->getAttribute('webroot') ?>"
              data-username="<?= $vv_user['username'] ?? '' ?>"
              data-personid="<?= $vv_person_id ?? '' ?>"
              data-bs-toggle="collapse" 
              data-bs-target="#changelog"
              aria-expanded="false" 
              aria-controls="changelog"
              aria-label="<?= $changeLogState === 'show' ?
                __d('information','changelog.aria.expanded') :
                __d('information','changelog.aria.collapsed')?>">
        <?= __d('information', 'changelog') ?>
      </button>
    </h2>
    <div id="changelog" class="accordion-collapse collapse <?= $changeLogState ?>" data-bs-parent="#changelog-container">
      <div class="accordion-body">
        <?php if(empty($vv_obj->$clAttr && !empty($vv_archives))): // $vv_obj is an active record ?>
          <table>
            <tr>
              <th><?= __d('field', 'id') ?></th>
              <th><?= __d('field', 'changelog.revision') ?></th>
              <th><?= __d('field', 'modified') ?></th>
              <th><?= __d('field', 'changelog.actor_identifier') ?></th>
            </tr>
            <!-- start with the current record -->
            <tr>
              <td><?=
                  // In general it's confusing to have a link back to the record currently being displayed,
                  // so just echo the ID without making it a link
                  $vv_obj->id
                ?></td>
              <td><?= $vv_obj->revision ?></td>
              <td><?= $this->Time->nice($vv_obj->modified, $vv_tz) ?></td>
              <td><?= $vv_obj->actor_identifier ?></td>
            </tr>
            <?php foreach($vv_archives as $archive): ?>
              <tr class="linked-row">
                <td><?=
                    $this->Html->link(
                      $archive->id,
                      ['action' => 'view', $archive->id],
                      ['class' => 'row-link']
                    )
                  ?></td>
                <td><?= $archive->revision ?></td>
                <td><?= $this->Time->nice($archive->modified, $vv_tz) ?></td>
                <td><?= $archive->actor_identifier ?></td>
              </tr>
            <?php endforeach; // $archive ?>
          </table>
        <?php else: // $vv_archives -- $vv_obj is an archive record ?>
          <ul>
            <li>
              <div class="fieldname"><?= __d('field', 'changelog.deleted') ?></div>
              <div class="fieldval"><?= __d('enumeration', 'YesBooleanEnum.' . ($vv_obj->deleted ? '1' : '0')) ?></div>
            </li>
            <li>
              <div class="fieldname"><?= __d('field', 'changelog.revision') ?></div>
              <div class="fieldval"><?= $vv_obj->revision; ?></div>
            </li>
            <li>
              <div class="fieldname"><?= __d('field', 'changelog.actor_identifier') ?></div>
              <div class="fieldval"><?= $vv_obj->actor_identifier ?></div>
            </li>
            <li class="linked-row">
              <div class="fieldname"><?= __d('field', 'changelog.active') ?></div>
              <div class="fieldval changelog-active-link-container">
                <?php if(!empty($vv_obj->$clAttr)): ?>
                  <a class="changelog-active-link row-link" href="<?= $parentUrl ?>">
                    <div class="changelog-parent-id"><?= $vv_obj->$clAttr ?></div>
                  </a>
                  <button class="changelog-active-link-button btn btn-sm btn-primary">
                    <?= __d('operation','changelog.return') ?>
                  </button>
                <?php endif; ?>
              </div>
            </li>
          </ul>
        <?php endif; // $vv_archives ?>
      </div>
    </div>
  </div>
</div>
  