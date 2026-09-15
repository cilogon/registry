<?php
/**
 * COmanage Registry Basic Person Selector Petition Fields
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

use CoreEnroller\Lib\Enum\PersonSelectorConfirmationModeEnum;

// We only render for Select mode. (For Self mode we don't really need a view,
// except if an error happens we need a landing page to render, which this is.)
if($vv_config->mode != CoreEnroller\Lib\Enum\PersonSelectorModeEnum::Select) {
  return;
}

// Make the Form fields editable
$this->Field->enableFormEditMode();

// For use when rendering information about the candidate Enrollee, create some
// shortcuts to make the code somewhat more readable

// If dispatch.inc is rendered, all currently supported modes allow names to be shown
$showNames = true;

// Identifiers includes Email Addresses
$showIdentifiers = in_array($vv_config->confirmation_page_mode, [
  PersonSelectorConfirmationModeEnum::Attributes,
  PersonSelectorConfirmationModeEnum::Full,
  PersonSelectorConfirmationModeEnum::Identifiers
]);

// Contact info includes Addresses and Telephone Numbers
// This will also include the remaining MVEAs since they are subject to the same
// configuration, but that might change in the future
$showContact = in_array($vv_config->confirmation_page_mode, [
  PersonSelectorConfirmationModeEnum::Attributes,
  PersonSelectorConfirmationModeEnum::Full
]);

$showRoles = in_array($vv_config->confirmation_page_mode, [
  PersonSelectorConfirmationModeEnum::Full,
  PersonSelectorConfirmationModeEnum::Roles
]);

?>

<?php
// If $vv_enrollee_candidate is populated, the user has selected an option from
// the Person Picker and we're presenting the configured attributes for them to confirm
if(!empty($vv_enrollee_candidate)): 
?>

<?= __d('core_enroller', 'result.PersonSelectors.confirm'); ?>

<?php if($showNames): ?>
<h1><?= $vv_enrollee_candidate->primary_name->full_name ?></h1>

<h2><?= __d('controller', 'Names', [99]) ?></h2>

<ul>
<?php foreach($vv_enrollee_candidate->names as $name): ?>
  <li><?= __d('field', 'typepair', [$name->full_name, $name->type->display_name]) ?></li>
<?php endforeach; // names ?>
</ul>
<?php endif; // showNames ?>

<?php if($showIdentifiers): ?>
<h2><?= __d('controller', 'Identifiers', [99]) ?></h2>

<ul>
<?php foreach($vv_enrollee_candidate->identifiers as $identifier): ?>
  <li><?= __d('field', 'typepair', [$identifier->identifier, $identifier->type->display_name]) ?></li>
<?php endforeach; // identifiers ?>
</ul>

<h2><?= __d('controller', 'EmailAddresses', [99]) ?></h2>

<ul>
<?php foreach($vv_enrollee_candidate->email_addresses as $email): ?>
  <li><?= __d('field', 'typepair', [$email->mail, $email->type->display_name]) ?></li>
<?php endforeach; // email_addresses ?>
</ul>
<?php endif; // showIdentifiers ?>

<?php if($showContact): ?>
<h2><?= __d('controller', 'TelephoneNumbers', [99]) ?></h2>

<ul>
<?php foreach($vv_enrollee_candidate->telephone_numbers as $number): ?>
  <li><?= __d('field', 'typepair', [$number->formatted_number, $number->type->display_name]) ?></li>
<?php endforeach; // telephone_numbers ?>
</ul>

<h2><?= __d('controller', 'Addresses', [99]) ?></h2>

<ul>
<?php foreach($vv_enrollee_candidate->addresses as $address): ?>
  <li><?= __d('field', 'typepair', [$address->formatted_address, $address->type->display_name]) ?></li>
<?php endforeach; // addresses ?>
</ul>

<h2><?= __d('controller', 'AdHocAttributes', [99]) ?></h2>

<ul>
<?php foreach($vv_enrollee_candidate->ad_hoc_attributes as $aha): ?>
  <li><?= __d('field', 'listpair', [$aha->tag, $aha->value]) ?></li>
<?php endforeach; // addresses ?>
</ul>

<h2><?= __d('controller', 'Pronouns', [99]) ?></h2>

<ul>
<?php foreach($vv_enrollee_candidate->pronouns as $p): ?>
  <li><?= $p->pronouns ?></li>
<?php endforeach; // addresses ?>
</ul>

<h2><?= __d('controller', 'URLs', [99]) ?></h2>

<ul>
<?php foreach($vv_enrollee_candidate->urls as $url): ?>
  <li><?= __d('field', 'typepair', [$url->url, $url->type->display_name]) ?></li>
<?php endforeach; // urls ?>
</ul>

<?php endif; // showContacts ?>

<?php if($showRoles): ?>
<h2><?= __d('controller', 'PersonRoles', [99]) ?></h2>

<?php foreach($vv_enrollee_candidate->person_roles as $role): ?>
<ul>
  <!-- As a first pass we just include the title and COU, but maybe we want more details? -->
  <li><?= __d('field', 'typepair', [$role->title, $role->cou->name]) ?></li>
</ul>
<?php endforeach; // role ?>
<?php endif; // showRoles ?>

<!-- (Re)insert the enrollee person into the form and also a confirmation flag 
     Note we don't require an explicit check box to confirm, if the Petitioner wants
     to change the Person they should page back (at least until we have a better UX)
-->

<?= $this->Form->hidden('enrollee_person_id', ['value' => $vv_enrollee_candidate->id]); ?>

<?= $this->Form->hidden('confirm', ['value' => 'yes']); ?>

<hr />

<?= __d('core_enroller', 'information.PersonSelectors.review'); ?>

<?php else: // vv_enrollee_candidate ?>

<?= $this->element('CoreEnroller.spa-field', [
  'vueElementName' => 'peopleAutocomplete',
  'formArguments' => [
    'fieldType' => 'string',
    'fieldName' => 'enrollee_person_id',
    // We don't need any of these, except to get the component to render
    'fieldLabel' => '',
    'fieldDescription' => '',
    'fieldNameAlias' => 'enrollee',
    'fieldOptions' => [
      // We don't really need required since we'll just reject an empty value in the controller,
      // and the * (currently) renders floating unattached to anything
      'required' => false
    ]
  ]
]); ?>

<?php endif; // vv_enrollee_candidate ?>

<!-- Offer additional options depending on the configuration -->

<ul>
  <?php if((isset($vv_user_roles['co']) && $vv_user_roles['co'])
           || (isset($vv_user_roles['platform']) && $vv_user_roles['platform'])): ?>
  <li><?= $this->Html->link(
    __d('core_enroller', 'operation.terminate'),
    [
      'action' => 'dispatch',
      $vv_config->id,
      '?' => [
        'op' => 'terminate',
        'petition_id' => $vv_petition->id
      ]
    ]
  ) ?></li>
  <?php endif; // vv_user_roles ?>

  <?php if(!empty($vv_config->roe_enrollment_flow_id)): ?>
  <li><?= $this->Html->link(
    __d('core_enroller', 'operation.transition', [$vv_config->roe_enrollment_flow->name]),
    [
      'action' => 'dispatch',
      $vv_config->id,
      '?' => [
        'op' => 'transition',
        'petition_id' => $vv_petition->id
      ]
    ]
  ) ?></li>
  <?php endif; // roe_enrollment_flow_id ?>
</ul>
