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
 * $columns                 : array, required
 * $key                     : string, required
 * $options                 : array, required
 */

declare(strict_types = 1);

use Cake\Utility\Inflector;
use App\Lib\Enum\ApplicationStateEnum;

// $columns = the passed parameter $indexColumns as found in columns.inc; provides overrides for labels and sorting.
$columns = $vv_indexColumns;
$modelsName = $this->name;

$label = Inflector::humanize(
  Inflector::underscore(
    strtolower($options['label'] ?? $columns[$key]['label'])
  )
);

$propertyName = $this->ApplicationState->constructComplexStateTag(
  [ApplicationStateEnum::SearchBlockOptions, $modelsName, $label]
);
$searchBlockOptionsState = $this->ApplicationState->getValue($propertyName, '');

// take into consideration the Application State
$wrapperCssClass = 'filter-inactive';
if (
  ($searchBlockOptionsState === ''
    && isset($options['active'])
    && filter_var($options['active'], FILTER_VALIDATE_BOOLEAN))
  ||
  ($searchBlockOptionsState !== '' && filter_var($searchBlockOptionsState, FILTER_VALIDATE_BOOLEAN))
) {
  $wrapperCssClass = 'filter-active';
  $this->set('vv_active_search_filters_count', (int)$vv_active_search_filters_count+1);
}

// Get the Field configuration
$formParams = $this->Filter->calculateFieldParams($key, $label);

?>

<div class="filter-standard <?= $wrapperCssClass ?>">
  <?= $this->Form->control($key, $formParams) ?>
</div>