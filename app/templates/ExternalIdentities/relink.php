<?php
/**
 * COmanage Registry External Identity Link View
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);
?>

<div class="page-title-container">
  <div class="page-title">
    <h2><?= $vv_title ?></h2>
  </div>
</div>

<?= $this->element('flash') // Flash messages ?>

<?php
  print $this->Form->create(null, [
    'id'   => 'ei-relink-form',
    'type' => 'post'
  ]);

  print $this->Form->hidden('external_identity_id', ['default' => $vv_external_identity->id]);
// This label isn't localized, but it should go away when the UX is fixed
  print $this->Form->control('target_person_id', ['type' => 'integer', 'label' => 'Target Person ID']);

  print $this->Form->submit();

  print $this->Form->end();