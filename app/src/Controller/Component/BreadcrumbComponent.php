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
use \App\Lib\Util\StringUtilities;

class BreadcrumbComponent extends Component
{
  // Configuration provided by the controller
  // Don't render any breadcrumbs
  protected $skipAllPaths = [];
  // Don't render the configuration link
  protected $skipConfigPaths = [];
  // Don't render the parent links
  protected $skipParentPaths = [];
  // Inject parent links (these render before the index link, if set)
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
    $modelsName = $controller->getName();

    if(!$request->is('restful')) {
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

      // Do we have a target model, and if so is it a configuration
      // model (eg: ApiUsers) or an object model (eg: CoPeople)?
      if(isset($controller->$modelsName) // May not be set under certain error conditions
        && method_exists($controller->$modelsName, "isConfigurationTable")) {
        $controller->set('vv_bc_configuration_link', $controller->$modelsName->isConfigurationTable());
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
            'plugin'     => null,
            'controller' => $modelsName,
            'action'     => 'index'
          ];

          if(!empty($primaryLink->attr)) {
            $target['?'] = [$primaryLink->attr => $primaryLink->value];
          }

          $parents[] = [
            'label'   => __d('controller', $modelsName, [99]),
            'target'  => $target
          ];
        }
      }

      $controller->set('vv_bc_parents', $parents);

      $controller->set('vv_bc_title_links', $this->injectTitleLinks);
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
  ) {
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
   * Inject the primary link into the breadcrumb path.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  object link  Primary Link (as returned by getPrimaryLink())
   */

  public function injectPrimaryLink(object $link) {
    // eg: "People"
    $modelsName = StringUtilities::foreignKeyToClassName($link->attr);

    $contain = [];

    if($modelsName == 'People' || $modelsName == 'ExternalIdentities') {
      // We need the Primary Name to render it
      $contain[] = 'PrimaryName';
    }

    $linkTable = TableRegistry::getTableLocator()->get($modelsName);
    $linkObj = $linkTable->get($link->value, ['contain' => $contain]);
    $displayField = $linkTable->getDisplayField();

    $this->injectParents[] = [
      'target' => [
        'plugin'      => null,
        'controller'  => $modelsName,
        'action'      => 'index',
        '?'           => [
          'co_id' => $link->co_id
        ]
      ],
      'label' => __d('controller', $modelsName, [99])
    ];

    $label = $linkObj->$displayField;

    if(!empty($linkObj->primary_name)) {
      $label = $linkObj->primary_name->full_name;
    }

    // If we don't have a visible label use the record ID
    if(empty($label)) {
      $label = $linkObj->id;
    }

    $this->injectParents[] = [
      'target' => [
        'plugin'      => null,
        'controller'  => $modelsName,
        'action'      => 'edit',
        $linkObj->id
      ],
      'label' => $label
    ];
  }

  /**
   * Set the set of paths that should be skipped when rendering breadcrumbs.
   * Paths are specified as regular expressions, eg: '/^\/cos\/select/'
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array  $skipPaths  Array of regular expressions describing paths to be skipped
   */

  public function skipAll(array $skipPaths) {
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

  public function skipConfig(array $skipPaths) {
    $this->skipConfigPaths = $skipPaths;
  }

  /**
   * Set the set of paths that should not automatically get a link back to their parent.
   * Paths are specified as regular expressions, eg: '/^\/co-settings\/edit/'
   * 
   * @since  COmanage Registry v5.0.0
   * @param  array  $skipPaths  Array of regular expressions describing paths
   */

  public function skipParents(array $skipPaths) {
    $this->skipParentPaths = $skipPaths;
  }
}