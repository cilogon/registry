<?php
/**
 * COmanage Registry External Identity Fields
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

if(!empty($vv_obj->ext_identity_source_record)) {

  $noticeText = __d(
    'information',
    'ExternalIdentities.source',
    [
      $this->Html->link(
        $vv_obj->ext_identity_source_record->external_identity_source->description,
        [
          'controller' => 'external-identity-sources',
          'action' => 'edit',
          $vv_obj->ext_identity_source_record->external_identity_source->id
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
  'status',
  'date_of_birth' // date
];

$subnav = 'people';

// List the MVEAs that may be shown on the mveaCanvas
// When this array exists, the mveaCanvas.php element will render  
$mveas = [
  'names',
  'email_addresses',
  'identifiers',
  'ad_hoc_attributes',
  'addresses',
  'telephone_numbers',
  'urls',
  'pronouns'
];

// Name the MVEAs Entity Type
$mveasEntityType = "external_identity";

// Top Links
$topLinks = [
  [
    'icon' => 'history',
    'order' => 'Default',
    'label' => __d('controller', 'HistoryRecords', [99]),
    'link' => [
      'controller' => 'history_records',
      'action' => 'index',
      '?' => [
        'external_identity_id' => $vv_obj->id
      ]
    ]
  ],
];

if($vv_obj?->ext_identity_source_record?->id !== null) {
  $topLinks[] =   [
    'icon' => 'visibility',
    'order' => 'Default',
    'class' => 'cm-modal-link nospin', // launch this in a modal
    'dataAttrs' => [
      ['data-cm-modal-title',__d('controller', 'ExtIdentitySourceRecords', 1)]
    ],
    'label' => __d('operation', 'view.a', [__d('controller', 'ExtIdentitySourceRecords', 1)]),
    'link' => [
      'controller' => 'ext_identity_source_records',
      'action' => 'view',
      $vv_obj->ext_identity_source_record->id
    ]
  ];

  $topLinks[] = [
    'icon' => 'stroller',
    'order' => 'Default',
    'label' => __d('operation', 'adopt'),
    'link' => [
      'action' => 'adopt',
      $vv_obj->id
    ],
    'confirm' => [
      'dg_body_txt' => __d(
        'operation',
        'ExternalIdentities.adopt.confirm',
        [
          $vv_obj->ext_identity_source_record->source_key,
          $vv_obj->ext_identity_source_record->external_identity_source->description,
        ]),
      'dg_confirm_btn' => __d('operation', 'adopt')
    ]
  ];

  $topLinks[] = [
    'icon' => 'move',
    'order' => 'Default',
    'label' => __d('operation', 'relink'),
    'link' => [
      'action' => 'relink',
      $vv_obj->id
    ]
  ];
}

// $addMenuLinks is also given slightly different treatment from the typical $topLinks found in most views: 
// it is a page-global menu used for adding MVEAs and is given special treatment in element/mveaCanvas.php.
$addMenuLinks = [
  [
    'controller' => 'names',
    'action' => 'add',
    'icon' => 'account_box'
  ],
  [
    'controller' => 'email_addresses',
    'action' => 'add',
    'icon' => 'email'
  ],
  [
    'controller' => 'identifiers',
    'action' => 'add',
    'icon' => 'fingerprint'
  ],
  [
    'controller' => 'ad_hoc_attributes',
    'action' => 'add',
    'icon' => 'user_attributes'
  ],
  [
    'controller' => 'addresses',
    'action' => 'add',
    'icon' => 'contact_mail'
  ],
  [
    'controller' => 'history_records',
    'action' => 'add',
    'icon' => 'history'
  ],
  [
    'controller' => 'pronouns',
    'action' => 'add',
    'icon' => '3p'
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
