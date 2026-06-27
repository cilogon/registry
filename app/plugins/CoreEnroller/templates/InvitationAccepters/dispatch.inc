<?php
/**
 * COmanage Registry Invitation Accepter Petition Fields
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

// This view is intended to work with dispatch
if($vv_action == 'dispatch') {
  if(!empty($vv_config->welcome_message)) {
    print '<li class="dispatch-welcome">' . $vv_config->welcome_message . "</li>\n";
  }

  // Make the Form fields editable
  $this->Field->enableFormEditMode();

  print $this->element('CoreEnroller.listItem', [
    'arguments' => [
      'fieldName' => 'accepted',
      'fieldLabel' => __d('operation','accept.invitation'),
      'fieldOptions' => [
        'type' => 'radio',
        'options' => [
          ['value' => '1', 'text' => __d('operation','accept')],
          ['value' => '0', 'text' => __d('operation','decline')],
        ],
        'empty' => false,
        'required' => true,
        'class' => 'form-check-input'
      ]
  ]]);
?>
  <script nonce="<?= $vv_js_nonce ?>">
    $('#accepted-1').click(function() {
      $(this).closest('label').addClass('is-selected');
      $("label[for='accepted-0']").removeClass('is-selected');
    });
    $('#accepted-0').click(function() {
      $(this).closest('label').addClass('is-selected');
      $("label[for='accepted-1']").removeClass('is-selected');
    });
  </script>
<?php  
}