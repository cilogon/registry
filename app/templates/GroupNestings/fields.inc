<?php
/**
 * COmanage Registry Group Nestings Fields
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

if($vv_action == 'add') {
  print $this->Field->control('target_group_id');
} elseif($vv_action == 'edit') {
  // The target group can't be changed after adding
  print $this->Field->statusControl('target_group_id',
                                    $vv_obj->target_group->name, 
                                    ['url' => ['controller' => 'groups',
                                               'action'     => 'edit',
                                               $vv_obj->target_group_id]]);
}
  
if($vv_action == 'add' || $vv_action == 'edit') {
  print $this->Field->statusControl('group_id',
                                    $vv_bc_parent_obj->name, 
                                    ['url' => ['controller' => 'groups',
                                               'action'     => 'edit',
                                               $vv_bc_parent_obj->id]]);
  
  print $this->Field->control('negate');
}