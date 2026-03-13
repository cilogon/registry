<?php
/**
 * COmanage Registry Enrollment Attributes Index Columns
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

use \Cake\Utility\Inflector;

$indexColumns = [
  'label' => [
    'type' => 'link',
    'sortable' => true,
//    'class' => 'cm-modal-link nospin', // launch this in a modal
    'dataAttrs' => [
      ['data-cm-modal-title', __d('operation', 'EnrollmentAttributes', 1)]
    ]
  ],
  'attribute' => [
// XXX we probably want to render something more like v4 does
    'type' => 'echo'
  ],
  'ordr' => [
    'type' => 'echo',
    'sortable' => true
  ],
  'required' => [
    'type' => 'boolean',
    'class' => 'YesBooleanEnum'
  ],
  'hidden' => [
    'type' => 'boolean',
    'class' => 'YesBooleanEnum'
  ]
];

$subnav = [
  'tabs' => ['EnrollmentFlowSteps', 'CoreEnroller.AttributeCollectors', 'EnrollmentFlowSteps.Hierarchy'],
  'action' => [
    'EnrollmentFlowSteps' => ['edit', 'view'],
    'CoreEnroller.AttributeCollectors' => ['edit'],
    'EnrollmentFlowSteps.Hierarchy' => ['index']
  ],
];


if(empty($attributes)) {
  return;
}

// Map attributes to icons
$attributeToIcons =
  [
    'adHocAttribute' => 'user_attributes',
    'address' => 'home',
    'affiliation_type_id' => 'person_search',
    'cou_id' => 'supervised_user_circle',
    'date_of_birth' => 'event',
    'department' => 'domain',
    'emailAddress' => 'email',
    'group_id' => 'groups',
    'identifier' => 'badge',
    'manager_person_id' => 'supervisor_account',
    'name' => 'face',
    'organization' => 'corporate_fare',
    'pronoun' => '3p',
    'sponsor_person_id' => 'person',
    'telephoneNumber' => 'call',
    'petition_textarea' => 'text_fields',
    'petition_text' => 'text_fields',
    'title' => 'title',
    'url' => 'link',
    'valid_from' => 'calendar_month',
    'valid_through' => 'calendar_month'
  ];

// Build the Add menu
$action_args = array();
// This is an add action as a result we do not have an obect id
// and we will use the username instead.
$action_args['vv_attr_id'] = $vv_user['username'];
$action_args['vv_actions_type'] = 'mvea-add-menu';
$action_args['vv_actions_title'] = __d('operation', 'add.attribute');
$action_args['vv_actions_icon'] = 'add_circle';
$action_args['vv_actions_icon_class'] = 'material-symbols-outlined';
$action_args['vv_actions_class'] = 'mvea-add-menu';
$actionOrderDefault = $this->Menu->getMenuOrder('Default');
foreach ($attributes as $attr => $hr_name) {
  $title = __d('operation', 'add.a-1', [
    __d('core_enroller', 'controller.EnrollmentAttributes', [1]),
    $hr_name
  ]);
  $actionLabel = $hr_name;
  // XXX This is already inside a modal. We just need the layout to be correct
//  $actionClass = 'cm-modal-link nospin';
  $actionOrder = $actionOrderDefault++;
  $actionIcon = $attributeToIcons[$attr] ?? $this->Menu->getMenuIcon('Default');
  $dataAttrs = ['data-cm-modal-title', __d('file-connector', 'EnrollmentAttributes', 1)];
  $actionUrl = [
    'pluing' => $this->getPlugin(),
    'controller' => $vv_controller,
    'action' => 'add',
    '?' => array_merge($linkFilter, ['attribute_type' => $attr])
  ];

  $action_args['vv_actions'][] = [
    'order' => $actionOrder,
    'icon' => $actionIcon,
    'iconClass' => 'material-symbols-outlined',
    'url' => $actionUrl,
    'class' => $actionClass ?? '',
    'label' => $actionLabel,
    'dataAttrs' => []
  ];
}
