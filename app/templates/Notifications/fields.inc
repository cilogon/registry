<?php
/**
 * COmanage Registry Notifications Fields
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

use \App\Lib\Enum\NotificationStatusEnum;

$fields = [];

// This view does not support add or edit
if($vv_action == 'view') {
  
  $fields = [
   'comment',
   'action',
   'source',
   'created',
 ];

  if($vv_obj->status == NotificationStatusEnum::PendingAcknowledgment) {
    $fields['status'] = [
      'status'    => __d('enumeration', 'NotificationStatusEnum.PA'),
      'link'      => [
                      'url' => [
                        'action' => 'acknowledge',
                        $vv_obj->id
                      ],
                      'label' => __d('operation', 'acknowledge'),
                      'confirm' => __d('operation', 'Notifications.acknowledge.confirm')
                    ]
    ];
  } else {
    $fields[] = 'status';
  }

  $fields = array_merge($fields, [
    'subject_person_id' => [
      'status' => $vv_obj->subject_person->primary_name?->full_name ?? '',
      'link'   => !empty($vv_obj->subject_person)
        ? ['url' => [
          'controller' => 'people',
          'action' => 'edit',
          $vv_obj->subject_person->id
        ]]
        : []
    ],
    'subject_group_id' => [
      'status'    => $vv_obj->subject_group->name ?? '',
      'link'      => !empty($vv_obj->subject_group)
        ? ['url' => [
          'controller' => 'groups',
          'action' => 'edit',
          $vv_obj->subject_group->id
        ]]
        : []
    ],
    'recipient_person_id' => [
      'status'    => $vv_obj->recipient_person->primary_name?->full_name ?? '',
      'link'      => !empty($vv_obj->recipient_person)
        ? ['url' => [
          'controller' => 'people',
          'action' => 'edit',
          $vv_obj->recipient_person->id
        ]]
        : []
    ],
    'recipient_group_id' => [
      'status'    => $vv_obj->recipient_group->name ?? '',
      'link'      => !empty($vv_obj->recipient_group)
        ? ['url' => [
          'controller' => 'groups',
          'action' => 'edit',
          $vv_obj->recipient_group->id
        ]]
        : []
    ],
    'actor_person_id' => [
      'status'    => $vv_obj->actor_person->primary_name?->full_name ?? '',
      'link'      => !empty($vv_obj->actor_person)
        ? ['url' => [
          'controller' => 'people',
          'action' => 'edit',
          $vv_obj->actor_person->id
        ]]
        : []
    ],
    'resolution_time',
    'resolver_person_id' => [
      'status'    => $vv_obj->resolver_person->primary_name?->full_name ?? '',
      'link'      => !empty($vv_obj->resolver_person)
        ? ['url' => [
          'controller' => 'people',
          'action' => 'edit',
          $vv_obj->resolver_person->id
        ]]
        : []
    ],
    'notification_time',
    'email_subject',
    'email_body_text',
    'resolution_subject',
    'resolution_body'
  ]);
}

// Top Links
if(!empty($vv_obj) && $vv_obj->canCancel()) {
  $topLinks[] = [
    'icon'  => 'cancel',
    'order' => 'Default',
    'label' => __d('operation', 'cancel'),
    'link'  => [
      'action' => 'cancel',
      $vv_obj->id
    ],
    'confirm' => [
      'dg_body_txt' => __d('operation', 'Notifications.cancel.confirm'),
      'dg_cancel_btn' => __d('enumeration', 'YesBooleanEnum.0'),
      'dg_confirm_btn' => __d('enumeration', 'YesBooleanEnum.1')
    ],
    'class' => ''
  ];
}

if(!empty($vv_obj) && $vv_obj->canNotify()) {
  $topLinks[] = [
    'icon'  => 'send',
    'order' => 'Default',
    'label' => __d('operation', 'resend'),
    'link'  => [
      'action' => 'resend',
      $vv_obj->id
    ],
    'confirm' => [
      'dg_body_txt' => __d('operation', 'Notifications.resend.confirm'),
      'dg_cancel_btn' => __d('enumeration', 'YesBooleanEnum.0'),
      'dg_confirm_btn' => __d('enumeration', 'YesBooleanEnum.1')
    ],
    'class' => ''
  ];
}
