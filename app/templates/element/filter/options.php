<?php
/**
 * COmanage Registry Filter Options
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

use App\Lib\Enum\ApplicationStateEnum;

$modelsName = $this->getName();

?>

<div id="top-filters-options-container">
  <button id="top-filters-options-button"
          class="btn btn-default options-button dropdown-toggle"
          type="button" data-bs-toggle="dropdown" aria-expanded="false">
    <?= __d('menu', 'options') ?>
  </button>
  <div class="dropdown-menu dropdown-menu-lg-end" aria-labelledby="top-filters-options-button">
    <h4><?= __d('menu','available.filters') ?></h4>
    <div id="top-filters-options">
      <?php foreach($vv_searchable_attributes as $key => $options): ?>
        <?php if($options['type'] == 'timestamp' || $options['type'] == 'boolean') continue; // skip timestamp types and put booleans at the bottom of the list ?>
        <?php
          $propertyName = $this->ApplicationState->constructComplexStateTag(
            [ ApplicationStateEnum::SearchBlockOptions, $modelsName, $options['label']]
          );
          $searchBlockOptionsState = $this->ApplicationState->getValue($propertyName, '');
          $appStateId = $this->ApplicationState->getId($propertyName);
        ?>
        <div class="form-check filter-selector filter-selector-text">
          <input class="form-check-input"
                 type="checkbox"
                 id="filter-selector-<?= $key ?>"
                 <?php
                 if (
                   ($searchBlockOptionsState === ''
                   && isset($options['active'])
                   && filter_var($options['active'], FILTER_VALIDATE_BOOLEAN))
                   ||
                   ($searchBlockOptionsState !== '' && filter_var($searchBlockOptionsState, FILTER_VALIDATE_BOOLEAN))
                 ) {
                   print 'checked';
                 }
                 ?>
                 value="<?= Cake\Utility\Inflector::dasherize($key) ?>"
                 data-coid="<?= $vv_cur_co->id ?? '' ?>"
                 data-appstateid="<?= $appStateId ?>"
                 data-model="<?= $this->getName() ?>"
                 data-stateattr="<?= $propertyName ?>"
                 data-webroot="<?= $this->request->getAttribute('webroot') ?>"
                 data-username="<?= $vv_user['username'] ?? '' ?>"
                 data-personid="<?= $vv_person_id ?? '' ?>"
          >
          <label class="form-check-label" for="filter-selector-<?= $key ?>">
            <?= $options['label'] ?>
          </label>
        </div>
      <?php endforeach; ?>
      <?php foreach($field_booleans_columns as $key => $options): ?>
        <?php
        $propertyName = $this->ApplicationState->constructComplexStateTag(
          [ ApplicationStateEnum::SearchBlockOptions, $modelsName, $options['label']]
        );
        $searchBlockOptionsState = $this->ApplicationState->getValue($propertyName, '');
        $appStateId = $this->ApplicationState->getId($propertyName);
        ?>
        <div class="form-check filter-selector filter-selector-boolean">
          <input class="form-check-input"
                 type="checkbox"
                 value="<?= Cake\Utility\Inflector::dasherize($key) ?>"
                 id="filter-selector-<?= $key ?>"
                 <?php
                 if (
                   ($searchBlockOptionsState === ''
                     && isset($options['active'])
                     && filter_var($options['active'], FILTER_VALIDATE_BOOLEAN))
                   ||
                   ($searchBlockOptionsState !== '' && filter_var($searchBlockOptionsState, FILTER_VALIDATE_BOOLEAN))
                 ) {
                   print 'checked';
                 }
                 ?>
                 data-coid="<?= $vv_cur_co->id ?? '' ?>"
                 data-appstateid="<?= $appStateId ?>"
                 data-model="<?= $this->getName() ?>"
                 data-stateattr="<?= $propertyName ?>"
                 data-webroot="<?= $this->request->getAttribute('webroot') ?>"
                 data-username="<?= $vv_user['username'] ?? '' ?>"
                 data-personid="<?= $vv_person_id ?? '' ?>">
          <label class="form-check-label" for="filter-selector-<?= $key ?>">
            <?= $options['label'] ?>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
