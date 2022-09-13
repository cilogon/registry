<?php
/**
 * COmanage Registry People Actions Menu Configuration
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

// Index top-links appear as top links in the index view
$indexTopLinks = [];

// Actions appear as row-level menu items in the index view gear icon and as a tab menu or top-link menu on 
// edit and view pages based on user permissions (TODO).

// TODO: Merge this with tabs (and top-links) available on the add-edit-view page (CFM-192 (and CO-647))
// TODO: Note that this is already marked up for 'tab' or 'topLink' for use in CFM-192. 
$actions = [
  [
    'controller' => 'names',
    'action' => 'index',
    'icon' => 'account_box',
    'iconClass' => 'material-icons-outlined',
    'type' => 'tab'
  ],
  [
    'controller' => 'email_addresses',
    'action' => 'index',
    'icon' => 'email',
    'iconClass' => 'material-icons-outlined',
    'type' => 'tab'
  ],
  [
    'controller' => 'identifiers',
    'action' => 'index',
    'icon' => 'fingerprint',
    'type' => 'tab',
    'class' => 'bottom-border'
  ],
  [
    'controller' => 'person_roles',
    'action' => 'index',
    'icon' => 'emoji_people',
    'type' => 'tab'
  ],
  [
    'controller' => 'external_identities',
    'action' => 'index',
    'icon' => 'system_update_alt',
    'type' => 'tab',
    'class' => 'bottom-border'
  ],
  [
    'controller' => 'ad_hoc_attributes',
    'action' => 'index',
    'icon' => 'check_box',
    'iconClass' => 'material-icons-outlined',
    'type' => 'tab'
  ],
  [
    'controller' => 'addresses',
    'action' => 'index',
    'icon' => 'contact_mail',
    'iconClass' => 'material-icons-outlined',
    'type' => 'tab'
  ],
  [
    'controller' => 'history_records',
    'action' => 'index',
    'icon' => 'history',
    'type' => 'tab'
  ],
  [
    'controller' => 'telephone_numbers',
    'action' => 'index',
    'icon' => 'phone',
    'type' => 'tab'
  ],
  [
    'controller' => 'urls',
    'action' => 'index',
    'icon' => 'link',
    'type' => 'tab'
  ]
];
