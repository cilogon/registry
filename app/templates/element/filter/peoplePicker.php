<?php
/**
 * COmanage Registry People Picker
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

use Cake\Utility\{Inflector};

// $columns = the passed parameter $indexColumns as found in columns.inc; provides overrides for labels and sorting.
$columns = $vv_indexColumns;

$wrapperCssClass = 'filter-active';
if(empty($options['active'])) {
  $wrapperCssClass = 'filter-inactive';
}

$label = Inflector::humanize(
  Inflector::underscore(
    $options['label'] ?? $columns[$key]['label']
  )
);

// Get the Field configuration
$formParams = $this->Filter->calculateFieldParams($key, $label);
if(!empty($formParams['value']) && $key == 'person_id') {
  $formParams['fullName'] = $this->Filter->getFullName((int)$formParams['value']);
}
$vv_autocomplete_arguments['formParams'] = $formParams;

?>

<div class="filter-standard <?= $wrapperCssClass ?>">
  <?= $this->element('peopleAutocomplete', $vv_autocomplete_arguments) ?>
</div>