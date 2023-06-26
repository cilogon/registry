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
use Cake\Utility\Inflector;


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

<div id="<?= $modelName . ucfirst($this->request->getParam('action')); ?>Search" class="top-filters">
  <fieldset>
    <legend id="top-filters-toggle">
      <em class="material-icons" aria-hidden="true">search</em>
      <?= __d('operation', 'filter'); ?>

      <?php if(!empty($search_params)):?>
        <span id="top-filters-active-filters">
        <?php foreach($search_params as $key => $params): ?>
          <?php
            // Construct aria-controls string
            $aria_controls = $key;
            // We save the name of the id into a dataset variable, data-identifier. This is an easy way
            // to store the correct identifier in the case of dates. Dates have two search fields for each column
            // which makes it more complicated to keep track of the id.
            $data_identifier = is_array($params) ? implode(':', array_keys($params)) : $key;

            // We have active filters - not just a sort.
            $hasActiveFilters = true;

            // The populated variables are in plural while the column names are singular
            // Convention: It is a prerequisite that the vvar should be the plural of the column name
            $populated_vvar = Inflector::pluralize($key);
            $button_label = isset($$populated_vvar) ?
              $$populated_vvar[ $search_params[$key] ] :
              (is_array($search_params[$key]) ? 'Range' : $search_params[$key]);
          ?>
          <button class="top-filters-active-filter deletebutton spin btn btn-default btn-sm" data-identifier="<?= $data_identifier ?>" type="button" aria-controls="<?php print $aria_controls; ?>" title="<?= __d('operation', 'clear.filters',[2]); ?>">
            <em class="material-icons" aria-hidden="true">cancel</em>
            <span class="top-filters-active-filter-title">
              <?= !empty($columns[$key]['label']) ? $columns[$key]['label'] : $vv_searchable_attributes[$key]['label'] ?>
            </span>
            <?php if($vv_searchable_attributes[$key]['type'] != 'boolean'): ?>
              <span class="top-filters-active-filter-value">
                <?= filter_var($button_label, FILTER_SANITIZE_SPECIAL_CHARS); ?>
              </span>
            <?php endif; ?>
          </button>
        <?php endforeach; ?>
          <?php if($hasActiveFilters): ?>
            <button id="top-filters-clear-all-button" class="filter-clear-all-button spin btn" type="button" aria-controls="top-filters-clear" onclick="event.stopPropagation()">
              <?= __d('operation', 'clear.filters',[2]); ?>
           </button>
          <?php endif; ?>
      </span>
      <?php endif; ?>
      <button class="cm-toggle nospin" aria-expanded="false" aria-controls="top-filters-fields" type="button"><em class="material-icons drop-arrow">arrow_drop_down</em></button>
    </legend>
    <div id="top-filters-fields">
      <div class="top-filters-fields-subgroups">
      <?php
        $field_booleans_columns = [];
        $field_datetime_columns = [];
        
        if(!empty($columns)) {
          // To make our filters consistently ordered with the index columns, sort the $vv_searchable_attributes
          // by the keys of columns.inc $indexColumns (passed in to this View element as $indexColumns and referenced
          // as "$columns"). The fields found in $columns will be placed first in the resulting array.
          // The result should only include fields that exist in the original $vv_searchable_attributes array.
          $vv_searchable_attributes = array_intersect_key(array_replace(array_flip(array_keys($columns)), $vv_searchable_attributes), $vv_searchable_attributes);
        }
        
        foreach($vv_searchable_attributes as $key => $options) {
          if($options['type'] == 'boolean') {
            $field_booleans_columns[$key] = $options;
            continue;
          } elseif ($options['type'] == 'timestamp') {
            $field_datetime_columns[$key] = $options;
            continue;
          }
          
          if($options['type'] == 'date') {
            // date picker
            $formParams = [
              'label' => !empty($columns[$key]['label']) ? $columns[$key]['label'] : $options['label'],
              'type' => 'text', // date inputs must be text for accessibility reasons for now.
              'value' => (!empty($query[$key]) ? $query[$key] : ''),
              'required' => false,
              'pattern' => '\d{4}-\d{2}-\d{2}',
              'placeholder' => 'YYYY-MM-DD',
              'title' => __d('field','datepicker.enterDate'),
              'class' => 'form-control datepicker'
            ];
          } else {
            // text input
            $formParams = [
              'label' => !empty($columns[$key]['label']) ? $columns[$key]['label'] : $options['label'],
              // The default type is text, but we might convert to select below
              'type' => 'text',
              'value' => (!empty($query[$key]) ? $query[$key] : ''),
              'required' => false,
              'class' => 'form-control'
            ];
          }
          
          // The populated variables are in plural while the column names are singular
          // Convention: It is a prerequisite that the vvar should be the plural of the column name
          $populated_vvar = Inflector::pluralize($key);
          if(isset($$populated_vvar)) {
            // If we have an AutoViewVar matching the name of this key,
            // convert to a select
            $formParams['type'] = 'select';
            $formParams['options'] = $$populated_vvar;
            // Allow empty so a filter doesn't require (eg) SOR
            $formParams['empty'] = true;
          }

          print $this->Form->control($key, $formParams);
        }
      ?>
      <?php if(!empty($field_booleans_columns)): ?>
        <div class="top-search-checkboxes input">
          <div class="top-search-checkbox-fields">
            <?php foreach($field_booleans_columns as $key => $options): ?>
              <div class="form-check form-check-inline">
                <?php
                  print $this->Form->label(!empty($columns[$key]['label']) ? $columns[$key]['label'] : $key);
                  print $this->Form->checkbox($key, [
                    'id' => str_replace("_", "-", $key),
                    'class' => 'form-check-input',
                    'checked' => $query[$key] ?? 0,
                    'hiddenField' => false,
                    'required' => false
                  ]);
                ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
      </div>
      
      <?php if(!empty($field_datetime_columns)): ?>
        <div class="top-filters-fields-subgroups">
        <?php foreach($field_datetime_columns as $key => $options): ?>
          <div class="input">
            <div class="top-search-date-label">
              <?= !empty($columns[$key]['label']) ? $columns[$key]['label'] : Inflector::humanize($key) ?>
            </div>
            <div class="top-filters-fields-dates">
              <!--     Start at       -->
              <div class="top-search-start-date">
                <div class="d-flex">
                  <?php
                  // A datetime field will be rendered as plain text input with adjacent date and time pickers
                  // that will interact with the field value. Allowing direct access to the input field is for
                  // accessibility purposes.
                  $starts_field = $key . "_starts_at";
                  $coptions = [];
                  $coptions['class'] = 'form-control datepicker';
                  $coptions['label'] = __d('field','starts_at');
                  $coptions['required'] = false;
                  $coptions['placeholder'] = '';
//                  $coptions['placeholder'] = 'YYYY-MM-DD HH:MM:SS';
                  $coptions['pattern'] = '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}';
                  $coptions['title'] = __d('field','datepicker.enterDateTime');
                  $coptions['id'] = str_replace("_", "-", $starts_field);

                  $pickerDate = '';
                  if(!empty($query[$starts_field])) {
                    $starts_date = \Cake\I18n\FrozenTime::parse($query[$starts_field]);
                    // Adjust the time back to the user's timezone
                    $coptions['value'] = $starts_date->i18nFormat("yyyy-MM-dd HH:mm:ss", $this->get('vv_tz'));
                    $pickerDate = $starts_date->i18nFormat("yyyy-MM-dd", $this->get('vv_tz'));
                  }

                  $date_args = [
                    'fieldName' => $starts_field,
                    'pickerDate' => $pickerDate
                  ];
                  // Create a text field to hold our value.
                  print $this->Form->label($starts_field, __d('field','starts_at'), ['class' => 'filter-datepicker-lbl']);
                  print $this->Form->text($starts_field, $coptions) . $this->element('datePicker', $date_args);
                  ?>
                </div>
              </div>
              <!--     Ends at       -->
              <div class="top-search-end-date">
                <div class="d-flex">
                  <?php
                  // A datetime field will be rendered as plain text input with adjacent date and time pickers
                  // that will interact with the field value. Allowing direct access to the input field is for
                  // accessibility purposes.
                  $ends_field = $key . "_ends_at";
                  $coptions = [];
                  $coptions['class'] = 'form-control datepicker';
                  $coptions['required'] = false;
                  $coptions['placeholder'] = ''; // todo: Make this configurable
//                  $coptions['placeholder'] = 'YYYY-MM-DD HH:MM:SS';
                  $coptions['pattern'] = '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}';
                  $coptions['title'] = __d('field','datepicker.enterDateTime');
                  $coptions['label'] = __d('field','ends_at');
                  $coptions['id'] = str_replace("_", "-", $ends_field);

                  $pickerDate = '';
                  if(!empty($query[$ends_field])) {
                    // Adjust the time back to the user's timezone
                    $ends_date = \Cake\I18n\FrozenTime::parse($query[$ends_field]);
                    $coptions['value'] = $ends_date->i18nFormat("yyyy-MM-dd HH:mm:ss", $this->get('vv_tz'));
                    $pickerDate = $ends_date->i18nFormat("yyyy-MM-dd", $this->get('vv_tz'));
                  }

                  $date_args = [
                    'fieldName' => $ends_field,
                    'pickerDate' => $pickerDate
                  ];
                  // Create a text field to hold our value.
                  print $this->Form->label($ends_field, __d('field','ends_at'), ['class' => 'filter-datepicker-lbl']);
                  print $this->Form->text($ends_field, $coptions) . $this->element('datePicker', $date_args);
                  ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php $rebalanceColumns = ((count($vv_searchable_attributes)) % 2 != 0) ? ' class="tss-rebalance"' : ''; ?>
      <div id="top-filters-submit"<?php print $rebalanceColumns ?>>
        <?php
          $args = array();
          // search button (submit)
          $args['id'] = 'top-filters-filter-button';
          $args['aria-label'] = __d('operation', 'filter');
          $args['class'] = 'submit-button spin btn btn-primary';
          print $this->Form->submit(__d('operation', 'filter'),$args);

          // clear button
          $args['id'] = 'top-filters-clear';
          $args['class'] = 'clear-button spin btn btn-default';
          $args['aria-label'] = __d('operation', 'clear');
          $args['onclick'] = 'clearTopSearch(this.form)';
          print $this->Form->button(__d('operation', 'clear'),$args);
        ?>
      </div>
    </div>
  </fieldset>
</div>

<?= $this->Form->end(); ?>
