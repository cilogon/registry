<?php
/**
 * COmanage Registry Petition Fields
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
 *
 * This view uses only navigation config in the fields.inc file. For the 'view' action, the controller
 * sets $vv_formless_template and points to 'petition.inc' which is included instead of a standard form. 
 */ 

// Top Links
$topLinks = [
  // Note we don't need an 'if' test for cancel or resume because the generalized permission
  // calculation will call isReadOnly() (and not insert these items when true) which is sufficient.
  [
    'icon'  => 'cancel',
    'order' => 'Default',
    // We localize the text as "terminate" to make the confirmation dialog less confusing
    'label' => __d('operation', 'terminate'),
    'link'  => [
      'action' => 'terminate',
      $vv_obj->id
    ],
    'confirm' => [
      'dg_body_txt' => __d('operation', 'Petitions.terminate.confirm', [$vv_obj->id]),
      'dg_confirm_btn' => __d('operation', 'terminate')
    ]
  ],
  [
    'icon'  => 'resume',
    'order' => 'Default',
    'label' => __d('operation', 'resume'),
    'link'  => [
      'action' => 'resume',
      $vv_obj->id
    ]
  ]
]; 
