<?php
/**
 * COmanage Registry Standard JSON Index View
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

// We use this template for both /view (1 record) and /index (n records)
$action = $this->template;

$responseMeta = [
  'resource' => $vv_model_name,
  'version' => '2'
];

if($action == 'index') {
  $responseMeta['totalResults'] = $this->Paginator->counter('{{count}}');
  $responseMeta['startIndex'] = $this->Paginator->counter('{{start}}');
  $responseMeta['itemsPerPage'] = $this->Paginator->counter('{{current}}'); // confusingly this is different than ->current()
  $responseMeta['currentPage'] = $this->Paginator->current();
  $responseMeta['pageCount'] = $this->Paginator->total();
}

$metaAttrs = ['created', 'modified', 'revision', 'deleted', 'actor_identifier'];

// Inflect the table name to get the changelog parent record key
$pkey = \Cake\Utility\Inflector::singularize($vv_table_name) . "_id";
$metaAttrs[] = $pkey;

$results = [];

foreach($$vv_table_name as $r) {
  $rec = $r;
  $meta = [];
  
  foreach($metaAttrs as $a) {
    $meta[$a] = $rec[$a];
    unset($rec[$a]);
  }
  
  $rec['meta'] = $meta;
  $results[] = $rec;
}

print json_encode(["responseMeta" => $responseMeta, $vv_model_name => $results]);