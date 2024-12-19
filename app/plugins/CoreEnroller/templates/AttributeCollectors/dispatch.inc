<?php
/**
 * COmanage Registry Dispatch Fields
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

use App\Lib\Enum\StatusEnum;

// This view is intended to work with dispatch
if($vv_action !== 'dispatch') {
  return;
}

// Make the Form fields editable
$this->Field->enableFormEditMode();
// Populate the AutoViewVars. These are the same we do for the EnrollmentAttributes configuration view
$this->Petition->populateAutoViewVars();
// We just populated the AutoViewVars. Add them to the current context
extract($this->viewVars);

// Sort according to configuration
$vv_enrollment_attributes_sorted = $vv_enrollment_attributes->sortBy(function ($attribute) {
  return $attribute->ordr;
}, SORT_ASC)->toList();

// Iterate over the attributes and render
foreach($vv_enrollment_attributes_sorted as $attr) {
  // Do not render if this is not active
  if ($attr->status !== StatusEnum::Active) {
    continue;
  }

  // Fieldset with legend for MVEAs
  if(\in_array($attr->attribute, ['name', 'address', 'telephoneNumber'], true)) {
    print $this->element('CoreEnroller.mveas/mvea-fieldset', compact('attr'));
    continue;
  }

  // Default
  print $this->element('CoreEnroller.field', compact('attr'));
}