<?php
/**
 * COmanage Registry Mostly Static Resources Fields
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

$fields = [
  'title',
  'name',
  'description',
  'status',
  // Not clear if we should render mime type...
  // 'mime_type',
  'file_content' => [
    'type' => 'file'
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

<script>
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
