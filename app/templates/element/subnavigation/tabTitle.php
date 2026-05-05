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

use App\Lib\Util\StringUtilities;
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

// Simple use case
$tabLanguageKey = in_array('index', $navigation_action[$tab], true) ? $tab : 'Properties';
$title = !empty($tabLabel) ? $tabLabel : __d('controller', $tabLanguageKey, [99]);
$request = $this->getRequest();
$requesterModel = StringUtilities::getQualifiedName(
  $request->getParam('plugin'),
  $request->getParam('controller')
);

// Some tabs (eg GroupMembers from ExternalIdentityRoles context) are not directly
// associated with the current requester model. Title rendering must not fatal.
try {
$tabToTableName = StringUtilities::modelNameToQualifiedModelName($tab, $requesterModel);
} catch (\Throwable $e) {
  $tabToTableName = $tab;
}

// Plugin Configuration Tab
if (
  str_contains($tab, '.')
  && in_array('edit', $navigation_action[$tab], true)
  && array_search($tab, $vv_subnavigation_tabs, true) !== 0
) {
  $title = __d('operation', 'configure.plugin');
} else if (str_contains($tab, '@action.')) { // Top Links/Actions
  [$modelName, ] = explode('@', $tab);
  [, $action] = explode('.', $tab);
  $title = __d('operation', $modelName . '.' . $action);

  try {
  $tabToTableName = StringUtilities::modelNameToQualifiedModelName($modelName, $requesterModel);
  } catch (\Throwable $e) {
    $tabToTableName = $modelName;
  }
} else if (str_ends_with($tab, '.Hierarchy')) { // Deep Associations
  $fullModelName = $this->Tab->getAssociation();
  [$plugin, $modelName] = explode('.', $fullModelName);
  $poFile = Inflector::underscore($plugin);
  $title = __d($poFile, 'controller.' . $modelName, [99]);

  try {
  $tabToTableName = StringUtilities::modelNameToQualifiedModelName($fullModelName, $requesterModel);
  } catch (\Throwable $e) {
    $tabToTableName = $fullModelName;
  }
} else if (str_contains($tabLanguageKey, '.')) { // Simple Plugin Plugin.Model
  [$plugin, $modelName] = explode('.', $tabLanguageKey);
  $poFile = Inflector::underscore($plugin);
  $title = __d($poFile, 'controller.' . $modelName, [99]);

  try {
  $tabToTableName = StringUtilities::modelNameToQualifiedModelName($tabLanguageKey, $requesterModel);
  } catch (\Throwable $e) {
    $tabToTableName = $tabLanguageKey;
  }
}

// Insert Counter Badge if applicable
if (isset($tab_counter)
  && in_array($tab, $tab_counter, true)
) {
  $url = $this->Tab->constructLinkUrl($tab, $curId, $isNested);
  $model = $url['controller'];
  if (!empty($url['plugin'])) {
      $model = $url['plugin'] . '.' . $model;
  }
  if (isset($url['?'])) {
    $where = $url['?'];
  } else {
    $passed = array_values(array_filter(
      $url,
      static fn($k) => is_int($k),
      ARRAY_FILTER_USE_KEY
    ));
    $where = ['id' => (int)$passed];
  }
  $num = $this->Tab->getModelTotalCount($model, $where);
}

?>

<?php if (isset($num)): ?>
<span class='tab-count'>
  <span class='tab-count-item'><?= $num ?></span>
</span>
<?php endif; ?>
<span class='tab-title'><?= $title ?></span>