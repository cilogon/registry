<?php
/**
 * COmanage Registry Subnavigation Include
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

declare(strict_types = 1);

$fullModelsName = !empty($this->getPlugin()) ? $this->getPlugin() . '.' . $modelsName : $modelsName;
$modelsTable = $this->Tab->getModelTableReference($fullModelsName);
if(method_exists($modelsTable, 'getTabsConfig')) {
  $tabsConfiguration = $modelsTable->getTabsConfig($vv_action);
  $subnav = [
    'flashArgs' => $flashArgs ?? [],
    ...$tabsConfiguration
  ];
  if(!empty($topLinks) && $vv_action == 'edit') {
    // Pass along the toplink. These are useful for building the action menu and the plugin sub navigation
    $subnav['topLinks'] = $topLinks;
  }
  if (
    ( // Simple use case
      isset($tabsConfiguration['action'][$fullModelsName])
      && in_array($vv_action, $tabsConfiguration['action'][$fullModelsName], true)
    )
    ||
    ( // Use case with two levels
      isset($tabsConfiguration['nested']['action'][$fullModelsName])
      && in_array($vv_action, $tabsConfiguration['nested']['action'][$fullModelsName], true)
    )
  ) {
    print $this->element('subnavigation/navBar', ['subNavAttributes' => $subnav]);
  }
}