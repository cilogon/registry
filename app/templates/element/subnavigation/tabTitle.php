<?php
/**
 * COmanage Registry Tab Title With count Element
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

/*
 * Parameters:
 * $tab                 : string, required
 * $curId               : int, required
 * $isNested            : boolean, required
 */

declare(strict_types = 1);

use Cake\Utility\Inflector;

extract($vv_sub_nav_attributes, EXTR_PREFIX_ALL, 'vv_subnavigation');
$navigation_action = $vv_subnavigation_action;
$tab_counter = $vv_subnavigation_counter ?? null;

if (isset($isNested) && $isNested) {
  $navigation_action = $vv_subnavigation_nested['action'];
  $tab_counter = $vv_subnavigation_nested['counter'] ?? null;
}

// We calculate this first because we want to initialize the Helper variables
$tabAction = $this->Tab->getTabAction($tab, $isNested);
$linkFilter = $this->Tab->getLinkFilter($tab, $curId, $tabAction, $isNested);

// Simple use case
$tabLanguageKey = in_array('index', $navigation_action[$tab], true) ? $tab : 'Properties';
$title = !empty($tabLabel) ? $tabLabel : __d('controller', $tabLanguageKey, [99]);
$tabToTableName = Inflector::tableize(Inflector::singularize($tab));

// Plugin Configuration Tab
if (str_contains($tab, '.') && in_array('edit', $navigation_action[$tab], true)) {
  $title = __d('operation','configure.plugin');
} else if (str_contains($tab, '@action.')) { // Top Links/Actions
  [$modelName, ] = explode('@', $tab);
  [, $action] = explode('.', $tab);
  $title = __d('operation', $modelName . '.' . $action);
  $tabToTableName = Inflector::tableize(Inflector::singularize($modelName));
} else if (str_ends_with($tab, '.Hierarchy')) { // Deep Associations
  $fullModelName = $this->Tab->getAssociation();
  [$plugin, $modelName] = explode('.', $fullModelName);
  $poFile = Inflector::underscore($plugin);
  $title = __d($poFile, 'controller.' . $modelName, [99]);
  $tabToTableName = Inflector::tableize(Inflector::singularize($fullModelName));
} else if(str_contains($tabLanguageKey, '.')) { // Simple Plugin Plugin.Model
  [$plugin, $modelName] = explode('.', $tabLanguageKey);
  $poFile = Inflector::underscore($plugin);
  $title = __d($poFile, 'controller.' . $modelName, [99]);
  $tabToTableName = Inflector::tableize(Inflector::singularize($tabLanguageKey));
}

// Insert Counter Badge if applicable
if(isset($tab_counter)
  && in_array($tab, $tab_counter, true)
) {
  $model = $tabToTableName;
  $where = $linkFilter;
}

if(!isset($num)
  && !empty($model)
  && !empty($where)) {
  $num = $this->Tab->getModelTotalCount($model, $where);
}

?>

<?php if(isset($num)): ?>
<span class='tab-count'>
  <span class='tab-count-item'><?= $num ?></span>
</span>
<?php endif; ?>
<span class='tab-title'><?= $title ?></span>