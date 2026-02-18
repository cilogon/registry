<?php
/*
 * COmanage Registry Duplicatable Element
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

/* We currently support cloning related metadata, which comes in two flavors:
 *  (1) Clonable Objects have uuid, cri, and do_not_clone attributes.
 *  (2) Related Objects have originalid (if multivalued).
 * 
 * We use annotations on the entity to determine what to render, this approach can be
 * expanded for future metadata types.
 */

if(isset($vv_obj->_supportedMetadata)) {
  $controls = [];

  if(in_array('clonable', $vv_obj->_supportedMetadata)) {
    $controls = array_merge($controls, [
      'uuid' => [
        'singleRowItem' => true,
        'label' => __d('field','uuid'),
        'type' => 'text'
      ],
      'cri' => [
        'singleRowItem' => true,
        'label' => __d('field','cri')
      ],
      'do_not_clone' => [
        'singleRowItem' => true,
        'label' => __d('field','do_not_clone')
      ]
    ]);
  }

  if(in_array('clonable-related', $vv_obj->_supportedMetadata)) {
    // originalid is a foreign key, but possibly not to the current CMP. As such, we don't
    // try to make it linkable to anything, and as a first pass we make it read only.
    $controls = array_merge($controls, [
      'originalid' => [
        'singleRowItem' => true,
        'label' => __d('field','originalid'),
        'type' => 'text',
        'readonly' => true
      ]
    ]);
  }

  return [
    'metadata' => [
      'fieldLabel' => __d('field','metadata'),
      'labelIsTextOnly' => true, 
      'groupedControls' => $controls
    ]
  ];
} else {
  return [];
}