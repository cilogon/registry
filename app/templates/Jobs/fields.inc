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

$fields = [];

// This view does not support edit
if($vv_action == 'add') {
  // We are registering a Job, so the fields we accept are somewhat limited/different
  // from view
  $fields = [
    'plugin' => [
      'fieldLabel' => __d('controller', 'Jobs', [1])
    ]
  ];
} elseif($vv_action == 'view') {
  $fields = [
    'plugin' => [
      'fieldLabel' => __d('controller', 'Jobs', [1]),
      'type' => 'text'
    ]
  ];

  $fields[] = 'status';

  if($vv_obj->status == \App\Lib\Enum\JobStatusEnum::InProgress) {
    $fields['percent_complete'] = [
      'status' => (string)$vv_obj->percent_complete
    ];
  }

  $fields = array_merge($fields, [
    'parameters' => [
      // Establish the supplemental field wrappers for the parameters field
      'beforeField' => 
        '<div class="field-supplement">
           <div class="form-check">
             <input class="form-check-input" type="checkbox" value="" id="format-toggle">
             <label class="form-check-label" for="format-toggle">' . __d('field','format') . '</label>
           </div>
         </div>',
      'afterField' =>
        '<script>
           parametersValue = $("#parameters").val();
           parametersJSON = JSON.parse(parametersValue);
            $("#format-toggle").click(function() {
              if($(this).is(":checked")) {
                $("#parameters").val(JSON.stringify(parametersJSON, null, 4));
                paramsHeight = Object.keys(parametersJSON).length + 6 + "em";
                $("#parameters").css("height",paramsHeight);
              } else {
                $("#parameters").val(parametersValue);
                $("#parameters").css("height","4em");
              }
           });
         </script>'
    ],
    'register_time' => [
      'labelIsTextOnly' => true
    ],
    'register_summary',
    'assigned_host',
    'assigned_pid',
    'start_after_time' => [
      'labelIsTextOnly' => true
    ],
    'start_time' => [
      'labelIsTextOnly' => true
    ],
    'start_summary',
    'finish_time' => [
      'labelIsTextOnly' => true
    ],
    'finish_summary',
    'requeue_interval',
    'retry_interval'
  ]);

  if(!empty($vv_obj->requeued_from_job->id)) {
    $link = [
      'url' => [
        'controller'  => 'jobs',
        'action'      => 'view',
        $vv_obj->requeued_from_job->id
      ]
    ];
    $fields['requeued_from_job_id'] = [
      'status' => (string)$vv_obj->requeued_from_job->id,
      'link' => $link,
    ];
  }
}

// Top Links
$topLinks[] = [
  'icon'  => 'history',
  'order' => 'Default',
  'label' => __d('controller', 'JobHistoryRecords', [99]),
  'link'  => [
    'controller'  => 'job_history_records',
    'action'      => 'index',
    'job_id'      => $vv_obj->id
  ],
  'class' => ''
];

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
      'dg_body_txt' => __d('operation', 'Jobs.cancel.confirm', [$vv_obj->id]),
      'dg_confirm_btn' => __d('operation', 'confirm.yes'),
      'dg_cancel_btn' => __d('operation', 'confirm.no')
    ],
    'class' => ''
  ];
}
