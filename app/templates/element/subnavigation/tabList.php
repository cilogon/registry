<?php
/**
 * COmanage Registry Tab Subnav Element
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

$curAction = $this->request->getParam('action');
$curController = $this->request->getParam('controller');
$isNested = false;

extract($vv_sub_nav_attributes, EXTR_PREFIX_ALL, 'vv_subnavigation');

?>

<?php foreach($vv_subnavigation_tabs as $tab): ?>
<?php
  // calculate the id
  $curId = $this->Tab->getCurrentId($tab, $isNested);
  // Check if there are child models. If not continue
  if (str_contains($tab, '.Hierarchy')) {
    $modelName = $this->Tab->retrievePluginName($tab, (int)$curId);
    [$plugin, ] = explode('.', $modelName);
    if($this->Tab->getHasManyAssociationModels($modelName)->current() === null) {
      continue;
    }
    // Check if a view is supported
    $className = $this->Tab->getHasManyAssociationModels($modelName)->current();
    if (!$this->Tab->modelSupportsView($className)) {
      continue;
    }
  }
?>
<!-- if a tab has no fields do not render skip -->
  <li class="nav-item">
    <?php
    // Calculate Tab Title
    $title = $this->element('subnavigation/tabTitle', compact('tab', 'curId', 'isNested'));
    // Construct Target URL
    $url = $this->Tab->constructLinkUrl($tab, $curId, $isNested);
    // Calculate Tab Style Class(es)
    $linkClass = $this->Tab->getLinkClass($tab, $isNested);
    // Import <a> element in the DOM
    print $this->Html->link($title, $url, ['class' => $linkClass, 'escape' => false]);
    ?>
  </li>
<?php endforeach; ?>

