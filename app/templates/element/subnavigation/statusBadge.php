<?php
/**
 * COmanage Registry Status badge Subnav Element
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


declare(strict_types = 1);

extract($vv_sub_nav_attributes, EXTR_PREFIX_ALL, 'vv_subnavigation');
$curController = $this->request->getParam('controller');

if(!in_array('People', $vv_subnavigation_tabs, true)) {
  return;
}

$personId = $vv_mvea_person_id
  ?? $this->request->getQuery('person_id')
  ?? $vv_obj?->person_id
  ?? $vv_obj?->id;

$status = $this->Tab->getPersonStatus((int)$personId);

$statusBadgeClass = match ($status) {
  'A' => 'bg-outline-secondary primary',
  'D','N','S','X','XP' => 'bg-danger',
  default => 'bg-warning'
};

?>

<span class="person-status-badge mr-1 badge <?= $statusBadgeClass ?>">
  <?= __d('enumeration', 'StatusEnum.' . $status) ?>
</span>
