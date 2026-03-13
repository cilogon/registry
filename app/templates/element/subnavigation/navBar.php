<?php
/**
* COmanage Registry Subnavigation Tabs Element
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
*
* 
*/

// Return if no configurations found
if(empty($subNavAttributes)) {
  return;
}

// We wil not include the subnavigation header if the query paremater co_id exists. This means that
// we want to render everything and not in the context of an association
if($this->request->getQuery('co_id') !== null) {
  return;
}

$this->set('vv_sub_nav_attributes', $subNavAttributes);
extract($subNavAttributes, EXTR_PREFIX_ALL, 'vv_subnavigation');

/** var string $modelsName */
$modelsName = $this->getName();
$fullModelsName = !empty($this->getPlugin()) ? $this->getPlugin() . '.' . $modelsName : $modelsName;
?>

<div id="subnavigation">
  <div class="supertitle-container">
    <div class="supertitle">
      <?= $this->element('subnavigation/supertitle') ?>
      <?= $this->element('subnavigation/statusBadge') ?>
    </div>
    <?= $this->element('subnavigation/upperButtons') ?>
  </div>
  
  <!-- Top-Level Subnavigation Tabs -->  
  <nav id="cm-<?= $fullModelsName ?>-subnav-tabs" class="cm-subnav-tabs">
    <ul class="nav nav-tabs">
      <?= $this->element('subnavigation/tabList')?>
    </ul>
  </nav>

  <?php if (
    // Do we have a nested element configured?
    !empty($subNavAttributes['nested'])
    // Check the nested elements if they allow navigation for this action
    && in_array($vv_action, $subNavAttributes['nested']['action'][$fullModelsName] ?? [], true)
  ): ?>
  <nav id="cm-<?= $fullModelsName ?>-subnav-links" class="cm-subnav-links">
    <ul class="list-inline">
      <?= $this->element('subnavigation/inlineList')?>
    </ul>
  </nav>
  <?php endif; ?>
</div>
