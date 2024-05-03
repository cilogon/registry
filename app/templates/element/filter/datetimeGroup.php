<?php
/**
 * COmanage Registry Filter Element - for index view filtering
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
 * $field_datetime_columns : array, required
 */

declare(strict_types = 1);


use App\Lib\Enum\DateTypeEnum;
use Cake\Utility\Inflector;

// $columns = the passed parameter $indexColumns as found in columns.inc;
// provides overrides for labels and sorting.
$columns = $vv_indexColumns;

?>

<?php if(!empty($field_datetime_columns)): ?>
  <?php foreach($field_datetime_columns as $key => $options): ?>
    <div class="input">
      <div class="top-search-date-label">
        <?= !empty($columns[$key]['label']) ? $columns[$key]['label'] : Inflector::humanize($key) ?>
      </div>
      <div class="top-filters-fields-dates">
        <!--     Start at       -->
        <div class="top-search-start-date">
          <?php
          // Create a text field to hold our value.
          print $this->Form->label("{$key}_starts_at", __d('field', 'starts_at'), ['class' => 'filter-datepicker-lbl']);
          print $this->Field->dateField("{$key}_starts_at", DateTypeEnum::DateOnly);
          ?>
        </div>
        <!--     Ends at       -->
        <div class="top-search-end-date">
          <?php
          // Create a text field to hold our value.
          print $this->Form->label("{$key}_ends_at", __d('field','ends_at'), ['class' => 'filter-datepicker-lbl']);
          print $this->Field->dateField("{$key}_ends_at", DateTypeEnum::DateOnly);
          ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
