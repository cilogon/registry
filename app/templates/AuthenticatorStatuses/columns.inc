<?php
/**
 * COmanage Registry Authenticator Statuses Index Columns
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

$pagination = false;

$indexColumns = [
  'description' => [
    'type' => 'echo'
  ],
  'status' => [
    'type' => 'enum',
    'class' => 'AuthenticatorStatusEnum'
  ],
  'comment' => [
    'type' => 'echo'
  ]
  ];

// $rowActions appear as row-level menu items in the index view gear icon
// Because these apply generically per row, we can't deep link into each plugin,
// but instead we need a generic URL that can redirect to the correct endpoint.
$rowActions = [
/* We can't have a view icon because the permissions configuration will conflict with
   Authenticators::view, but it's also a simpler interface to route everything through
   manage instead
  [
    'controller' => 'authenticators',
    'action' => 'view',
    'icon' => 'settings',
    'if' => 'notLocked',
    'label' => __d('operation', 'view'),
    'query' => function($entity) {
      // The standard index code will also inject authenticator_status_id, which we'll just ignore
      return [
        'authenticator_id' => $entity->authenticator_id,
        'person_id' => $entity->person_id
      ];
    }
  ],*/
  [
    'controller' => 'authenticators',
    'action' => 'manage',
    'icon' => 'settings',
    'if' => 'notLocked',
    'label' => __d('operation', 'Authenticators.manage'),
    'query' => function($entity) {
      // The standard index code will also inject authenticator_status_id, which we'll just ignore
      return [
        'authenticator_id' => $entity->authenticator_id,
        'person_id' => $entity->person_id
      ];
    }
  ],
  [
    'controller' => 'authenticators',
    'action' => 'reset',
    'icon' => 'lock_reset',
    'if' => 'notLocked',
    'label' => __d('operation', 'Authenticators.reset'),
    'query' => function($entity) {
      // The standard index code will also inject authenticator_status_id, which we'll just ignore
      return [
        'authenticator_id' => $entity->authenticator_id,
        'person_id' => $entity->person_id
      ];
    },
    'confirm' => [
      'dg_body_txt' => __d('operation', 'Authenticators.reset.confirm')
    ] 
  ],
  [
    'controller' => 'authenticators',
    'action' => 'lock',
    'icon' => 'lock',
    'if' => 'notLocked',
    'label' => __d('operation', 'Authenticators.lock'),
    'query' => function($entity) {
      // The standard index code will also inject authenticator_status_id, which we'll just ignore
      return [
        'authenticator_id' => $entity->authenticator_id,
        'person_id' => $entity->person_id
      ];
    },
    'confirm' => [
      'dg_body_txt' => __d('operation', 'Authenticators.lock.confirm')
    ]    
  ],
  [
    'controller' => 'authenticators',
    'action' => 'unlock',
    'icon' => 'lock_open_right',
    'if' => 'isLocked',
    'label' => __d('operation', 'Authenticators.unlock'),
    'query' => function($entity) {
      // The standard index code will also inject authenticator_status_id, which we'll just ignore
      return [
        'authenticator_id' => $entity->authenticator_id,
        'person_id' => $entity->person_id
      ];
    },
    'confirm' => [
      'dg_body_txt' => __d('operation', 'Authenticators.unlock.confirm')
    ]
  ]
];