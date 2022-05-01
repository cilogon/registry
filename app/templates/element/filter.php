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

// $this->name = Models
$modelsName = $this->name;
// $modelName = Model
$modelName = \Cake\Utility\Inflector::singularize($modelsName);

// Get the query string and separate the search params from the non-search params
$query = $this->request->getQueryParams();
$non_search_params = array_diff_key($query, $vv_searchable_attributes);
$search_params = array_intersect_key($query, $vv_searchable_attributes);

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
      <em class="material-icons">search</em>
      <?= __d('operation', 'filter'); ?>
      

      <?php if(!empty($search_params)):?>
        <span id="top-filters-active-filters">
        <?php foreach($search_params as $key => $params): ?>
          <?php
            // Construct aria-controls string
            $aria_controls = $key;

            // We have active filters - not just a sort.
            $hasActiveFilters = true;
          ?>
          <button class="top-filters-active-filter deletebutton spin btn btn-default btn-sm" type="button" aria-controls="<?php print $aria_controls; ?>" title="<?= __d('operation', 'clear.filters',[2]); ?>">
             <em class="material-icons">cancel</em>
             <span class="top-filters-active-filter-title">
               <?= $vv_searchable_attributes[$key]['label']; ?>
             </span>
             <span class="top-filters-active-filter-value">
               <?= filter_var($search_params[$key], FILTER_SANITIZE_SPECIAL_CHARS); ?>
             </span>
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
      <div id="top-filters-fields-subgroups">
      <?php
        $field_subgroup_columns = array();

        foreach($vv_searchable_attributes as $key => $options) {
          $formParams = [
            'label' => $options['label'],
            // The default type is text, but we might convert to select below
            'type' => 'text',
            'value' => (!empty($query[$key]) ? $query[$key] : ''),
            'required' => false,
          ];

          // The populated variables are in plural while the column names are singular
          // Convention: It is a prerequisite that the vvar should be the plural of the column name
          $populated_vvar = Cake\Utility\Inflector::pluralize($key);
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
      </div>
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
