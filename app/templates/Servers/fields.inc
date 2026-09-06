<?php
/**
 * COmanage Registry Servers Fields
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

$fields = [
  'description',
  'status',
  'plugin' => [
    'labelIsTextOnly' => true
  ]
];

$fields = array_merge($fields, include(ROOT . DS . 'templates' . DS . 'Standard/metadata.inc'));

// Match Servers have special subnavigation treatment (they have an extra tab for Match Server Attributes).
if($vv_obj['plugin'] == 'CoreServer.MatchServers') {
  $subnav = [
    'tabs' => ['Servers', 'Servers.Plugin', 'CoreServer.MatchServerAttributes'],
    'action' => [
      'Servers' => ['edit'],
      'Servers.Plugin' => ['edit'],
      'CoreServer.MatchServerAttributes' => ['index']
    ]
  ];
} else {
  // Otherwise, servers get the standard subnavigation treatment.
  $subnav = 'standard';  
}

// List the MVEAs that may be shown on the mveaCanvas
// When this array exists, the mveaCanvas.php element will render  
$mveas = [
  'identifiers'
];

// Name the MVEAs Entity Type
$mveasEntityType = "server";

// $addMenuLinks is also given slightly different treatment from the typical $topLinks found in most views: 
// it is a page-global menu used for adding MVEAs and is given special treatment in element/mveaCanvas.php.
$addMenuLinks = [
  [
    'controller' => 'identifiers',
    'action' => 'add',
    'icon' => 'fingerprint'
  ]
];
