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

// This view does not support edit
if($vv_action == 'add') {
  // We are registering a Job, so the fields we accept are somewhat limited/different
  // from view

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'plugin',
      'fieldLabel' => __d('controller', 'Jobs', [1])
    ]
  ]);
} elseif($vv_action == 'view') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'plugin',
      'fieldLabel' => __d('controller', 'Jobs', [1]),
      'fieldOptions' => [
        'type' => 'text'
      ]
    ]
  ]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'status'
    ]
  ]);

  if($vv_obj->status == \App\Lib\Enum\JobStatusEnum::InProgress) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'percent_complete',
        'status' => (string)$vv_obj->percent_complete
      ]
    ]);
  }
  
  // Establish the supplemental field wrappers for the parameters field
  $beforeField = 
    '<div class="field-suppliment">
       <div class="form-check">
         <input class="form-check-input" type="checkbox" value="" id="format-toggle">
         <label class="form-check-label" for="format-toggle">' . __d('field','format') . '</label>
       </div>
     </div>';
  
  $afterField = 
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
     </script>';

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'parameters',
    ],
    'beforeField' => $beforeField,
    'afterField' => $afterField
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'register_time',
      'labelIsTextOnly' => true
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'register_summary'
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'register_time',
      'labelIsTextOnly' => true
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'assigned_host'
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'assigned_pid'
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'start_after_time',
      'labelIsTextOnly' => true
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'start_time',
      'labelIsTextOnly' => true
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'start_summary'
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'finish_time',
      'labelIsTextOnly' => true
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'finish_summary'
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'requeue_interval'
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'retry_interval'
    ]
  ]);

  if(!empty($vv_obj->requeued_from_job->id)) {
    $link = [
      'url' => [
        'controller'  => 'jobs',
        'action'      => 'view',
        $vv_obj->requeued_from_job->id
      ]
    ];
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => 'requeued_from_job_id',
        'status' => (string)$vv_obj->requeued_from_job->id,
        'link' => $link,
      ]
    ]);
  }
}
