<?php
/**
 * COmanage Registry Active Top Button Element
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

use Cake\Utility\{Inflector, Hash};

// Construct aria-controls string
$aria_controls = $key;
// We save the name of the id into a dataset variable, data-identifier. This is an easy way
// to store the correct identifier in the case of dates. Dates have two search fields for each column
// which makes it more complicated to keep track of the id.
$data_identifier = is_array($params) ? implode(':', array_keys($params)) : $key;

// The populated variables are in plural while the column names are singular
// Convention: It is a prerequisite that the vvar should be the plural of the column name
$populated_vvar = lcfirst(Inflector::pluralize(Inflector::camelize($key)));
$button_label = 'Range';
if(isset($$populated_vvar) && isset($$populated_vvar[$params])) {
  $button_label = $$populated_vvar[$params];
} elseif(!is_array($params)) {
  $button_label = $params;
  if(isset($vv_searchable_attributes_extras)) {
    $flattenedSearchableAttributesExtras = Hash::flatten($vv_searchable_attributes_extras);
    $filteredFlattenedSearchableAttributesExtras = array_filter(
      $flattenedSearchableAttributesExtras,
      static fn($flKey) => str_contains($flKey, $params),
      ARRAY_FILTER_USE_KEY
    );
    if(!empty($filteredFlattenedSearchableAttributesExtras)) {
      $button_label = array_pop($filteredFlattenedSearchableAttributesExtras);
    }
  }
}

$filter_title =
  Inflector::humanize(
    Inflector::underscore(
      $vv_searchable_attributes[$key]['label'] ?? $columns[$key]['label']
    )
  );

?>

<button class="top-filters-active-filter deletebutton spin btn btn-default btn-sm"
        data-identifier="<?= $data_identifier ?>"
        type="button" aria-controls="<?= $aria_controls ?>"
        title="<?= __d('operation', 'clear.filters',[2]) ?>">
  <em class="material-icons" aria-hidden="true">cancel</em>
  <span class="top-filters-active-filter-title"><?= $filter_title ?></span>
  <?php if($vv_searchable_attributes[$key]['type'] != 'boolean'): ?>
  <span class="top-filters-active-filter-value">
    <?= filter_var($button_label, FILTER_SANITIZE_SPECIAL_CHARS) ?>
  </span>
  <?php endif; ?>
</button>