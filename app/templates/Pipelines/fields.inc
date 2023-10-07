<?php
/**
 * COmanage Registry Pipelines Fields
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
?>
<?php
// This view does not support read-only
if($vv_action == 'add' || $vv_action == 'edit') {
  print $this->Field->control('description');

  print $this->Field->control('status');
  
  // Match Strategy
  print $this->Field->control('match_strategy');

    // print $this->Field->control('match_type_id');

    // print $this->Field->control('match_server_id');

  // Sync Strategy
//  print $this->Field->control('sync_on_update');

//  print $this->Field->control('sync_on_delete');

  print $this->Field->control('sync_status_on_delete', ['empty' => false]);

  print $this->Field->control('sync_cou_id');

  print $this->Field->control('sync_replace_cou_id');

  print $this->Field->control('sync_affiliation_type_id');

  print $this->Field->control('sync_identifier_type_id');

  // Connections
//XXX
}
