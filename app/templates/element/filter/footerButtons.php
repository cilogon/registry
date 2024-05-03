<?php
/**
 * COmanage Registry Top Filters Submit area buttons Element
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


/*
 * Parameters:
 * $field_booleans_columns : array, required
 */

declare(strict_types = 1);

use Cake\Collection\Collection;

$classes = '';
$inactiveFiltersCount = (new Collection($vv_searchable_attributes))->filter(fn($col) => boolval($col['active']) === false)
                                                                   ->count();
if ((count($vv_searchable_attributes) - $inactiveFiltersCount) % 2 === 1
    &&
    empty($field_booleans_columns)
) {
  $classes .= ' class="tss-rebalance"';
}

?>

<div id="top-filters-submit"<?= $classes ?>>

  <?php
  // Order of the submitted buttons is important here: the Enter key will submit the first (and we want the tab order to follow suit).
  // We reverse the visual order of all these buttons with CSS (flex-direction: row-reverse;).

  // search button (submit)
  $args = array();
  $args['id'] = 'top-filters-filter-button';
  $args['aria-label'] = __d('operation', 'filter');
  $args['class'] = 'submit-button spin btn btn-primary';
  print $this->Form->submit(__d('operation', 'filter'), $args);

  // clear button
  $args = array();
  $args['id'] = 'top-filters-clear';
  $args['class'] = 'clear-button spin btn btn-default';
  $args['aria-label'] = __d('operation', 'clear');
  $args['onclick'] = 'clearTopSearch(this.form)';
  print $this->Form->button(__d('operation', 'clear'), $args);

  // Options dropdown list
  if(!empty($vv_searchable_attributes)) {
    print $this->element(
      'filter/options',
      compact('field_booleans_columns')
    );
  }
  ?>
</div>