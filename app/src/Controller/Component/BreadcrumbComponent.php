<?php
/**
 * COmanage Registry Breadcrumb Component
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

namespace App\Controller\Component;

use \Cake\Controller\Component;
use \Cake\Event\EventInterface;
use \Cake\ORM\TableRegistry;
use \Cake\Utility\Inflector;
use \App\Lib\Util\StringUtilities;

class BreadcrumbComponent extends Component {
  use \App\Lib\Traits\LabeledLogTrait;

  /*
   * Breadcrump example
   * COmanage Registry > Alfa Community > Configuration > External Identity Sources > Test Filesource Plugin > Configure Test Filesource Plugin
   *
   * Root link(prepend):  COmanage Registry // Root node (link)
   * CO Level Link:       Alfa Community    // if Current CO is defined (link)
   * configuration:       Configuration     // Configuration breadcrumb (link)
   * path:                Parent            // Render the path from dashboard (link)
   * Title Links:
   * Page Title:          Title             // e.g. Configure Test Filesource Plugin (string)
   */

  // Configuration provided by the controller
  // Don't render any breadcrumbs
  protected $skipAllPaths = [];
  // Don't render the configuration link
  protected $skipConfigPaths = [];
  // Don't render the parent links
  protected $skipParentPaths = [];
  // Inject parent links (these render before the index link, if set)
  // The parent links are constructed as part of the injectPrimaryLink function, as well as in the StandardController and
  // in the StandardPluginController, MVEAController, ProvisioningHistoryRecordController, etc. These controllers are
  // a descendant from the StandardController we will calculate the Parents twice. To avoid duplicates, the
  // injectParents table has to be an associative array. The uniqueness of the key will preserve the uniqueness of
  // the parent path while the order of firing will create the correct breadcrumb path order
  protected $injectParents = [];
  // Inject title links (immediately before the title breadcrumb)
  protected $injectTitleLinks = [];
  
  /**
   * Callback run prior to rendering the view.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */
  
  public function beforeRender(EventInterface $event) {
    $controller = $event->getSubject();
    $request = $controller->getRequest();

    if($request->is('restful') || $request->is('ajax')) {
      return;
    }

    // Determine the request target, but strip off query params
    $requestTarget = $request->getRequestTarget(false);

    $skipAll = false;
    $skipConfig = false;

    foreach($this->skipAllPaths as $p) {
      if(preg_match($p, $requestTarget)) {
        $skipAll = true;
        break;
      }
    }

    foreach($this->skipConfigPaths as $p) {
      if(preg_match($p, $requestTarget)) {
        $skipConfig = true;
        break;
      }
    }

    // Determine if the current request maps to a path where
    // breadcrumb rendering should be skipped in whole or in part
    $controller->set('vv_bc_skip', $skipAll);

    $controller->set('vv_bc_skip_config', $skipConfig);

    $table = $controller->getCurrentTable();

    // Do we have a target model, and if so is it a configuration
    // model (eg: ApiUsers) or an object model (eg: CoPeople)?
    if(method_exists($table, "isConfigurationTable")
    ) {
      $controller->set('vv_bc_configuration_link', $table->isConfigurationTable());
    } else {
      $controller->set('vv_bc_configuration_link', false);
    }

    // Build a list of intermediate parent links, starting with any
    // injected parents. This overrides $skipParentPaths.
    $parents = $this->injectParents;

    $skipParent = false;

    foreach($this->skipParentPaths as $p) {
      if(preg_match($p, $requestTarget)) {
        $skipParent = true;
        break;
      }
    }

    if(!$skipParent) {
      // For non-index views, insert a link back to the index.
      $action = $request->getParam('action');
      $primaryLink = $controller->getPrimaryLink(true);

      if($action != 'index') {
        $target = [
          'plugin'     => $primaryLink->plugin ?? null,
          'controller' => $controller->getName(),
          'action'     => 'index'
        ];

        if(!empty($primaryLink->attr)) {
          $target['?'] = [$primaryLink->attr => $primaryLink->value];
        }

        $label = StringUtilities::localizeController(
          $controller->getName(),
          $primaryLink->plugin ?? null,
          true
        );

        $parents[] = [
          'label'   => $label,
          'target'  => $target
        ];
      }
    }

    $controller->set('vv_bc_parents', $parents);

    $controller->set('vv_bc_title_links', $this->injectTitleLinks);
  }

  /**
   * Inject the primary link into the breadcrumb path.
   * 
   * @param  object $link            Primary Link (as returned by getPrimaryLink())
   * @param  bool   $index           Include link to parent index
   * @param  string|null $linkLabel  Override the constructed label
   *
   *@since  COmanage Registry v5.0.0
   */

  public function injectPrimaryLink(object $link, bool $index = true, string $linkLabel = null): void
  {
    $controller = $this->getController();
    $request    = $controller->getRequest();

    // Ensure null stays null; don’t coerce to 0
    $idParam = $request->getParam('pass.0');
    $id      = $idParam !== null ? (int)$idParam : null;

    // Determine link model name, optionally overridden by the view
    $linkModelName = $controller->viewBuilder()->getVar('vv_primary_link_model')
      ?? StringUtilities::foreignKeyToClassName($link->attr);

    // Fully-qualify with plugin if not already qualified
    $linkModelFqn = StringUtilities::qualifyModelPath($linkModelName, $link->plugin ?? null);

    // Permissions for current page and linked entity
    $pagePermissions   = $controller->RegistryAuth->calculatePermissionsForView(id: $id);
    $linkedPermissions = $controller->RegistryAuth->getTablePermissions(
      table: $controller->fetchTable($linkModelFqn),
      id:    (int)$link->value
    );

    try {
      // Map the current action to a canonical one for breadcrumbs/contains
      $mappedAction = $this->mapActionForBreadcrumb(
        requestAction: $request->getParam('action'),
        currentId:     $id,
        pagePermissions: $pagePermissions,
        peopleActionOverride: function () use ($controller, $link, $linkedPermissions, $linkModelFqn): string {
          // For People, derive edit/view based on roles and granted permissions
          $alias = \Cake\ORM\TableRegistry::getTableLocator()->get($linkModelFqn)->getAlias();
          if ($alias !== 'People') {
            return '';
          }

          $roles = $controller->RegistryAuth->getApplicationUserRoles($link->co_id);

          $canEdit = (
              in_array('platformAdmin', $linkedPermissions['entity']['edit'] ?? [], true) && !empty($roles['platform'])
            ) || (
              in_array('coAdmin', $linkedPermissions['entity']['edit'] ?? [], true) && !empty($roles['co'])
            );

          $canView = (
              in_array('platformAdmin', $linkedPermissions['entity']['view'] ?? [], true) && !empty($roles['platform'])
            ) || (
              in_array('coAdmin', $linkedPermissions['entity']['view'] ?? [], true) && !empty($roles['co'])
            );

          return $canEdit ? 'edit' : ($canView ? 'view' : '');
        },
        forChainItem: true
      );

      $linkTable = \Cake\ORM\TableRegistry::getTableLocator()->get($linkModelFqn);
      $contain   = $this->resolveContainList($linkTable, $mappedAction);

      // Normalize attr (people_id → id when the attr matches the model’s own foreign key)
      $modelAlias = $linkTable->getAlias();
      $foreignKey = StringUtilities::classNameToForeignKey($modelAlias);
      $linkAttr   = ($link->attr === $foreignKey) ? 'id' : $link->attr;

      // Fetch the linked entity; if not found, handle gracefully
      $linkedEntity = $linkTable
        ->find()
        ->where(["$modelAlias.$linkAttr" => $link->value])
        ->contain($contain)
        ->firstOrFail();

      // Optional parent index breadcrumb
      if ($index && method_exists($linkTable, 'findPrimaryLink')) {
        $parentLink = $linkTable->findPrimaryLink($linkedEntity->id);

        $this->injectParents[strtolower($linkModelFqn) . ':index'] = [
          'target' => [
            'plugin'      => $parentLink->plugin ?? StringUtilities::blankToNull(StringUtilities::pluginPlugin($linkModelFqn)) ?? null,
            'controller'  => StringUtilities::pluginModel($linkModelFqn),
            'action'      => 'index',
            '?'           => [
              $parentLink->attr => $parentLink->value
            ]
          ],
          'label' => StringUtilities::localizeController(
            controllerName: $linkModelFqn,
            pluginName:     $link->plugin ?? null,
            plural:         true
          )
        ];
      }

      // Determine target action for entity link
      $breadcrumbAction = $this->determineEntityAction($linkedEntity, $mappedAction);

      // Build a human-friendly label
      [$title] = StringUtilities::entityAndActionToTitle(
        entity:    $linkedEntity,
        modelPath: $linkModelFqn,
        action:    $breadcrumbAction,
        domain:    StringUtilities::pluginToTextDomain($link->plugin ?? null)
      );

      $title = StringUtilities::stripActionPrefix($title);

      // Inject the entity breadcrumb (unique per table:id)
      $this->injectParents[$this->composeEntityKey($linkTable->getTable(), (int)$linkedEntity->id)] = [
        'target' => [
          'plugin'      => $parentLink->plugin ?? StringUtilities::blankToNull(StringUtilities::pluginPlugin($linkModelFqn)) ?? null,
          'controller'  => StringUtilities::pluginModel($linkModelFqn),
          'action'      => $breadcrumbAction,
          (int)$linkedEntity->id
        ],
        'label'  => $linkLabel ?? $title,
      ];
    }
    catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
      $this->llog('error', "Breadcrumbs: linked entity not found for $linkModelFqn {$link->attr}={$link->value}");
    }
    catch (\Throwable $e) {
      // Never block rendering due to breadcrumbs
      $this->llog('error', 'Breadcrumbs failed: ' . $e->getMessage());
      $this->llog('error', json_encode($e->getTrace(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
  }

  /**
   * Inject a title link based on the display field of an entity into the breadcrumb set.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Table    $table    Table for $entity
   * @param  Entity   $entity   Entity to generate title link for
   * @param  string   $action   Action to link to
   * @param  string   $label    If set, use this label instead of the entity's displayField
   */
  
  public function injectTitleLink(
    $table,
    $entity,
    string $action='edit',
    ?string $label=null
  ): void {
    $displayField = $table->getDisplayField();

    $this->injectTitleLinks[] = [
      'target' => [
        'plugin'      => null,
        'controller'  => $table->getTable(),
        'action'      => $action,
        $entity->id
      ],
      'label' => $label ?: $entity->$displayField
    ];
  }

  /**
   * Set the set of paths that should be skipped when rendering breadcrumbs.
   * Paths are specified as regular expressions, eg: '/^\/cos\/select/'
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array  $skipPaths  Array of regular expressions describing paths to be skipped
   */

  public function skipAll(array $skipPaths): void
  {
    $this->skipAllPaths = $skipPaths;
  }

  /**
   * Set the set of paths which should not get a "configuration" breadcrumb even
   * though they might otherwise ordinarily get one (by being configuration objects).
   * Paths are specified as regular expressions, eg: '/^\/provisioning-targets\/status/'
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array  $skipPaths  Array of regular expressions describing paths
   */

  public function skipConfig(array $skipPaths): void
  {
    $this->skipConfigPaths = $skipPaths;
  }

  /**
   * Set the set of paths that should not automatically get a link back to their parent.
   * Paths are specified as regular expressions, eg: '/^\/co-settings\/edit/'
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array  $skipPaths  Array of regular expressions describing paths
   */

  public function skipParents(array $skipPaths): void
  {
    $this->skipParentPaths = $skipPaths;
  }

  /**
   * Resolves the contain list for a given table and mapped action
   *
   * @param \Cake\ORM\Table $table Table instance
   * @param string $mappedAction Mapped action name
   * @return array List of associations to contain
   * @since  COmanage Registry v5.2.0
 */
  private function resolveContainList($table, string $mappedAction): array
  {
    $method = 'get' . ucfirst($mappedAction) . 'Contains';
    return method_exists($table, $method) ? $table->$method() : [];
  }

  /**
   * Maps the request action to a canonical action for breadcrumb purposes
   *
   * @param string $requestAction Current request action
   * @param int|null $currentId Current entity ID
   * @param array $pagePermissions Permissions for the current page
   * @param callable $peopleActionOverride Override callback for People actions
   * @param bool $forChainItem Explicit call for a chain item (e.g. add)
   * @return string Mapped action name
   * @since  COmanage Registry v5.2.0
   */
  private function mapActionForBreadcrumb(
    string $requestAction,
    ?int $currentId,
    array $pagePermissions,
    callable $peopleActionOverride,
    bool $forChainItem
  ): string {
    $override = $peopleActionOverride();
    if ($override !== '') {
      return $override;
    }

    if ($forChainItem) {
      // Never render 'add' for chain items
      if ($requestAction === 'add') {
        return 'index';
      }
      // If the current page is edit/view/delete (and thus has an entity), prefer edit/view
      if (in_array($requestAction, ['edit', 'view', 'delete'], true) && $currentId !== null) {
        return (!empty($pagePermissions['edit'])) ? 'edit' : 'view';
      }
      return in_array($requestAction, ['index', 'view', 'delete', 'edit'], true)
        ? $requestAction
        : 'index';
    }

    // If you ever reuse this for non-chain items, decide appropriate behavior here
    return $requestAction;
  }

  /**
   * Determines the appropriate action for an entity in breadcrumbs
   *
   * @param \Cake\ORM\Entity $entity Entity instance
   * @param string $mappedAction Mapped action name
   * @return string Determined action name
   * @since  COmanage Registry v5.2.0
   */
  private function determineEntityAction($entity, string $mappedAction): string
  {
    // Only allow 'delete' to pass through; never return 'add' for existing entities
    if ($mappedAction === 'delete') {
      return 'delete';
    }

    if (method_exists($entity, 'isReadOnly')) {
      return $entity->isReadOnly() ? 'view' : 'edit';
    }

    // Fall back to mapped action when not 'add'/'delete'
    return $mappedAction === 'add' ? 'view' : $mappedAction;
  }

  /**
   * Composes a unique key for entity breadcrumb entries
   *
   * @param string $tableName Table name
   * @param int $id Entity ID
   * @return string Composed key
   * @since  COmanage Registry v5.2.0
   */
  private function composeEntityKey(string $tableName, int $id): string
  {
    return strtolower($tableName) . ':' . $id;
  }
}
