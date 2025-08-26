<?php
/**
 * COmanage Registry Generic View Template For Authenticator Manage Actions
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

print $this->element('flash', []);

// Make the Form fields editable
$this->Field->enableFormEditMode();
?>

<div class="page-title-container">
  <div class="page-title">
    <h1><?= $vv_title ?></h1>
  </div>

  <?php
// XXX auto-injecting a reset URL here is too hard at the moment because $topLinks
//     is going to need some major refactoring to support actions based on query params
//     with no entity ID. ie, we need a URL like
//     /authenticators/reset?authenticator_id=x*person_id=y
//     not /authenticators/reset/23
  ?>
</div>

<?php
// Include subnavigation structures on add/edit/view pages
// XXX: if CFM-218 (Make fields.inc configuration only) is accepted, move the contents of fields-nav.inc into fields.inc
// When subnav exists, include on all Edit/View views and on Add views for items with a parent.
if($vv_action == 'manage') {
  // Can we just inject $topLinks here?

  $topLinks[] = [
    'icon' => 'history',
    'order' => 'Default',
    'label' => __d('operation', 'reset'),
    'link' => [
      'controller' => 'authenticators',
      'action' => 'reset',
    ]
  ];

  // XXX maybe other authenticators will want to add custom topLinks?
/*  if(file_exists($templatePath . DS . "fields-nav.inc")) {
    include($templatePath . DS . "fields-nav.inc");
  }  */
}

print $this->Form->create();

if(!$vv_status->locked) {
  // Inject the parent keys

  $hidden = [
    'password_authenticator_id' => $vv_authenticator->password_authenticator->id,
    'person_id' => $vv_status->person_id
  ];
} else {
  $suppress_submit = true;
}

// List of records to collect
// We allow the form to render even if the Authenticator is locked so the Plugin
// can render (read-only) information if it wants to
print $this->element('form/unorderedList');

// Close the Form
print $this->Form->end();
