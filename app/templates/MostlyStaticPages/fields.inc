<?php
/**
 * COmanage Registry Mostly Static Pages Fields
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

$fields = [
  'title',
  'name',
  'pageUrl' => [
    'readonly' => true
  ],
  'description',
  'status',
  'context',
  'body' => [
    'class' => 'big-textarea',
    'afterField' => 
      '<div class="alert alert-info small p-2 d-flex gap-1" role="alert">' .
      '  <em class="material-symbols-outlined" aria-hidden="true">info</em>' .
      '  <span class="alert-text">' . __d('information','html.sanitization') . '</span>' .
      '</div>'
  ]
];

// Top Links
if($vv_action == 'edit') {
  $topLinks[] = [
    'icon'  => 'arrow_outward',
    'order' => 'Default',
    'label' => __d('operation', 'view'),
    'url'  => $vv_base_url . $vv_obj->name,
    'class' => ''
  ];
}
?>

<script nonce="<?= $vv_js_nonce ?>">
  function setPageUrl() {
    var baseurl = "<?= $vv_base_url ?>";
    var slug = document.getElementById('name').value;

    var fullurl = "";

    if(slug) {
      fullurl = baseurl + slug;
    }

    document.getElementById('pageurl').value = fullurl;
  }

  $(function() {
    // run on first load
    setPageUrl();

    // register onchange events
    // note that underscores in field names above must be represented with hyphens here.
    $('#name').change(function() {
      setPageUrl();
    });
  });
</script>
