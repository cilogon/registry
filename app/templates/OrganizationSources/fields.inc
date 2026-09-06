<?php
/**
 * COmanage Registry Organization Sources Fields
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

$fields = [
  'description',
  'status' => [
    'default'  => \App\Lib\Enum\OrgSyncModeEnum::Manual
  ],
  'plugin'
];

$subnav = 'os';
      
// Top Links
$topLinks = [
  [
    'icon' => 'search',
    'order' => 'Default',
    // XXX unclear why we can't just use "search", but leaving this for now for consistency
    //     with ExternalIdentitySources
    'label' => __d('operation', 'OrganizationSources.search'),
    'link' => [
      'action' => 'search',
      $vv_obj->id
    ],
    'class' => ''
  ]
];