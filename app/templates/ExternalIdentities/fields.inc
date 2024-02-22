<?php
/**
 * COmanage Registry External Identity Fields
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

// This view will not support add/edit
if($vv_action == 'add' || $vv_action == 'edit' || $vv_action == 'view') {
  if(!empty($vv_obj->ext_identity_source_record)) {

    $noticeText = __d(
      'information',
      'ExternalIdentities.source',
      [
        $this->Html->link(
          $vv_obj->ext_identity_source_record->external_identity_source->description,
          [
            'controller' => 'external-identity-sources',
            'action' => 'edit',
            $vv_obj->ext_identity_source_record->external_identity_source->id
          ]
        )
      ]
    );
    
    print $this->Alert->alert($noticeText, 'information', false);
  }

  print $this->Field->control('status', ['empty' => false]);
  
  print $this->Field->dateControl('date_of_birth', \App\Lib\Enum\DateTypeEnum::DateOnly);
}
