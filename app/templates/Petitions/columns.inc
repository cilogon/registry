<?php
/**
 * COmanage Registry Petitions Index Columns
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

$indexColumns = [
  // We start with id to ensure there is a clickable field in the row
  'id' => [
    'type' => 'link',
    'labelPrefix' => __d('controller','Petitions',[1])
  ],
  'status' => [
    'type' => 'enum',
    'class' => 'PetitionStatusEnum',
    'sortable' => true
  ],
  'enrollee_person_id' => [
    'type' => 'relatedLink',
    'action' => 'edit',
    'label' => __d('field', 'Petitions.enrollee_person_id'),
    'model' => 'enrollee_person',
    'submodel' => 'primary_name',
    'field' => 'full_name',
    'default' => __d('field', 'Petitions.enrollee.new')
  ],
  'enrollee_email' => [
    'type' => 'echo'
  ],
  'enrollment_flow_id' => [
    'type' => 'relatedLink',
    'model' => 'enrollment_flow',
    'field' => 'name'
  ],
  'cou_id' => [
    'type' => 'relatedLink',
    'model' => 'cou',
    'field' => 'name'
  ],
/* XXX this apparently isn't in the data model yet?
  'petitioner_person_id' => [
    'type' => 'relatedLink',
    'action' => 'edit',
//    'label' => __d('field', 'actor'),
    'model' => 'petitioner_person',
    'submodel' => 'primary_name',
    'field' => 'full_name'
  ],*/
// sponsor
// approver - there can be multiple approvers going forward, maybe omit this column
  'created' => [
    'type' => 'datetime'
  ],
  'modified' => [
    'type' => 'datetime'
  ]
];

$subnav = 'petitions';

// $rowActions appear as row-level menu items in the index view gear icon
$rowActions = [
  [
    'controller'  => 'petitions',
    'action'      => 'view',
    'class'       => '',
    'icon'        => 'visibility',
    'iconClass'   => 'material-symbols-outlined',
    'label'       => __d('operation', 'view')
  ],
  [
    'controller'  => 'petitions',
    'action'      => 'resume',
    'class'       => '',
    'icon'        => 'resume',
    'label'       => __d('operation', 'resume'),
    'if'          => 'isResumable'
  ]
];