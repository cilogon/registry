<?php
/**
 * COmanage Registry Top Filters Toggle Element
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

// $search_params passed as a parameter

$hasActiveFilters = false;

?>


<legend id="top-filters-toggle">
  <em class="material-icons top-filters-search-icon" aria-hidden="true">search</em>
  <span class="top-filters-title">
        <?= __d('operation', 'filter'); ?>
      </span>

  <?php if(!empty($search_params)):?>
    <span id="top-filters-active-filters">
        <?php
        foreach($search_params as $key => $params) {
          // We have active filters - not just a sort.
          $hasActiveFilters = true;
          print $this->element('filter/activeTopButton', compact('key', 'params'));
        }
        ?>
      <?php if($hasActiveFilters): ?>
        <button id="top-filters-clear-all-button" class="filter-clear-all-button spin btn" type="button" aria-controls="top-filters-clear" onclick="event.stopPropagation()">
            <?= __d('operation', 'clear.filters',[2]); ?>
          </button>
      <?php endif; ?>
      </span>
  <?php endif; ?>
  <button class="cm-toggle nospin" aria-expanded="false" aria-controls="top-filters-fields" type="button"><em class="material-icons drop-arrow">arrow_drop_down</em></button>
</legend>
