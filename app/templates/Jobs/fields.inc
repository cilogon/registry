<?php
/**
 * COmanage Registry Jobs Fields
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

// This view does not support add or edit
if($vv_action == 'view') {
  print $this->Field->control('plugin', labelText: __d('controller', 'Jobs', [1]));
  
  print $this->Field->control('status');

  if($vv_obj->status == \App\Lib\Enum\JobStatusEnum::InProgress) {
    print $this->Field->statusControl(
      fieldName: 'percent_complete',
      status: (string)$vv_obj->percent_complete
    );
  }

  print $this->Field->control('parameters');

  print $this->Field->control('register_time');

  print $this->Field->control('register_summary');

  print $this->Field->control('assigned_host');

  print $this->Field->control('assigned_pid');

  print $this->Field->control('start_after_time');

  print $this->Field->control('start_time');

  print $this->Field->control('start_summary');

  print $this->Field->control('finish_time');

  print $this->Field->control('finish_summary');

  print $this->Field->control('requeue_interval');

  print $this->Field->control('retry_interval');

  if(!empty($vv_obj->requeued_from_job->id)) {
    print $this->Field->statusControl(
      fieldName: 'requeued_from_job_id',
      status: (string)$vv_obj->requeued_from_job->id,
      link: [
        'url' => [
          'controller'  => 'jobs',
          'action'      => 'view',
          $vv_obj->requeued_from_job->id
        ]
      ]
    );
  }
}
