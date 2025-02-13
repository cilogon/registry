<?php
/**
 * COmanage Registry Message Templates Fields
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

?>
<script type="text/javascript">
  function setPageUrl() {
    var baseurl = "<?= $vv_base_url ?>";
    var slug = document.getElementById('name').value;

    var fullurl = "";

    if(slug) {
      fullurl = baseurl + "/" + slug;
    }
    
    document.getElementById('pageurl').value = fullurl;
  }

  function jsLocalOnLoad() {
    setPageUrl(true);
  }
</script>

<?php
// This view does not support read-only
if($vv_action == 'add' || $vv_action == 'edit') {
  print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'title'
      ]]);

  print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'name',
        'fieldOptions' => [
          'onChange' => 'jsLocalOnLoad()'
        ]
      ]]);

  print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'pageUrl',
        'fieldOptions' => [
          'readOnly' => true
        ]
      ]]);

  foreach ([
             'description',
             'status',
             'context'
           ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field
      ]]);
  }

  $afterField =
    '<div class="alert alert-info small p-2 d-flex gap-1" role="alert">' .
    '  <em class="material-symbols-outlined" aria-hidden="true">info</em>' .
    '  <span class="alert-text">' . __d('information','html.sanitization') . '</span>' .
    '</div>';
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'body',
      'fieldOptions' => [
        'class' => 'big-textarea'
      ]
    ],
    'afterField' => $afterField
  ]);
}