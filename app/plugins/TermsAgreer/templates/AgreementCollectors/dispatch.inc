<?php
/**
 * COmanage Registry T&C Agreement Colectors Petition Fields
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

use \TermsAgreer\Lib\Enum\TAndCEnrollmentModeEnum;

print $this->element('flash', []);

// Make the Form fields editable
$this->Field->enableFormEditMode();
?>

<p><?= __d('terms_agreer', 'information.AgreementCollectors.review'); ?></p>

<?php

// Disable submit (Continue) button until all T&C are agreed to
if($vv_tandc_mode == TAndCEnrollmentModeEnum::ExplicitConsent) {
  // XXX T&C must be opened and scrolled before checkbox is enabled
} else {
  // XXX Checkboxes are all enabled, but must be clicked before Continue button is enabled
}

// Begin the form
print $this->Form->create(null, [
  'id'   => 'agreement-form',
  'type' => 'post'
]);

print "
  <table>
    <tr>
      <th>Terms and Conditions</th>
      <th>I Agree</th>
    </tr>
";

foreach($vv_tandc as $tc) {
  print "
    <tr>
      <td>" . $tc['description'] . "</td>
      <td>" . $this->Form->checkbox("tc".$tc['id']) . "</td>
    </tr>
  ";
}

print "
  </table>
";