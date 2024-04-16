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

<div id="<?= $modelName . ucfirst($this->request->getParam('action')) ?>Search" class="top-filters">
  <fieldset>
    <legend id="top-filters-toggle">
      <em class="material-icons top-filters-search-icon" aria-hidden="true">search</em>
      <span class="top-filters-title">
        <?= __d('operation', 'filter'); ?>
      </span>

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
            $populated_vvar = lcfirst(Inflector::pluralize(Inflector::camelize($key)));
            $button_label = isset($$populated_vvar) ?
              $$populated_vvar[ $search_params[$key] ] :
              (is_array($search_params[$key]) ? 'Range' : $search_params[$key]);
          ?>
          <button class="top-filters-active-filter deletebutton spin btn btn-default btn-sm" data-identifier="<?= $data_identifier ?>" type="button" aria-controls="<?php print $aria_controls; ?>" title="<?= __d('operation', 'clear.filters',[2]); ?>">
            <em class="material-icons" aria-hidden="true">cancel</em>
            <span class="top-filters-active-filter-title">
              <?= Inflector::humanize(Inflector::underscore($vv_searchable_attributes[$key]['label'] ?? $columns[$key]['label'])) ?>
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
        
        $inactiveFiltersCount = 0; // for re-balancing the columns and submit buttons

        foreach($vv_searchable_attributes as $key => $options) {
          if($options['type'] == 'boolean') {
            $field_booleans_columns[$key] = $options;
            continue;
          } elseif ($options['type'] == 'timestamp') {
            $field_datetime_columns[$key] = $options;
            continue;
          }

          $label = Inflector::humanize(
            Inflector::underscore(
              $options['label'] ?? $columns[$key]['label']
            )
          );

          if($options['type'] == 'date') {
            // Date fields use a date picker (e.g. DOB)
            // (Note that timestamps are handled specially. See below.)
            $opts = [];
            $opts['type'] = 'text'; // date inputs must be text for accessibility reasons for now.
            $opts['required'] = false;
            $opts['pattern'] = '\d{4}-\d{2}-\d{2}';
            $opts['placeholder'] = 'YYYY-MM-DD';
            $opts['title'] = __d('field','datepicker.enterDate');
            $opts['class'] = 'form-control datepicker';
            $opts['id'] = str_replace("_", "-", $key);

            $date = \Cake\I18n\FrozenTime::parseDate(date("Y-m-d"),'yyyy-MM-dd');
            if(!empty($query[$key])) {
              $date = \Cake\I18n\FrozenTime::parseDate($query[$key],'yyyy-MM-dd');
              $opts['value'] = $date->i18nFormat("yyyy-MM-dd");
            }
            $pickerDate = $date->i18nFormat("yyyy-MM-dd");
            $pickerFloor = $date->subYears(100)->i18nFormat("yyyy-MM-dd");
            
            $date_args = [
              'fieldName' => $key,
              'pickerDate' => $pickerDate,
              'pickerType' => \App\Lib\Enum\DateTypeEnum::DateOnly,
              'pickerFloor' => $pickerFloor
            ];

            $wrapperCssClass = 'filter-active';
            if(empty($options['active'])) {
              $wrapperCssClass = 'filter-inactive';
              $inactiveFiltersCount++;
            }
            
            // Create a text field to hold our date value.
            print '<div class="top-filters-fields-date filter-standard ' . $wrapperCssClass . '">';
            print $this->Form->label($key, $label);
            print '<div class="d-flex">';
            print $this->Form->text($key, $opts) . $this->element('datePicker', $date_args);
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
            // Allow empty so a filter doesn't require (eg) SOR
            $formParams['empty'] = true;
          }

          $wrapperCssClass = 'filter-active';
          if(empty($options['active'])) {
            $wrapperCssClass = 'filter-inactive';
            $inactiveFiltersCount++;
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

      <?php $rebalanceColumns = (((count($vv_searchable_attributes) - $inactiveFiltersCount) % 2 == 1) && empty($field_booleans_columns)) ? ' class="tss-rebalance"' : ''; ?>
      <div id="top-filters-submit"<?php print $rebalanceColumns ?>>
        
        <?php
          // Order of the submit buttons is important here: the Enter key will submit the first (and we want the tab order to follow suit).  
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
        ?>
        
        <?php if(!empty($vv_searchable_attributes)): ?>
          <div id="top-filters-options-container">
            <button id="top-filters-options-button" class="btn btn-default options-button dropdown-toggle" 
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <?= __d('menu', 'options') ?>
            </button>
            <div class="dropdown-menu dropdown-menu-lg-end" aria-labelledby="top-filters-options-button">
              <h4><?= __d('menu','available.filters') ?></h4>
              <div id="top-filters-options">
                <?php foreach($vv_searchable_attributes as $key => $options): ?>
                  <?php if($options['type'] == 'timestamp' || $options['type'] == 'boolean') continue; // skip timestamp types and put booleans at the bottom of the list ?>
                  <div class="form-check filter-selector filter-selector-text">
                    <input class="form-check-input" 
                           type="checkbox" 
                           value="<?= Cake\Utility\Inflector::dasherize($key) ?>" 
                           id="filter-selector-<?= $key ?>"<?= !empty($options['active']) ? ' checked' : '' ?>>
                    <label class="form-check-label" for="filter-selector-<?= $key ?>">
                      <?= $options['label'] ?>
                    </label>
                  </div>
                <?php endforeach; ?>
                <?php foreach($field_booleans_columns as $key => $options): ?>
                  <div class="form-check filter-selector filter-selector-boolean">
                    <input class="form-check-input" 
                           type="checkbox" 
                           value="<?= Cake\Utility\Inflector::dasherize($key) ?>" 
                           id="filter-selector-<?= $key ?>"<?= !empty($options['active']) ? ' checked' : '' ?>>
                    <label class="form-check-label" for="filter-selector-<?= $key ?>">
                      <?= $options['label'] ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </fieldset>
</div>

<?= $this->Form->end(); ?>

