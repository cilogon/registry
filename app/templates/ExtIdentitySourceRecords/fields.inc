<?php
/**
 * COmanage Registry External Identity Source Records Fields
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
  
$noticeText = __d(
  'information', 
  'ExternalIdentitySourceRecords.view',
  [
    $this->Url->build(
      [
        'controller' => 'external_identity_sources',
        'action' => 'retrieve',
        $vv_obj->external_identity_source_id,
        '?' => [
          'source_key' => $vv_obj->source_key
        ]
      ]
    )
  ]
);

print $this->element('notify/alert', [
  'message'     => $noticeText,
  'type'        => 'information'
]);

// This view does not support add or edit
if($vv_action == 'view') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'external_identity_source_id',
      'status'    => $vv_obj->external_identity_source->description,
      'link'      => [
        'url' => [
          'controller' => 'external_identity_sources',
          'action'     => 'edit',
          $vv_obj->external_identity_source->id
        ]
      ]
    ]
  ]);

  if(!empty($vv_obj->adopted_person_id)) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'adopted_person_id',
        'status'    => $vv_obj->adopted_person->primary_name->full_name,
        'link'      => [
          'url' => [
            'controller' => 'people',
            'action'     => 'edit',
            $vv_obj->adopted_person->id
          ]
        ]
      ]
    ]);
  }

  if(!empty($vv_obj->external_identity_id)) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'external_identity_id',
        'status'    => $vv_obj->external_identity->names[0]->full_name,
        'link'      => [
          'url' => [
            'controller' => 'external_identities',
            'action'     => 'edit',
            $vv_obj->external_identity->id
          ]
        ]
      ]
    ]);
  }

  foreach([
            'source_key',
            'last_update',
            'reference_identifier',
            'source_record'
          ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
      ]]);
  }
}
