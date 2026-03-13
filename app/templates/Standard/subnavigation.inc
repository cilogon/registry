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

$modelsName = $this->getName();
$tabsConfiguration = [];

/**
 * Subnavigation is established by setting a variable called $subnav in fields.inc/columns.inc  
 * either to a string that matches a named set from the $subnavConfig[] array below
 * or by setting $subnav = [a local tab definition array].
 * 
 * For core views, the tab definition will typically reference a named set below,
 * e.g. $subnav = 'people', or $subnav = 'standard'.
 * 
 * For plugins, the tab definition will typically be local in the plugin fields.inc config.
 * For example (in app/availableplugins/PasswordAuthenticator/templates/PasswordAuthenticators/fields.inc):
 * $subnav = [
 *   'tabs' => ['Authenticators', 'PasswordAuthenticator.PasswordAuthenticators'],
 *   'action' => [
 *     'Authenticators' => ['edit'],
 *     'PasswordAuthenticator.PasswordAuthenticators' => ['edit']
 *   ]
 * ]
 * 
 * Note that the 'tabs' array may use associative arrays rather than strings to pass in a custom tab label. 
 * See $subnavConfig['people']['tabs'] below.
 */
  
// Subnavigation configuration definitions for common subnavigation sets.
// These are here to avoid duplicate configurations when handling core views.
$subnavConfig = [
  // STANDARD: for any object that has a simple two-tab set of "PROPERTIES" and "PLUGIN CONFIGURATION".
  // Use $subnav = 'standard' for the properties tab; each plugin tab definition will live in the 
  // plugin fields.inc config as $subnav = [the tab definition array]
  // as described above.
  'standard' => [
    'tabs' => [$modelsName, $modelsName . '.Plugin'],
    'action' => [
      $modelsName => ['edit'],
      $modelsName . '.Plugin' => ['edit']
    ]
  ],
  
  // PEOPLE
  'people' => [
    // Ordered list of Tabs
    'tabs' => [
      'People', 'PersonRoles', 'ExternalIdentities',
      [
        // A tab may be defined as just a model name (as above)
        // or as an associative array using the following form.
        // This allows for a custom label. 
        'tabModel' => 'GroupMembers',
        'tabLabel' => __d('controller','Groups', 99)
      ]
    ],
    // What actions will include the subnavigation header
    'action' => [
      'People' => ['edit', 'view'],
      'PersonRoles' => ['edit','view','index'],
      'ExternalIdentities' => ['edit','view','index'],
      'GroupMembers' => ['index']
    ],
    // What model will have a counter-badge after the tab title
    'counter' => ['PersonRoles', 'ExternalIdentities', 'GroupMembers'],
    'nested' => [
      // Ordered list of Tabs
      'tabs' => ['ExternalIdentities', 'ExternalIdentityRoles'],
      // What actions will include the subnavigation header
      'action' => [
        // If a model renders in a subnavigation mode in edit/view mode, it cannot
        // render in index mode for the same use case/context
        // XXX edit should go first.
        'ExternalIdentities' => ['edit', 'view'],
        'ExternalIdentityRoles' => ['edit', 'view', 'index'],
      ],
      // What model will have a counter-badge after the tab title
      'counter' => ['ExternalIdentityRoles'],
    ]
  ],
  
  // GROUPS
  'groups' => [
    'tabs' => ['Groups', 'GroupMembers', 'GroupNestings'],
    'action' => [
      'Groups' => ['edit', 'view'],
      'GroupMembers' => ['index'],
      'GroupNestings' => ['index'],
    ],
    'counter' => ['GroupMembers']
  ],
  
  // ENROLLMENT FLOWS and PETITIONS
  'petitions' => [
    'tabs' => ['EnrollmentFlows', 'EnrollmentFlowSteps', 'Petitions'],
    'action' => [
      'EnrollmentFlows' => ['edit', 'view'],
      'EnrollmentFlowSteps' => ['index'],
      'Petitions' => ['index'],
    ],
    'counter' => ['EnrollmentFlowSteps', 'Petitions']
  ],
  
  // ENROLLMENT FLOW STEPS
  'steps' => [
    'tabs' => ['EnrollmentFlowSteps', 'EnrollmentFlowSteps.Plugin', 'EnrollmentFlowSteps.Hierarchy'],
    'action' => [
      'EnrollmentFlowSteps' => ['edit', 'view'],
      'EnrollmentFlowSteps.Plugin' => ['edit'],
      // This means that we are looking at the plugins associated model
      // EnrollmentFlowSteps -> plugin -> @plugin
      // XXX There might be plugins that have no hasMany associations. We will check
      //     for these use cases inside the element.
      'EnrollmentFlowSteps.Hierarchy' => ['index']
    ],
  ],
  
  // EXTERNAL IDENTITY SOURCES
  'eis' => [
    'tabs' => ['ExternalIdentitySources', 'ExternalIdentitySources.Plugin', 'ExternalIdentitySources@action.search'],
    'action' => [
      'ExternalIdentitySources' => ['edit', 'view', 'search'],
      'ExternalIdentitySources.Plugin' => ['edit'],
      'ExternalIdentitySources@action.search' => [],
    ],
  ],
];

// Do not render subnavigation if $subnav is empty or if we have no configuration array. 
if(!empty($subnav) && (is_array($subnav) || (is_string($subnav) && !empty($subnavConfig[$subnav])))) {
  if(is_array($subnav)) {
    // We are rendering a locally configured tabs configuration set in fields.inc or columns.inc.
    $tabsConfiguration = $subnav;
  } else {
    // We are rendering a standard set of tabs as defined in $subnavConfig above.
    $tabsConfiguration = $subnavConfig[$subnav];  
  }
  
  if(!empty($topLinks) && $vv_action == 'edit') {
    // Pass along the toplink. These are useful for building the action menu and the plugin sub navigation
    $subnavAttr['topLinks'] = $topLinks;
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
    ||
    ( // Special case enrollment flow steps
      $subnav = 'steps'
    )
  ) {
    print $this->element('subnavigation/navBar', ['subNavAttributes' => $tabsConfiguration]);
  }
}