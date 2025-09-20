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

if(empty($vv_obj))
  return;

// If this is an archived record, include a link to the parent (active) record
$clAttr = $vv_obj->changelogAttributeName();
$parentLink = null;

if(!empty($vv_obj->$clAttr)) {
  $parentLink = $this->Html->link(
    $vv_obj->$clAttr,
    ['action'  => 'edit', $vv_obj->$clAttr]
  );
}

// We'll render an index of all archived records if we are the current active record,
// or just the current metadata if we are an archived record
?>

<?= __d('information', 'changelog') ?>

<?php if(!empty($vv_archives)): // $vv_obj is an active record ?>
<div class="table-container">
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
      <td><?= $vv_obj->modified ?></td>
      <td><?= $vv_obj->actor_identifier ?></td>
    </tr>
<?php foreach($vv_archives as $archive): ?>
    <tr>
      <td><?=
        $this->Html->link(
          $archive->id,
          ['action' => 'view', $archive->id]
        )
      ?></td>
      <td><?= $archive->revision ?></td>
      <td><?= $archive->modified ?></td>
      <td><?= $archive->actor_identifier ?></td>
    </tr>
<?php endforeach; // $archive ?>
  </table>
</div>
<?php else: // $vv_archives -- $vv_obj is an archive record ?>
<div class="table-container">
  <table>
    <tr>
      <th><?= __d('field', 'changelog.deleted') ?></th>
      <td><?= __d('enumeration', 'YesBooleanEnum.'.($vv_obj->deleted ? '1' : '0')) ?></td>
    </tr>
    <tr>
      <th><?= __d('field', 'changelog.revision') ?></th>
      <td><?= $vv_obj->revision; ?></td>
    </tr>
    <tr>
      <th><?= __d('field', 'modified') ?></th>
      <td><?= $vv_obj->modified ?></td>
    </tr>
    <tr>
      <th><?= __d('field', 'changelog.actor_identifier') ?></th>
      <td><?= $vv_obj->actor_identifier ?></td>
    </tr>
    <tr>
      <th><?= __d('field', 'changelog.parent') ?></th>
      <td><?= $parentLink ?></td>
    </tr>
  </table>
</div>
<?php endif; // $vv_archives ?>