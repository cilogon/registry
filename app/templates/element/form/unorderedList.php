<?php
/**
 * COmanage Registry Unordered List Element
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

// View vars
// $vv_obj
// $vv_required_fields
// $vv_action
// $vv_fields_inc
// $vv_template_path
// $vv_field_types

?>
<ul id="<?= $vv_action . '_' . $this->name ?>" class="fields form-list">
  <?php
  // We allow the fields.inc file to be specified for Controllers that have more
  // complicated/non-default actions.
  // XXX Each fields.inc file will calculate and provide the hidden controls.
  $fieldsFile = $vv_fields_inc ?? 'fields.inc';
  // The controller will calculate the template path for us, since it could be
  // in one of several paths if we are in a plugin context.
  // The include files will contain the listItem elements
  include($vv_template_path . DS . $fieldsFile);
  // Element ID
  print $this->element('form/entityID');
  // The Submit element will be printed only if we are adding or updating
  print $this->element('form/submit', ['label' => __d('operation', 'save')]);
  ?>
</ul>

<?php
// Import all the hidden fields in the Form
if(!empty($hidden)) {
// Inject any hidden variables set by the included file
  foreach($hidden as $attr => $v) {
    print $this->Form->hidden($attr, ['value' => $v]);
  }
}