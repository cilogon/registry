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
use Cake\Collection\Collection;
use Cake\Utility\{Inflector, Hash};
use App\Lib\Enum\DateTypeEnum;


// $this->name = Models
$modelsName = $this->name;
// $modelName = Model
$modelName = Inflector::singularize($modelsName);
// $columns = the passed parameter $indexColumns as found in columns.inc; provides overrides for labels and sorting. 
$columns = $indexColumns;

// Get the query string and separate the search params from the non-search params
$query = $this->request->getQueryParams();
// Search attributes collection
$search_attributes_collection = new Collection($vv_searchable_attributes);
$alias_params = $search_attributes_collection->filter(fn ($val, $attr) => (is_array($val) && array_key_exists('alias', $val)) )
                                             ->extract('alias')
                                             ->unfold()
                                             ->toArray();
// For the non search params we need to search the alias params as well
$searchable_parameters = [
  ...array_keys($vv_searchable_attributes),
  ...$alias_params
  ];
$non_search_params = (new Collection($query))->filter( fn($value, $key) => !in_array($key, $searchable_parameters) )
                                             ->toArray();

// Filter the search params and take params with aliases into consideration
$search_params = [];
foreach ($vv_searchable_attributes as $attr => $value) {
  if(isset($query[$attr])) {
    $search_params[$attr] = $query[$attr];
    continue;
  }

  if(isset($value['alias'])
     && is_array($value['alias'])) {
    foreach ($value['alias'] as $alias_key) {
      if(isset($query[$alias_key])) {
        $search_params[$attr][$alias_key] = $query[$alias_key];
      }
    }
  }
}

// Begin the form
print $this->Form->create(null, [
  'id'   => 'top-filters-form',
  'type' => 'get'
]);

// Pass back the non-search params as hidden fields, but always exclude the page parameter
// because we need to start new searches on page one (or we're likely to end up with a 404).
if(!empty($non_search_params)) {
  foreach($non_search_params as $param => $value) {
    if($param != 'page') {
      print $this->Form->hidden(filter_var($param, FILTER_SANITIZE_SPECIAL_CHARS), array('default' => filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS))) . "\n";
    }
  }
}

// Boolean to distinguish between search filters and sort parameters
$hasActiveFilters = false;
?>

<div id="<?= $modelName . ucfirst($this->request->getParam('action')) ?>Search" class="top-filters">
  <fieldset>
    <!--  Top Filters toggle legend  -->
    <?= $this->element('filter/topFiltersToggle', compact('search_params')) ?>
    <div id="top-filters-fields">
      <div class="top-filters-fields-subgroups">
      <?php
        $field_booleans_columns = [];
        $field_datetime_columns = [];
        
        $inactiveFiltersCount = 0; // for re-balancing the columns and submit buttons

        foreach($vv_searchable_attributes as $key => $options) {
          if($options['type'] == 'boolean') {
            $field_booleans_columns[$key] = $options;
            continue;
          } elseif ($options['type'] == 'timestamp') {
            $field_datetime_columns[$key] = $options;
            continue;
          }

          $wrapperCssClass = 'filter-active';
          if(empty($options['active'])) {
            $wrapperCssClass = 'filter-inactive';
            $inactiveFiltersCount++;
          }


          $label = Inflector::humanize(
            Inflector::underscore(
              $options['label'] ?? $columns[$key]['label']
            )
          );

          if($options['type'] == 'date') {
            // Create a text field to hold our date value.
            print '<div class="top-filters-fields-date filter-standard ' . $wrapperCssClass . '">';
            print $this->Form->label($key, $label);
            print '<div class="d-flex">';
            print $this->Field->dateField($key, DateTypeEnum::DateOnly, $query)['controlCode'];
            print '</div>';
            print '</div>';
          } else {
            // text input
            $formParams = [
              'label' => $label,
              // The default type is text, but we might convert to select below
              'type' => 'text',
              'value' => (!empty($query[$key]) ? $query[$key] : ''),
              'required' => false,
              'class' => 'form-control'
            ];
          }
          
          // The populated variables are in plural while the column names are singular
          // Convention: It is a prerequisite that the vvar should be the plural of the column name
          $populated_vvar = lcfirst(Inflector::pluralize(Inflector::camelize($key)));
          if(isset($$populated_vvar)) {
            // If we have an AutoViewVar matching the name of this key,
            // convert to a select
            $formParams['type'] = 'select';
            $formParams['options'] = $$populated_vvar;
            if(isset($vv_searchable_attributes_extras[$key]['options'])) {
              // Flatten the custom options
              $customOptionsFlattened = Hash::flatten($vv_searchable_attributes_extras[$key]['options']);
              // Get the key of the place holder string
              $dataKey = array_search('@DATA@', $customOptionsFlattened, true);
              if($dataKey !== false) {
                $customOptionsFlattened[$dataKey] = $formParams['options'];
                $formParams['options'] = Hash::expand($customOptionsFlattened);
              }
            }
            // Allow empty so a filter doesn't require (eg) SOR
            $formParams['empty'] = true;
          }
          
          if($options['type'] != 'date') {
            print '<div class="filter-standard ' . $wrapperCssClass . '">';
            print $this->Form->control($key, $formParams);
            print '</div>';
          }
        }
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
      </div>

      <?php
      // Date Time filtering block
      if (!empty($field_datetime_columns)) {
        print $this->element(
          'filter/dateTimeFilters',
          compact('field_datetime_columns', 'query')
        );
      }
      ?>

      <?php $rebalanceColumns = (((count($vv_searchable_attributes) - $inactiveFiltersCount) % 2 == 1) && empty($field_booleans_columns)) ? ' class="tss-rebalance"' : ''; ?>
      <div id="top-filters-submit"<?= $rebalanceColumns ?>>
        
      <?php
        // Order of the submitted buttons is important here: the Enter key will submit the first (and we want the tab order to follow suit).
        // We reverse the visual order of all these buttons with CSS (flex-direction: row-reverse;).

        // search button (submit)
        $args = array();
        $args['id'] = 'top-filters-filter-button';
        $args['aria-label'] = __d('operation', 'filter');
        $args['class'] = 'submit-button spin btn btn-primary';
        print $this->Form->submit(__d('operation', 'filter'),$args);

        // clear button
        $args = array();
        $args['id'] = 'top-filters-clear';
        $args['class'] = 'clear-button spin btn btn-default';
        $args['aria-label'] = __d('operation', 'clear');
        $args['onclick'] = 'clearTopSearch(this.form)';
        print $this->Form->button(__d('operation', 'clear'),$args);

        // Options dropdown list
        if(!empty($vv_searchable_attributes)) {
          print $this->element(
            'filter/filterOptions',
            compact('vv_searchable_attributes', 'field_booleans_columns')
          );
        }
      ?>
      </div>
    </div>
  </fieldset>
</div>

<?= $this->Form->end(); ?>

