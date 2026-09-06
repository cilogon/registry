<?php
/**
 * COmanage Registry Departments Fields
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

$fields = [
  'name',
  'description',
  'type_id',
  'cou_id',
  'saml_scope',
  'logo_url',
  'public_description',
  'leadership_group_id',
  'administrative_group_id',
  'support_group_id'
];

// List the MVEAs that may be shown on the mveaCanvas
// When this array exists, the mveaCanvas.php element will render  
$mveas = [
  'ad_hoc_attributes',
  'addresses',
  'contacts',
  'email_addresses',
  'identifiers',
  'telephone_numbers',
  'urls'
];

// Name the MVEAs Entity Type
$mveasEntityType = "department";

// $addMenuLinks is also given slightly different treatment from the typical $topLinks found in most views: 
// it is a page-global menu used for adding MVEAs and is given special treatment in element/mveaCanvas.php.
$addMenuLinks = [
  [
    'controller' => 'ad_hoc_attributes',
    'action' => 'add',
    'icon' => 'user_attributes',
    'iconClass' => 'material-symbols-outlined'
  ],
  [
    'controller' => 'addresses',
    'action' => 'add',
    'icon' => 'contact_mail',
    'iconClass' => 'material-symbols-outlined'
  ],
  [
    'controller' => 'contacts',
    'action' => 'add',
    'icon' => 'contacts',
    'iconClass' => 'material-symbols-outlined'
  ],
  [
    'controller' => 'email_addresses',
    'action' => 'add',
    'icon' => 'email',
    'iconClass' => 'material-symbols-outlined'
  ],
  [
    'controller' => 'identifiers',
    'action' => 'add',
    'icon' => 'fingerprint'
  ],
  [
    'controller' => 'telephone_numbers',
    'action' => 'add',
    'icon' => 'phone'
  ],
  [
    'controller' => 'urls',
    'action' => 'add',
    'icon' => 'link'
  ]
];
