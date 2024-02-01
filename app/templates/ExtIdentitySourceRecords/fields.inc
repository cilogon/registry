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

print $this->Alert->alert($noticeText, 'information', false);

// This view does not support add or edit
if($vv_action == 'view') {
// Link to External Identity, if known

  print $this->Field->control('source_key');

  print $this->Field->control('last_update');

  print $this->Field->control('reference_identifier');

  print $this->Field->control(fieldName: 'source_record', cssClass: 'source-record');
}
