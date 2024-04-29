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
?>
<?php
// This view does not support add or edit
if($vv_action == 'view') {
  print $this->Field->control('comment');

  print $this->Field->control('action');

  print $this->Field->control('source');

  print $this->Field->control('created');

  if($vv_obj->status == NotificationStatusEnum::PendingAcknowledgment) {
    print $this->Field->statusControl(
      fieldName:  'status',
      status:     __d('enumeration', 'NotificationStatusEnum.PA'),
      link:       [
                    'url' => [
                      'action' => 'acknowledge',
                      $vv_obj->id
                    ],
                    'label' => __d('operation', 'acknowledge'),
                    'confirm' => __d('operation', 'Notifications.acknowledge.confirm')
                  ]
    );
  } else {
    print $this->Field->control('status');
  }  

  print $this->Field->statusControl(
    fieldName:  'subject_person_id',
    status:     !empty($vv_obj->subject_person->primary_name)
                ? $vv_obj->subject_person->primary_name->full_name
                : "",
    link:       !empty($vv_obj->subject_person)
                ? ['url' => [
                    'controller' => 'people',
                    'action' => 'edit',
                    $vv_obj->subject_person->id
                  ]]
                : []
  );

  print $this->Field->statusControl(
    fieldName:  'recipient_person_id',
    status:     !empty($vv_obj->recipient_person->primary_name)
                ? $vv_obj->recipient_person->primary_name->full_name
                : "",
    link:       !empty($vv_obj->recipient_person)
                ? ['url' => [
                    'controller' => 'people',
                    'action' => 'edit',
                    $vv_obj->recipient_person->id
                  ]]
                : []
  );

  print $this->Field->statusControl(
    fieldName:  'actor_person_id',
    status:     !empty($vv_obj->actor_person->primary_name)
                ? $vv_obj->actor_person->primary_name->full_name
                : "",
    link:       !empty($vv_obj->actor_person)
                ? ['url' => [
                    'controller' => 'people',
                    'action' => 'edit',
                    $vv_obj->actor_person->id
                  ]]
                : []
  );

  print $this->Field->control('resolution_time');

  print $this->Field->statusControl(
    fieldName:  'resolver_person_id',
    status:     !empty($vv_obj->resolver_person->primary_name)
                ? $vv_obj->resolver_person->primary_name->full_name
                : "",
    link:       !empty($vv_obj->resolver_person)
                ? ['url' => [
                    'controller' => 'people',
                    'action' => 'edit',
                    $vv_obj->resolver_person->id
                  ]]
                : []
  );

  print $this->Field->control('notification_time');

  print $this->Field->control('email_subject');

  print $this->Field->control('email_body_text');

  print $this->Field->control('resolution_subject');

  print $this->Field->control('resolution_body');
}
