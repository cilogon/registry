<?php
/*
 * COmanage Registry Attribute Collectors display
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
 *
 * This generic modal dialog stub is used for confirmations, e.g. when deleting a record.
 * The text of the box is overridden with JavaScript, and the confirm button is intended to
 * click a CakePHP postLink or postButton in the DOM. Use jsConfirmGeneric() to call it.
 */

declare(strict_types = 1);

if (empty($vv_enrollment_attributes)) {
  print $this->element('emptyPetitionFlowStep', [], [
    'cache' => '_html_elements',
  ]);
  return;
}

?>

<ul>
  <?php foreach ($vv_enrollment_attributes as $attribute): ?>
    <?php $vv_petition_atttributes = collection($vv_obj->petition_attributes)->filter(fn($attr) => $attr->enrollment_attribute_id === $attribute->id) ?>
    <?php if($vv_petition_atttributes->count() === 1): ?>
      <?php
      $attr = $vv_petition_atttributes->first();
      $value = $attr->value ?? 'N/A';
      if(!empty($value) && str_ends_with($attribute->attribute, 'person_id')) {
        // We need to load the associated model data
        $associatedModel = $this->Petition->getRecordForId('person_id', $attr->value, ['PrimaryName']);
        $value = $associatedModel['primary_name']['given'] . ' '
          . $associatedModel['primary_name']['family'] . ' '
          .  '(ID: ' . $associatedModel['id'] . ')';
        $value = $this->Html->link($value,
          ['controller' => 'people', 'action' => 'edit', $attr->value]);
      } elseif(!empty($value) && str_ends_with($attribute->attribute, '_id')) {
        // We need to load the associated model data
        $associatedModel = $this->Petition->getRecordForId($attribute->attribute, $attr->value);
        $value = $associatedModel['name'] ?? $associatedModel['value'] ?? $associatedModel['description'] ?? 'N/A';
        $value = $this->Html->link($value,
          ['controller' => \App\Lib\Util\StringUtilities::foreignKeyToController($attribute->attribute), 'action' => 'edit', $attr->value]);
      }
      ?>
      <li class="petition-attr-<?= $this->Petition->getClassPostfixFromAttributeName($attribute->attribute) ?>">
        <h4 class="petition-attr-label"><?= $attribute->label ?></h4>
        <div class="petition-attr-value"><?= $value ?>
        </div>
      </li>
    <?php else: ?>
      <li class="petition-attr-<?= $this->Petition->getClassPostfixFromAttributeName($attribute->attribute) ?>">
        <h4 class="petition-attr-label"><?= $attribute->label ?></h4>
        <ul class="petition-attrs-subset">
          <?php foreach ($vv_petition_atttributes as $attr): ?>
            <li>
              <div class="petition-attrs-subset-label"><?= $attr->column_name ?></div>
              <div class="petition-attr-subset-value"><?= $attr->value ?></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </li>
    <?php endif; ?>
  <?php endforeach; ?>
</ul>
