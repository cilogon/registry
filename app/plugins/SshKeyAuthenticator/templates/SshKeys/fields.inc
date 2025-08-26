<?php
/**
 * COmanage Registry SSH Keys Fields
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

use App\Lib\Util\StringUtilities;

if($vv_action == 'add') {
  // Note for add this file is called by our local add.php file, not add-edit-view.php
  $vv_submit_button_label = __d('operation', 'upload');

  $hidden = [
    // Normally add-edit-view.inc would inject the primary link as a hidden attribute,
    // but since we're not using that we need to do it manually
    StringUtilities::entityToForeignKey($vv_primary_link_obj) => $vv_primary_link_obj->id,
    'person_id' => $vv_person_id
  ];

  // As of v3.2.0, we only allow uploading of SSH Keys, not manually adding or editing

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'keyFile',
      'fieldOptions' => ['type' => 'file']
    ]
  ]);
} elseif($vv_action == 'view') {
  foreach([ 'type',
            'comment',
            'skey',
          ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
      ]
    ]);
  }
}