<?php
/**
 * COmanage Registry Dashboards Controller
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

namespace App\Controller;

// XXX not doing anything with Log yet
use App\Lib\Util\StringUtilities;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use \App\Lib\Util\SearchUtilities;

class DashboardsController extends StandardController {
  /**
   * Perform Controller initialization.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function initialize(): void {
    parent::initialize();

    // Configure breadcrumb rendering
    $this->Breadcrumb->skipConfig([
                                    '/^\/dashboards\/artifacts/',
                                    '/^\/dashboards\/dashboard/',
                                    '/^\/dashboards\/registries/',
                                    '/^\/dashboards\/search/'
                                  ]);
    // There is currently no inventory of dashboards, so we skip parents
    // for configuration, dashboard, and registries actions
    $this->Breadcrumb->skipParents(['/^\/dashboards/']);
  }

  /**
   * Render the CO Configuration Dashboard.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function configuration() {
    $cur_co = $this->getCO();

    [$title, , ] = StringUtilities::entityAndActionToTitle(null,
                                                           null,
                                                           'co.features.all',
                                                           'menu');
    $this->set('vv_title', $title);

    // Construct the set of configuration items. For everything except CO Settings
    // we want to order by the localized text string.

    // We're assuming that the permission for each of these items is the same as for
    // configuration() itself, ie: CMP or CO Admin. But plausibly some of this stuff
    // could be delegated to (eg) a COU Admin at some point...

    $configMenuItems = [
      __d('controller', 'ApiUsers', [99]) => [
        'icon'          => 'vpn_key',
        'controller'    => 'api_users',
        'action'        => 'index'
      ],
      __d('controller', 'Apis', [99]) => [
        'icon'          => 'api',
        'controller'    => 'apis',
        'action'        => 'index'
      ],
      __d('controller', 'Authenticators', [99]) => [
        'icon'          => 'lock',
        'controller'    => 'authenticators',
        'action'        => 'index'
      ],
      __d('controller', 'Cous', [99]) => [
        'icon'          => 'people_outline',
        'controller'    => 'cous',
        'action'        => 'index'
      ],
      __d('controller', 'EnrollmentFlows', [99]) => [
        'icon'          => 'subscriptions',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'enrollment_flows',
        'action'        => 'index'
      ],
      __d('controller', 'ExternalIdentitySources', [99]) => [
        'icon'          => 'cloud_download',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'external_identity_sources',
        'action'        => 'index'
      ],
      __d('controller', 'IdentifierAssignments', [99]) => [
        'icon'          => 'badge',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'identifier_assignments',
        'action'        => 'index'
      ],
      __d('controller', 'MessageTemplates', [99]) => [
        'icon'          => 'email',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'message_templates',
        'action'        => 'index'
      ],
      __d('controller', 'MostlyStaticPages', [99]) => [
        'icon'          => 'article',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'mostly_static_pages',
        'action'        => 'index'
      ],
      __d('controller', 'Pipelines', [99]) => [
        'icon'          => 'valve',
        'controller'    => 'pipelines',
        'action'        => 'index'
      ],
      __d('controller', 'ProvisioningTargets', [99]) => [
        'icon'          => 'cloud_upload',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'provisioning_targets',
        'action'        => 'index'
      ],
// XXX restore when Reports are ready to be exposed.
//      __d('controller', 'Reports', [99]) => [
//        'icon'          => 'summarize',
//        'controller'    => 'reports',
//        'action'        => 'index'
//      ],
      __d('controller', 'Types', [99]) => [
        'icon'          => 'widgets',
        'controller'    => 'types',
        'action'        => 'index'
      ]
    ];

    ksort($configMenuItems);

    // Insert CO Settings to the front of the list

    $configMenuItems = array_merge([
                                     __d('controller', 'CoSettings', [99]) => [
                                       'icon'          => 'settings',
                                       'controller'    => 'co_settings',
                                       'action'        => 'manage'
                                     ]],
                                   $configMenuItems
    );

    $this->set('vv_configuration_menu_items', $configMenuItems);

    $platformMenuItems = [];

    $co = $this->getCO();

    if($co->isCOmanageCO()) {
      // Also pass the platform menu items

      $platformMenuItems = [
        __d('controller', 'Cos', [99]) => [
          'icon'          => 'home',
          'controller'    => 'cos',
          'action'        => 'index'
        ],
        __d('controller', 'Plugins', [99]) => [
          'icon'          => 'electrical_services',
          'controller'    => 'plugins',
          'action'        => 'index'
        ],
        __d('controller', 'TrafficDetours', [99]) => [
          'icon'          => 'fork_right',
          'controller'    => 'traffic_detours',
          'action'        => 'index'
        ]
      ];
    }

    ksort($platformMenuItems);

    $this->set('vv_platform_menu_items', $platformMenuItems);
    $registryMenuItems = [
      __d('controller', 'Groups', [99]) => [
        'icon'          => 'people',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'groups',
        'action'        => 'index'
      ],
      __d('controller', 'People', [99]) => [
        'icon'          => 'person',
        'controller'    => 'people',
        'action'        => 'index'
      ],
      __d('controller', 'Servers', [99]) => [
        'icon'          => 'computer',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'servers',
        'action'        => 'index'
      ]
    ];

    ksort($registryMenuItems);

    $this->set('vv_registries_menu_items', $registryMenuItems);

    $artifactMenuItems = [
      __d('controller', 'ExtIdentitySourceRecords', [99]) => [
        'icon'          => 'badge',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'ext_identity_source_records',
        'action'        => 'index'
      ],
      __d('controller', 'Jobs', [99]) => [
        'icon'          => 'work_history',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'jobs',
        'action'        => 'index'
      ],
      __d('controller', 'Notifications', [99]) => [
        'icon'          => 'notifications_active',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'notifications',
        'action'        => 'index'
      ],
      __d('controller', 'Petitions', [99]) => [
        'icon'          => 'pending_actions',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'petitions',
        'action'        => 'index'
      ]
    ];

    if($co->isCOmanageCO()) {
      $artifactMenuItems[__d('controller', 'AuthenticationEvents', [99])] = [
        // Authentication Events are only visible to the Platform Administrators
        // since they are tied to Identifiers, not CO-specific information.
        'icon'          => 'lock',
        'iconClass'     => 'material-symbols-outlined',
        'controller'    => 'authentication_events',
        'action'        => 'index',
        // Suppress injection of CO ID
        'platform'      => true
      ];
    }

    ksort($artifactMenuItems);

    $this->set('vv_artifacts_menu_items', $artifactMenuItems);
  }

  /**
   * Render a Dashboard.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int   $id Dashboard ID
   */
  public function dashboard(?int $id=null) {
    // XXX placeholder
  }

  /**
   * Perform a cross model search.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function search() {
    // Gather our search string.
    $q = '';
    if(!empty($this->request->getData('q'))) {
      // A search was passed in from the form on the Global Search bar. 
      $q = trim($this->request->getData('q'));
    }

    // Only process the request if we have a string of non-space characters
    if(!empty($q)) {
      $results = SearchUtilities::globalSearch($this->getCOID(), $q);

      if($results['limitReached']) {
        $this->Flash->information(__d('result', 'search.limit'));
      }
    }

    $this->set('vv_supported_models', SearchUtilities::getGlobalSearchModels());

    // It's a single match if there is a single person or person role result,
    // or if there is a single result overall, redirect to that result.
    if((count($results['Cos']) == 0
        && (count($results['People']) + count($results['Groups'])) == 1)
       ||
       (count($results['Cos']) == 1
        && (count($results['People']) + count($results['Groups'])) == 0)) {
      // Figure out which model matched, as well as the target model to redirect to
      $matchClass = null;
      $targetClass = null;
      $targetRecordId = null;

      foreach(['Cos', 'Groups', 'People'] as $m) {
        if(!empty($results[$m])) {
          $targetClass = $m;
          $targetRecordId = array_key_first($results[$m]);
          $matchClass = array_key_first($results[$m][$targetRecordId]);
        }
      }

      $this->Flash->information(__d('result',
                                    'search.exact',
                                    [filter_var($this->request->getData('q'), FILTER_SANITIZE_SPECIAL_CHARS),
                                      __d('controller', $matchClass, [1])]));

      // Redirect to the matchClass controller
      return $this->redirect([
                               'controller'  => Inflector::dasherize($targetClass),
                               'action'      => 'edit',
                               $targetRecordId
                             ]);

      // XXX handle plugins
    } elseif($results['uuid']) {
      // There is an exact match on uuid, redirect there

      $this->Flash->information(__d('result',
                                    'search.exact',
                                    [filter_var($this->request->getData('q'), FILTER_SANITIZE_SPECIAL_CHARS),
                                     'uuid']));

      return $this->redirect([
        'controller'  => Inflector::dasherize($results['uuid']['class']),
        'action'      => 'edit',
        $results['uuid']['entity']->id
      ]);
    } elseif(!empty($results['cri'])) {
    } elseif(count($results['Cos'])
             + count($results['People'])
             + count($results['Groups']) == 0) {
      $this->Flash->information(__d('result', 'search.none'));
    }

    $this->set('vv_results', $results);
    // XXX The action is search and the result is not a modelPath. In this use the pattern is reversed. We should
    //     probably reconsider the po naming for the result domain
    [$title, , ] = StringUtilities::entityAndActionToTitle(null,
                                                           null,
                                                           'search.results',
                                                           'result');
    $this->set('vv_title', $title);
  }
}