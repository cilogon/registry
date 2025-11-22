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
// $vv_fields  
// $vv_obj
// $vv_required_fields
// $vv_action
// $vv_fields_inc
// $vv_template_path
// $vv_field_types
// $vv_submit_button_label

?>
<ul id="<?= $vv_action . '_' . $this->name ?>" class="<?= $vv_action . '_' . $this->name ?> fields form-list">
  <?php
  if(!empty($vv_fields)) {
    // Output the visible fields from the fields.inc configuration
    foreach($vv_fields as $key => $field) {
      if($key === 'SUBTITLE' || $key === 'HTML') {
        if($key === 'SUBTITLE') {
          // We have a subtitle 
          $content = $field['subtitle'];
          $type = 'subtitle';
        } else {
          // We have HTML to insert.
          $content = $field['html'];
          $type = 'html';  
        }
        print $this->element('form/htmlInject', compact('content', 'type'));
      } else {
        // We have a normal field. Parse the configuration for strings or associative arrays.
        if (is_int($key)) {
          // We have numeric keys, therefore the value is the field name (a string).
          $fieldArgs = ['fieldName' => $field];
        } else {
          // Otherwise, the key is the field name. Pass along the other arguments (in $field).
          $fieldArgs = ['fieldName' => $key] + $field;
        }
        print $this->element('form/listItem', ['arguments' => $fieldArgs]);
      }
    }
  }
  
  if(!isset($suppress_submit) || !$suppress_submit) {
    // The Submit element will be printed only if we are adding or updating, and if not
    // suppressed by the field configuration
    $vv_submit_button_label = $vv_submit_button_label ?? __d('operation', 'save');
    print $this->element('form/submit', ['label' => $vv_submit_button_label]);
  }
  ?>
</ul>
