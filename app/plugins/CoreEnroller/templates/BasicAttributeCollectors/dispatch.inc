<?php
/**
 * COmanage Registry Basic Attribute Collector Petition Fields
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

// Make the Form fields editable
$this->Field->enableFormEditMode();
?>
  
<li class="fields-name">
  <fieldset>
    <legend class="fieldset-name"><?=  __d('field','name') ?></legend>
    <div class="fieldset-info">
      
      <?php foreach(['honorific', 'given', 'middle', 'family', 'suffix'] as $n): ?>
        <?php if(in_array($n, $vv_permitted_name_fields)): ?>
          <div class="fieldset-field fields-<?= $n ?>">
            <label for="<?= $n ?>"><?= __d('field', $n) ?></label>
            <?= in_array($n, $vv_required_name_fields) ? $this->element('form/requiredSpan') : ''?>
            <?= $this->element('form/infoDiv/default', [
              'vv_field_arguments' => [
                'fieldName' => $n,
                'fieldOptions' => [
                  'required' => in_array($n, $vv_required_name_fields),
                  'default' => $petition_basic_attribute_sets[$n] ?? ""
                ]
              ]]);
            ?>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
    
    </div>
  </fieldset>
</li>
  
<?php
  print $this->element('CoreEnroller.listItem', [
    'arguments' => [
      'fieldName' => 'mail',
      'fieldOptions' => [
        'default' => $petition_basic_attribute_sets['mail'] ?? ""
      ]
  ]]);
