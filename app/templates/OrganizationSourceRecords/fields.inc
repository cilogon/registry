<?php
/**
 * COmanage Registry Organization Source Records Fields
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
  
$noticeText = __d(
  'information', 
  'ExternalIdentitySourceRecords.view',
  [
    $this->Url->build(
      [
        'controller' => 'organization_sources',
        'action' => 'retrieve',
        $vv_obj->organization_source_id,
        '?' => [
          'source_key' => $vv_obj->source_key
        ]
      ]
    )
  ]
);

$alerts = [
  [
    'type' => 'information',
    'message' => $noticeText
  ]
];

// This view does not support add or edit
if($vv_action == 'view') {
  $fields = [
    'organization_source_id' => [
      'status'    => $vv_obj->organization_source->description,
      'link'      => [
        'url' => [
          'controller' => 'organization_sources',
          'action'     => 'edit',
          $vv_obj->organization_source->id
        ]
      ]
    ]
  ];

  if(!empty($vv_obj->organization_id)) {
    $fields['organization_id'] = [
      'status'    => $vv_obj->organization->name,
      'link'      => [
        'url' => [
          'controller' => 'organizations',
          'action'     => 'edit',
          $vv_obj->organization->id
        ]
      ]
    ];
  }

  foreach([
            'source_key',
            'last_update',
            'source_record'
          ] as $field) {
    $fields[] = $field;
  }
}

$topLinks = [];

if(!empty($vv_obj->organization_id)) {
  $topLinks[] = [
    'icon' => 'visibility',
    'order' => 'Default',
    'label' => __d('operation', 'view.a', [__d('controller', 'Organizations', [1])]),
    'link' => [
      'controller'  => 'organizations',
      'action'      => 'view',
      $vv_obj->organization_id
    ]
  ];

  $topLinks[] = [
    'icon' => 'visibility',
    'order' => 'Default',
    'label' => __d('operation', 'OrganizationSourceRecords.retrieve'),
    'link' => [
      'controller' => 'organization_sources',
      'action' => 'retrieve',
      $vv_obj->organization_source_id,
      '?' => [
        'source_key' => $vv_obj->source_key
      ]
    ]
  ];
}
