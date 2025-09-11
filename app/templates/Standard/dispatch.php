<?php
/**
 * COmanage Registry Generic View Template For Enrollment Flow Actions
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

/** var string $modelsName */
use Cake\Utility\Inflector;

$modelsName = $this->getName();
// $tablename = models
$tableName = Inflector::tableize(Inflector::singularize($this->name));
// Populate the AutoViewVars. These are the same we do for the EnrollmentAttributes configuration view
$this->Petition->populateAutoViewVars();
// We just populated the AutoViewVars. Add them to the current context
extract($this->viewVars);

// $vv_template_path will be set for plugins
$templatePath = $vv_template_path ?? ROOT . DS . 'templates' . DS . $modelsName;

$action_args['vv_actions'][] = [
  'order' => $this->Menu->getMenuOrder('Default'),
  'icon' =>  'list',
  'url' => [
    'plugin' => null,
    'controller' => 'petitions',
    'action' => 'resume', 
    $vv_petition->id
  ],
  'label' => __d('controller', 'EnrollmentFlowSteps', 99)
];
?>
  
<div class="page-title-container">
  <div class="page-title">
    <h1 class="flow-name"><?= $vv_title ?></h1>
    <h2 class="flow-step-description"><?= $vv_step_config['enrollment_flow_step']['description'] ?></h2>
  </div>
</div>

<?= $this->element('flash') // Flash messages ?>
  
<?php
// Set the Include file name
// Will be used by the unorderedList element below
$this->set('vv_fields_inc', 'dispatch.inc');
if (empty($vv_submit_button_label)) {
  $this->set('vv_submit_button_label', __d('operation', 'continue'));
}

// By default, the form will POST to the current controller
// Note we need to open the form for view so Cake will autopopulate values
$idPrefix = Inflector::dasherize($modelsName);
print $this->Form->create(null, [
  'id' => "$idPrefix-dispatch-form",
  'type' => 'post',
]);

// Form body
print '<div id="dispatch-list-container">';
print $this->element('form/unorderedList');
print '</div>';

// Inject the Petition ID into the form, though it will most likely
// still be available in the URL.
print $this->Form->hidden('petition_id', ['value' => $vv_petition->id]);
// Inject the token, if indicated.
if(isset($vv_token_ok) && $vv_token_ok && !empty($vv_petition->token)) {
  print $this->Form->hidden('token', ['value' => $vv_petition->token]);
}

print $this->Form->end();
