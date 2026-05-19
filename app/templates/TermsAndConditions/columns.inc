<?php
/**
 * COmanage Registry Terms And Conditions Index Columns
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

$indexColumns = [
  'description' => [
    'type' => 'link',
    'sortable' => true
  ],
  'status' => [
    'type' => 'enum',
    'class' => 'SuspendableStatusEnum',
    'sortable' => true
  ],
  'ordr' => [
    'type' => 'echo',
    'sortable' => true
  ],
  'cou_id' => [
    'type' => 'fk',
    'sortable' => true
  ]
];

$rowActions = [
  [
    'icon'  => 'arrow_outward',
    'label' => __d('operation', 'visit.tandc'),
    'callbackUrl' => function($entity) use ($vv_base_url) {
      // There should always be either a Mostly Static Page or a URL to reference. 
      if(!empty($entity->mostly_static_page->name)) {
        // The T&C is a mostly static page. Provide a link to it.
        return $vv_base_url . '/' . $entity->mostly_static_page->name;
      }
      return $entity->url;
    }
  ]
];
