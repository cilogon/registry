<?php
/**
 * COmanage Registry API Users Fields
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

// This view does not support read-only
if($vv_action == 'add' || $vv_action == 'edit') {  
  if($vv_cur_co->id == 1) {
    print $this->Field->banner(__d('information', 'api.cmp'));
  }
  
  // AR-ApiUser-3 For namespacing purposes, API Users are named with a prefix consisting of the string "co_#.".
  print $this->Field->control('username', prefix: 'co_' . $vv_cur_co->id . '.');
  
  // We link to the "Generate" button on edit only
  $generateLink = [];
  $labelIsTextOnly = false;
  
  if(!empty($vv_obj->id)) {
    $generateLink = [
      'url' => [
        'controller' => 'api_users',
        'action'     => 'generate',
        $vv_obj->id
      ],
     'label' => __d('operation', 'api.key.generate'),
     'class' => 'provisionbutton nospin',
     'confirm' => __d('operation', 'api.key.generate.confirm')
    ];
    
    $labelIsTextOnly = true;
  }
  
  print $this->Field->statusControl('api_key',
                                    !empty($vv_obj->api_key) ? __d('enumeration', 'SetBooleanEnum.1') : __d('enumeration', 'SetBooleanEnum.0'),
                                    $generateLink,
                                    labelIsTextOnly: $labelIsTextOnly);
  
  print $this->Field->control('status', ['empty' => false]);
  
  print $this->Field->dateControl('valid_from');
  
  print $this->Field->dateControl('valid_through');
  
  print $this->Field->control('remote_ip');
  
  print $this->Field->control('privileged');
}
