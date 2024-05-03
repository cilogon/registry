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

declare(strict_types = 1);

use Cake\Utility\Inflector;


// $this->name = Models
$modelsName = $this->name;
// $modelName = Model
$modelName = Inflector::singularize($modelsName);

// Group Fields by Type
[ $search_params,
  $field_booleans_columns,
  $field_datetime_columns,
  $field_generic_columns ] = $this->Filter->explodeFieldsByType();

// Begin the form
print $this->Form->create(null, [
  'id'   => 'top-filters-form',
  'type' => 'get'
]);

// Hidden Form fields
foreach($this->Filter->getHiddenFields() as $param => $value) {
  print $this->Form->hidden(
      filter_var($param, FILTER_SANITIZE_SPECIAL_CHARS),
      array('default' => filter_var($value, FILTER_SANITIZE_SPECIAL_CHARS))) . PHP_EOL;
}

?>

<div id="<?= $modelName . ucfirst($this->request->getParam('action')) ?>Search" class="top-filters">
  <fieldset>
    <!--  Filter Legend  -->
    <?= $this->element('filter/legend', compact('search_params')) ?>

    <!--  Search TextBoxes/DropDowns/e.t.c.  -->
    <div id="top-filters-fields">
      <!--   Single Search Fields   -->
      <div class="top-filters-fields-subgroups">
      <?php
        foreach($field_generic_columns as $key => $options) {
          $elementArguments = compact('options', 'key');
          print match($options['type']) {
            'date'      => $this->element('filter/dateSingle', $elementArguments),
            default     => $this->element('filter/default', $elementArguments),
          };
        }
      ?>

      <!--   Checkboxes   -->
      <?= $this->element('filter/checkboxes', compact('field_booleans_columns')) ?>
      </div>

      <!--    Date/ Time search textboxes Group -->
      <div class="top-filters-fields-subgroups">
      <?= $this->element('filter/datetimeGroup', compact('field_datetime_columns'))
      ?>
      </div>

      <!--    Footer / Submit Buttons    -->
      <?= $this->element('filter/footerButtons', compact('field_booleans_columns'))
      ?>
    </div>
  </fieldset>
</div>

<?= $this->Form->end() ?>

