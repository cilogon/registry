<?php
/**
 * COmanage Registry Top Filters Checkboxes
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
 * $field_booleans_columns  : array, required
 */

declare(strict_types = 1);

// Get the query string and separate the search params from the non-search params
$query = $this->request->getQueryParams();

?>


<?php if(!empty($field_booleans_columns)): ?>
  <div class="top-filters-checkboxes input">
    <div class="top-filters-checkbox-fields">
      <?php foreach($field_booleans_columns as $key => $options): ?>
        <div class="filter-boolean <?= empty($options['active']) ? 'filter-inactive' : 'filter-active' ?>">
          <div class="form-check form-check-inline">
            <?php
            print $this->Form->label($options['label'] ?? $key);
            print $this->Form->checkbox($key, [
              'id' => str_replace("_", "-", $key),
              'class' => 'form-check-input',
              'checked' => $query[$key] ?? 0,
              'hiddenField' => false,
              'required' => false
            ]);
            ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
