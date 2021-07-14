<?php
/**
 * COmanage Registry Cos Select View
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

// XXX See registry/app/View/Pages/home.ctp for various error messages we should
//     render based on the user's state, include op.home.no.collabs if empty
?>

<div id="fpDashboard">
  <!-- XXX add lastlogin from registry/app/View/Pages/home.ctp -->
  
  <h2><?= __('registry.home.collab'); ?></h2>
<!-- XXX color of div has switched from blue to gray, do we care? -->
  <div id="fpCoList" class="co-grid co-grid-with-header mdl-shadow--2dp">
    <div class="mdl-grid co-grid-header">
      <div class="mdl-cell mdl-cell--6-col"><?= __('registry.fd.name'); ?></div>
      <div class="mdl-cell mdl-cell--6-col"><?= __('registry.fd.description'); ?></div>
    </div>
    
    <?php foreach($vv_available_cos as $co): ?>
    <div class="mdl-grid co-row spin">
      <div class="mdl-cell mdl-cell--6-col collab-name">
        <?= $this->Html->link(
// XXX do we need filter_var?
              $co->name,
              ['plugin'     => null,
               'controller' => 'dashboards',
               'action'     => 'dashboard',
               '?'          => [
                 'co_id'      => $co->id
               ]],
              ['class' => 'co-link']
            ); ?>
      </div>
      <div class="mdl-cell mdl-cell--6-col collab-desc">
<!-- XXX need to add "Not a Member" tag, maybe as a separate column instead of part of the link -->
        <?= filter_var($co->description, FILTER_SANITIZE_SPECIAL_CHARS); ?>
      </div>
    </div>
    <?php endforeach; // vv_available_cos ?>
  </div>
</div>
<!-- Allow the whole div to be clicked: -->
<script type="text/javascript">
  $(function() {
    $("#fpCoList .co-row").click(function () {
      location.href = $(this).find(".collab-name > a.co-link").attr('href');
    });
  });
</script>
