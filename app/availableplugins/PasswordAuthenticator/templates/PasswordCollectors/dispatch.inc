<?php
/**
 * COmanage Registry Password Collector Petition Fields
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

// We need this to get a continue button
$this->Field->enableFormEditMode();

// We actually don't want to use the same fields.inc file as the PasswordAuthenticator view
// because it has a bunch of logic in it that only applies to run-time authenticator maanagement.

$fields = [
  'password' => [
    'class' => 'focusFirst'
  ],
  'password2' => [
    'type' => 'password',
    'fieldLabel' => __d('password_authenticator', 'field.Passwords.password2')
  ]
];

// dispatch gets its own submit, so we don't need the form generator to add one
$suppress_submit = true;

print $this->element('form/unorderedList', ['vv_fields' => $fields]);

if($vv_config->required == \App\Lib\Enum\RequiredEnum::Optional) {
  // if our configuration is Optional, render a "Skip" button that POSTs back to the form
  // XXX If adding extra submit buttons becomes a more common need, we should create them from config.
  // The .submit-extra class will move the button next to the regular submit ("continue") button.
  print '<div class="submit-extra">';
  print $this->Form->button(__d('operation', 'skip'), [
    'type' => 'submit',
    'name' => 'skip',
    'value' => 'skip',  
    'class' => 'spin submit-button btn btn-default',
  ]);
  print '</div>';
}
