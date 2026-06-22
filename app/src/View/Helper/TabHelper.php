<?php
/**
 * COmanage Registry Tab Helper
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

namespace App\View\Helper;

use App\Lib\Util\StringUtilities;
use App\Lib\Util\TableUtilities;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\View\Helper;

class TabHelper extends Helper
{
  /**
   * @var string|null
   */
  private ?string $pluginName;

  /**
   * @var string|null
   */
  private ?string $association;

  /**
   * Only one group of actions is allowed under the subnavigation. For example:
   * - view/edit but not index
   * - index but not view/edit
   *
   * @param   string      $tab
   * @param   string|int  $curId
   * @param   bool        $isNested
   *
   * @return array
   * @since  COmanage Registry v5.0.0
   */
  public function constructLinkUrl(string $tab, string|int $curId, bool $isNested = false): array
  {
    $curController = $this->getView()->getRequest()->getParam('controller');
    $vv_associated_ids = $this->getView()->get('vv_associated_ids');

    $modelName = $tab;
    $controller = $modelName;
    $plugin = null;

    // Action calculation
    $action = $this->getTabAction($tab, $isNested);
    // id or query parameter calculation
    $linkFilter = $this->getLinkFilter($tab, $curId, $action, $isNested);

    // Controller + Plugin calculation
    if(str_ends_with($tab, '.Plugin')) {
      // This is always the second tab of the plugin and it is configuration
      $controller = $curController;
      $plugin = $this->getView()->getPlugin();
      $plugin = !empty($plugin) ? $plugin : null;
      $action = 'configure';
    } else if (str_ends_with($tab, '.Hierarchy')) {
      $modelName = $this->retrievePluginName($tab, (int)$curId);
      [$plugin, ] = explode('.', $modelName);
      foreach ($this->getHasManyAssociationModels($modelName) as $association) {
        [$plugin, $controller] = explode('.', $association);
        break;
      }
    } else if (str_contains($tab, '@action')) {
      // We have a plugin path
      [$controller,] = explode('@', $modelName);
      [, $action] = explode('.', $modelName);
    } else if (str_contains($tab, '.')) {
      // We have a plugin path
      [$plugin, $controller] = explode('.', $modelName);
    }

    $url = [
      'plugin' => $plugin,
      'controller' => $controller,
      'action' => $action
    ];

    if ($action === 'index') {
      // The FK in $linkFilter belongs to the *tab target model* (plugin/controller),
      // not necessarily the current requester controller.
      $linkFilterModel = StringUtilities::qualifyModelPath($controller, $plugin);

      $deepId = $this->getDeepNestedId($linkFilter, $linkFilterModel);
      If($deepId !== null) {
        $linkFilterForeignKey = array_key_first($linkFilter);
        $linkFilter[$linkFilterForeignKey] = $deepId;
      }
      $url['?'] = $linkFilter;
    } else if ($action === 'edit') {
      $vv_permission_set = $this->getView()->get('vv_permission_set');
      $vv_permission_view = $this->getView()->get('vv_permission_view');
      if ($vv_permission_set && is_array($vv_permission_set)) {
        $permission_set = array_pop($vv_permission_set);
        $url['action'] = $permission_set['edit'] ? 'edit' : 'view';
      } elseif (!empty($vv_permission_view)) {
        $url['action'] = $vv_permission_view['edit'] ? 'edit' : 'view';
      }

      // I will get the id from the associated ids table
      $modelPath = StringUtilities::qualifyModelPath($controller, $plugin);
      $url[] = $vv_associated_ids[$modelPath];
    } else {
      $url[] = $curId;
    }

    return $url;
  }


  /**
   * Retrieve the ID for a deeply nested association.
   *
   * @param array       $linkFilter        The link filter containing foreign key details.
   * @param string|null $linkFilterModel   Model that owns the FK in $linkFilter (eg "CoreEnroller.EnrollmentAttributes").
   *
   * @return int|null The ID of the deeply nested associated model or null if not found.
   * @since COmanage Registry v5.1.0
   */
  public function getDeepNestedId(array $linkFilter, ?string $linkFilterModel = null): ?int
  {
    // Retrieve associated IDs for the current view
    $vv_associated_ids = $this->getView()->get('vv_associated_ids');

    // Extract the first key from the link filter array (foreign key)
    $linkFilterForeignKey = array_key_first($linkFilter);

    $request = $this->getView()->getRequest();
    $requesterModel = StringUtilities::getQualifiedName($request->getParam('plugin'), $request->getParam('controller'));

    // Use the FK owner model (tab target) for strict FK resolution when provided.
    $fkOwnerModel = $linkFilterModel ?: $requesterModel;

    // resolve the FK to a canonical Plugin.Model registry alias using belongsTo metadata
    $qualifiedModelName = StringUtilities::foreignKeyToQualifiedModelName($linkFilterForeignKey, $fkOwnerModel);
    $table = TableRegistry::getTableLocator()->get($qualifiedModelName);

    // Attempt to retrieve the ID from associated IDs using different possible keys
    $modelName = StringUtilities::foreignKeyToClassName($linkFilterForeignKey);
    $linkFilterId =
        $vv_associated_ids[$qualifiedModelName]
      ?? $vv_associated_ids[$modelName]
      ?? current(array_filter($vv_associated_ids, fn($k) => str_ends_with($k, '.' . $modelName), ARRAY_FILTER_USE_KEY))
      ?: null;

    // If an ID is found, return it as an integer
    if ($linkFilterId !== null) {
      return (int)$linkFilterId;
    }

    // Initialize variables to track foreign key and its ID
    $foreignKeyId = null;
    $foreignKey = null;

    // Iterate through all schema columns to look for a valid foreign key
    foreach ($table->getSchema()->columns() as $column) {
      // Skip columns that do not end with '_id'
      if (!str_ends_with($column, '_id')) {
        continue;
      }

      // resolve nested FK relative to the table we are currently traversing
      // (qualifiedModelName is the requester in this nested context)
      $fkQualifiedModelName = StringUtilities::foreignKeyToQualifiedModelName($column, $qualifiedModelName);

      $fkModelName = StringUtilities::foreignKeyToClassName($column);

      // Check for a match in associated IDs
      $candidateId =
        $vv_associated_ids[$fkQualifiedModelName]
        ?? $vv_associated_ids[$fkModelName]
        ?? null;

      // If a match is found, set the foreign key ID and column, and break the loop
      if ($candidateId !== null) {
        $foreignKeyId = (int)$candidateId;
        $foreignKey = $column;
        break;
      }
    }

    // If no valid foreign key or ID was found, return null
    if ($foreignKey === null || $foreignKeyId === null) {
      return null;
    }

    // Query the database for the associated row using the foreign key
    $row = $table->find()->where([$foreignKey => $foreignKeyId])->first();

    // Return the ID from the row if found or null otherwise
    return $row ? (int)$row->id : null;
  }

  /**
   * Calculate the link Class
   *
   * @param   string  $tab
   * @param   bool    $isNested
   *
   * @return string
   * @since  COmanage Registry v5.0.0
   */
  public function getLinkClass(string $tab, bool $isNested = false, array $nestings = []): string
  {
    $vv_sub_nav_attributes = $this->getView()->get('vv_sub_nav_attributes');

    $curController = $this->getView()->getRequest()->getParam('controller');
    $curAction = $this->getView()->getRequest()->getParam('action');
    $plugin = $this->getView()->getPlugin();
    $fullModelName = $curController;
    if(isset($plugin)) {
      $fullModelName = "{$plugin}.{$curController}";
    }

    // The list of Nested models is in order. Which means that the first tab is always the parent and the one
    // that will be set as active
    $parentModelForNested = $vv_sub_nav_attributes['nested']['tabs'][0] ?? 0;

    // Calculate Tab Style Class(es)
    return match(true) {
      // Matches the tab to the current controller. It addresses the simple subnavigation
      // The fullModelName can either be a simple Model or a Plugin with the path.
      $tab === $fullModelName && \in_array($curAction, ['index', 'edit', 'view']),
      // Always mark active the parent Tab
      !$isNested && $parentModelForNested !== null && $tab === $parentModelForNested && in_array($fullModelName, $nestings),
      // Match Configuration and Hierarchy tabs
      isset($plugin) && str_contains($tab, '.Plugin') && $curAction === 'configure',
      isset($plugin) && str_contains($tab, '.Hierarchy') && $curAction === 'index',
      // Matches the action tab links, e.g. FileSource/search
      $tab === "{$curController}@action.{$curAction}" => 'nav-link active',
      default => 'nav-link'
    };
  }

  /**
   * Check the belongsTo tree hierarchy
   *
   * @param   string  $tab
   * @param   string  $modelFullName
   * @param   int     $depth
   *
   * @return bool
   * @since  COmanage Registry v5.0.0
   */
  public function tabBelongsToModelPath(string $tab, string $modelFullName, int &$depth = 0): bool
  {
    $model = TableRegistry::getTableLocator()->get($modelFullName);
    // We'll start by getting the set of models directly associated with the CO model.
    $associations = $model->associations();

    $depth++;
    foreach($associations->getByType(['belongsTo', 'belongsToMany']) as $ta) {
      if($ta->getClassName() === $tab) {
        return true;
      }
      return $this->tabBelongsToModelPath($tab, $ta->getClassName(), $depth);
    }

    return false;
  }

  /**
   * Calculate the ID for the tab link
   *
   * @param   string|null  $tabName
   * @param   bool         $isNested
   *
   * @return int
   * @since  COmanage Registry v5.0.0
   */
  public function getCurrentId(?string $tabName = null, bool $isNested = false): int
  {
    $vv_obj = $this->getView()->get('vv_obj');
    $vv_primary_link = $this->getView()->get('vv_primary_link');
    $vv_bc_title_links = $this->getView()->get('vv_bc_title_links');
    $request = $this->getView()->getRequest();
    $curController = StringUtilities::getQualifiedName($request->getParam('plugin'), $request->getParam('controller'));
    $vv_sub_nav_attributes = $this->getView()->get('vv_sub_nav_attributes');
    $tab_actions = !$isNested ? $vv_sub_nav_attributes['action'] :  $vv_sub_nav_attributes['nested']['action'];
    $tabs = !$isNested ? $vv_sub_nav_attributes['tabs'] :  $vv_sub_nav_attributes['nested']['tabs'];

    $tid = $request->getQuery($vv_primary_link)
      ?? $vv_obj->id
      ?? end($vv_bc_title_links[0]['target']);

    // Get the ids of all the associated Model records
    $results = [];
    if ($request->getQuery($vv_primary_link) !== null) {
      TableUtilities::treeTraversalFromPrimaryLink($vv_primary_link, (int)$tid, $results, null, $curController);
    } else {
      TableUtilities::treeTraversalFromId($curController, (int)$tid, $results);
    }

    $this->getView()->set('vv_associated_ids', $results);
    $tabAction = $this->getTabAction($tabName, $isNested);

    if(
      !$isNested
      && ($tabAction === 'index' || $curController !== $tabName)
      && \in_array($tabName, $tabs, true)
    ) {
      return (int)$results[ $tabs[0] ];
    } else if (
      !$isNested
      && ($tabAction !== 'index' || $curController === $tabName)
      && \in_array($tabName, $tabs, true)
    ) {
      return (int)$tid;
    } else if (
      $isNested
      && $curController !== $tabName
      && !str_contains($tabName, '.')
      && \in_array($tabAction, ['view', 'edit'], true)
      && \in_array($tabAction, $tab_actions[$tabName], true)
    ) {
      return (int)$results[ $tabName ];
    }

    return (int)$tid;
  }

  /**
   * Check the belongsTo tree hierarchy
   *
   * @param   string  $modelName
   *
   * @return \Generator
   * @since  COmanage Registry v5.0.0
   */
  public function getHasManyAssociationModels(string $modelName): \Generator
  {
    $model = TableRegistry::getTableLocator()->get($modelName);
    // We'll start by getting the set of models directly associated with the CO model.
    $associations = $model->associations()->getByType(['hasMany', 'hasOne']);

    if(empty($associations)) {
      // Yield null if empty
      yield;
    }

    foreach($associations as $ta) {
      $this->setAssociation($ta->getClassName());
      yield $ta->getClassName();
    }
  }


  /**
   * Check if a specified model supports the 'view' functionality.
   *
   * This method determines whether a given model is capable of handling
   * auto view variable population by checking for the existence of the
   * `getAutoViewVars` method and its return value.
   *
   * @param string $modelName The name of the model to check.
   *
   * @return bool Returns true if the model supports 'view', otherwise false.
   * @since  COmanage Registry v5.1.0
   * @note  Usually a table will expose AutoViewVars. This is not guaranteed though.
   *        Revisit if we come across a use case that required more fine-grained check.
   */
  public function modelSupportsView(string $modelName): bool
  {
    $table = TableRegistry::getTableLocator()->get($modelName);
    return method_exists($table, 'getAutoViewVars') && $table->getAutoViewVars();
  }

  /**
   * Construct the link filter
   *
   * @param   string      $tab
   * @param   int|string  $curId
   * @param   string      $tabAction
   * @param   bool        $isNested
   *
   * @return int[]|string[]
   * @since  COmanage Registry v5.0.0
   */
  public function getLinkFilter(
    string $tab,
    int|string $curId,
    string $tabAction,
    bool $isNested = false
  ): array {
    $vv_sub_nav_attributes    = $this->getView()->get('vv_sub_nav_attributes');
    $subnav_tabs    = $vv_sub_nav_attributes['tabs'];
    $subnav_allowed_actions    = $vv_sub_nav_attributes['action'];
    if ($isNested) {
      $subnav_tabs    = $vv_sub_nav_attributes['nested']['tabs'];
      $subnav_allowed_actions    = $vv_sub_nav_attributes['nested']['action'];
    }
    $fullModelsName = $tab;
    $modelName      = $tab;
    $curController  = $this->getView()->getRequest()->getParam('controller');

    $request = $this->getView()->getRequest();
    $requesterModel = StringUtilities::getQualifiedName($request->getParam('plugin'), $request->getParam('controller'));

    // We have two use cases. The first one is for the Core models and the second one is for the
    // plugins. In case we have a plugin we need to retrieve the name from the database
    if(str_contains($tab, '.Plugin')) {
      $modelName = $this->retrievePluginName($tab, (int)$curId, $requesterModel);
      $this->setPluginName($modelName);
      $fullModelsName = $modelName;
    } else if (str_contains($tab, '.Hierarchy')) {
      $modelName = $this->retrievePluginName($tab, (int)$curId, $requesterModel);
      [$plugin, ] = explode('.', $modelName);
      foreach ($this->getHasManyAssociationModels($modelName) as $association) {
        $fullModelsName = $association;
        break;
      }
    } else if(str_contains($tab, '@action')) {
      [$modelName, ] = explode('@', $tab);
      $fullModelsName = $modelName;
    } else if(str_contains($tab, '.')) {
      [$plugin, $modelName] = explode('.', $tab);
    }

    // Prefer strict canonical resolution first (avoids caching an unintended stub/alias in the Locator).
    // Fall back to direct instantiation for UI tabs that are not ORM-associated with the requester.
    try {
      $qualified = StringUtilities::modelNameToQualifiedModelName($fullModelsName, $requesterModel);
      $modelsTable = TableRegistry::getTableLocator()->get($qualified);
    } catch (\Throwable $e) {
      $modelsTable = TableRegistry::getTableLocator()->get($fullModelsName);
    }

    $primary_link_list = $modelsTable->getPrimaryLinks();
    $primary_link = null;
    if(count($primary_link_list) > 1) {
      $primary_link = collection($primary_link_list)
        ->filter(function($link) use ($subnav_tabs) {
          $linkToClass = StringUtilities::foreignKeyToClassName($link);
          return \in_array($linkToClass, $subnav_tabs, true);
        })->first();
    } else if (\is_array($primary_link_list) && !empty($primary_link_list)) {
      $primary_link = $primary_link_list[0];
    }

    $foreignKey = StringUtilities::classNameToForeignKey($modelName);

    return match(true) {
      // The current controller and the tab controller match
      // If the action is edit or view then we return the id since we actually have no filter
      $fullModelsName === $curController
      && $tabAction !== 'index'                                  => ['id' => $curId],
      // If the current controller and the tab constructed controller do not match
      // but we have an edit or view action then we have no link filter and no id. We return empty
      $fullModelsName !== $curController
      && \in_array($tabAction, ['edit', 'view'], true)
      && isset($subnav_allowed_actions[$fullModelsName])         => [],
      // If the action is index then filter using the primary link
      \in_array($tabAction, ['index'], true)
      && $primary_link !== 'co_id'                               => [$primary_link => $curId],
      // - If the primary link is the co_id, it means this is a root element. As a result, we will construct
      //   the link filter key from the controller itself.
      $primary_link === 'co_id'                                  => [$foreignKey => $curId],
      // We fallback to the primary link directly.
      default                                                    => [$primary_link => $curId]
    };
  }

  /**
   * @return string|null
   * @since  COmanage Registry v5.0.0
   */
  public function getPluginName(): ?string
  {
    return $this->pluginName;
  }

  /**
   * Calculate the Tab link action
   * - First level Tab:
   * The first level usually allows edit/view action for the first tab link and index for the rest.
   * There are exceptions like:
   *   * External Identity Sources: This has a plugin structure. The first and second tab
   *     refer to the same action:
   *       - Plugin instantiation edit view
   *       - Plugin configuration view
   *
   * - Second level Tab:
   * The second level follows the same logic. The first Tab Link is a view/edit
   * while the rest are always an index Link
   *
   * @param   string  $tab
   * @param   bool    $isNested
   *
   * @return string|null
   * @since  COmanage Registry v5.0.0
   */
  public function getTabAction(string $tab, bool $isNested = false): ?string
  {
    $vv_sub_nav_attributes    = $this->getView()->get('vv_sub_nav_attributes');
    $subnav_tabs    = $vv_sub_nav_attributes['tabs'];
    $subnav_allowed_actions    = $vv_sub_nav_attributes['action'];
    if ($isNested) {
      $subnav_tabs    = $vv_sub_nav_attributes['nested']['tabs'];
      $subnav_allowed_actions    = $vv_sub_nav_attributes['nested']['action'];
    }
    $vv_action = $this->getView()->get('vv_action');
    $curController = $this->getView()->getRequest()->getParam('controller');

    $modelName = $tab;
    $controller = $modelName;

    $customActionRegex = '/^.*?(@action.)(\w+)/m';
    $customAction = preg_match_all($customActionRegex, $tab, $matches, PREG_SET_ORDER, 0);
    return match(true) {
      // We get the action from the configuration
      filter_var($customAction, FILTER_VALIDATE_BOOLEAN)                            => $matches[0][2],
      // First level ONLY: return the index action (applies to all tabs except the first one)
      $subnav_tabs[0] !== $tab
      && \in_array('index', $subnav_allowed_actions[$tab], true)                   => 'index',
      // Second level ONLY: return the action of the url. We could just say edit
      // but we might have a use case with a different action?
      $controller === $curController
      && \in_array($vv_action, $subnav_allowed_actions[$tab], true)
      && $isNested                                                                 => $vv_action,
      // Return the first action we allow in the configuration
      default                                                                      => $subnav_allowed_actions[$tab][0]
    };
  }

  /**
   * @param   string|null  $pluginName
   *
   * @return void
   * @since  COmanage Registry v5.0.0
   */
  public function setPluginName(?string $pluginName): void
  {
    $this->pluginName = $pluginName;
  }

  /**
   * Get the plugin name from the database
   *
   * @param string $tab
   * @param int $curId
   * @param string|null $requesterModel
   * @return string|null
   * @since  COmanage Registry v5.0.0
   */
  public function retrievePluginName(string $tab, int $curId, ?string $requesterModel = null): ?string
  {
    // Get the name of the Core Model
    $coreModel = substr($tab, 0, strrpos($tab, '.'));

    // if this is not a real core table class, require requester context to resolve it canonically
    if (str_contains($coreModel, '.')) {
      $qualifiedCoreModel = $coreModel;
    } else {
      $coreTableClass = 'App\\Model\\Table\\' . $coreModel . 'Table';

      if (class_exists($coreTableClass)) {
        $qualifiedCoreModel = $coreModel;
      } else {
        // Use requester associations to find the canonical Plugin.Model
        $qualifiedCoreModel = StringUtilities::modelNameToQualifiedModelName($coreModel, $requesterModel);
      }
    }

    $ModelTable = TableRegistry::getTableLocator()->get($qualifiedCoreModel);

    // If this table actually has a "plugin" column, fetch the plugin model path from the record.
    // Example (pluggable wrapper tables):
    //   ProvisioningTargets.plugin = "LdapConnector.LdapProvisioners"
    if ($ModelTable->getSchema()->hasColumn('plugin')) {
      $response = $ModelTable
        ->find()
        ->select(['plugin'])
        ->where(['id' => $curId])
        ->first();

      return (string)($response?->plugin ?? '');
    }

    return null;
  }

  /**
   * Get reference to Model Table
   *
   * @param   string  $modelsName
   *
   * @return Table
   * @since  COmanage Registry v5.0.0
   */
  public function getModelTableReference(string $modelsName): Table
  {
    return TableRegistry::getTableLocator()->get($modelsName);
  }

  /**
   * Select count(*)
   *
   * @param   string  $modelName       Model name in `group_members` format
   * @param   array   $whereClause     where clause array
   *
   * @return int
   * @since  COmanage Registry v5.0.0
   */
  public function getModelTotalCount(string $modelName, array $whereClause): int
  {
    $modelsName = Inflector::camelize($modelName);
    $ModelTable = TableRegistry::getTableLocator()->get($modelsName);
    $count = $ModelTable->find()
                        ->where($whereClause)
                        ->count();

    return $count;
  }

  /**
   * Get Person Status by ID
   *
   * @param   int  $personId
   *
   * @return string
   * @since  COmanage Registry v5.0.0
   */
  public function getPersonStatus(int $personId): string
  {
    $peopleTable = TableRegistry::getTableLocator()->get('people');
    $response = $peopleTable
      ->find()
      ->select(['status'])
      ->where(['id' => $personId])
      ->first();

    return $response?->status;
  }

  /**
   * Get Person Full Name by
   *
   * @param   int  $personId
   *
   * @return string
   * @since  COmanage Registry v5.0.0
   */
  public function getPersonPrimaryName(int $personId): string
  {
    $namesTable = TableRegistry::getTableLocator()->get('names');
    $response = $namesTable
      ->find()
      ->select(['given', 'family', 'middle', 'honorific', 'suffix'])
      ->where(['person_id' => $personId])
      ->where(['primary_name' => true])
      ->first();

    return $response?->full_name;
  }

  /**
   * @return string|null
   * @since  COmanage Registry v5.0.0
   */
  public function getAssociation(): ?string
  {
    return $this->association;
  }

  /**
   * @param   string|null  $association
   *
   * @return void
   * @since  COmanage Registry v5.0.0
   */
  public function setAssociation(?string $association): void
  {
    $this->association = $association;
  }
}