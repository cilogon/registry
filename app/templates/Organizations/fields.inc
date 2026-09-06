<?php
/**
 * COmanage Registry Organizations Fields
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

if(!empty($vv_obj->organization_source_record)) {
  $noticeText = __d(
    'information',
    'Organizations.source',
    [
      $this->Html->link(
        $vv_obj->organization_source_record->organization_source->description,
        [
          'controller' => 'organization-sources',
          'action' => 'edit',
          $vv_obj->organization_source_record->organization_source->id
        ]
      )
    ]
  );

  // XXX The placement here is wrong; this should be displaying under the subtitle, not the main title.
  $alerts = [
    [
      'type' => 'information',
      'message' => $noticeText
    ]
  ];
}

$fields = [
  'name',
  'description',
  'type_id',
  'saml_scope',
  'logo_url'
];

$subnav = 'organizations';

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
$mveasEntityType = "organization";

$topLinks = [];

if($vv_obj?->organization_source_record?->id !== null) {
  $topLinks[] = [
    'icon' => 'visibility',
    'order' => 'Default',
    'class' => 'cm-modal-link nospin', // launch this in a modal
    'dataAttrs' => [
      ['data-cm-modal-title',__d('controller', 'OrganizationSourceRecords', 1)]
    ],
    'label' => __d('operation', 'view.a', [__d('controller', 'OrganizationSourceRecords', 1)]),
    'link' => [
      'controller' => 'organization_source_records',
      'action' => 'view',
      $vv_obj->organization_source_record->id
    ]
  ];
}

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
