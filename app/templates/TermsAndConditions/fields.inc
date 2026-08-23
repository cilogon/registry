<?php
/**
 * COmanage Registry Terms and Conditions Fields
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

$fields = [
  'description',
  'status',
  'mostly_static_page_id' => [
    'afterField' =>
      $this->Html->link(
        __d('operation','manage.a',[__d('controller','MostlyStaticPages', 99)]),
        [
          'controller' => 'MostlyStaticPages', 
          'action' => 'index',
          '?' => [
            'co_id' => $vv_cur_co->id
          ]
        ],
        ['class' => 'manage-link']
      )
  ],
  'url',
  'cou_id',
  'agreement_duration',
  'agree_to_updates',
  'ordr'
];

?>
<script nonce="<?= $vv_js_nonce ?>">
  // If both an MSP and URL are specified, the documentation says the URL
  // takes precedence, but it's less confusing just to blank the other one
  // out if an admin edits these fields.

  function clearMsp() {
    document.getElementById('mostly-static-page-id').value = "";
  }

  function clearUrl() {
    document.getElementById('url').value = "";
  }

  $(function() {
    // register onchange events
    // note that underscores in field names above must be represented with hyphens here.
    $('#mostly-static-page-id').change(function() {
      clearUrl();
    });

    $('#url').change(function() {
      clearMsp();
    });
  });
</script>
