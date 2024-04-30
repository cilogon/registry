<?php
/**
 * COmanage Registry Banner List Element
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

/*
 * Parameters:
 * $type          : String,  required
 * $message       : String,  required
 * $dismissible   : Boolean, optional
 */

$type ??= 'warning';

$alertClass = "alert-{$type}";
$showButton = false;

if(isset($dismissible) && $dismissible) {
  $alertClass .= ' alert-dismissible';
  $showButton = true;
}
?>

<div class="alert <?= $alertClass ?> co-alert" role="alert">
  <div class="alert-body d-flex align-items-center">
    <span class="alert-title d-flex align-items-center">
      <span class="material-icons-outlined alert-icon"><?= $this->Alert->getAlertIcon($type) ?></span>
      <?php if(isset($title)): ?>
      <span class="alert-title-text"><?= $title ?></span>
      <?php endif; ?>
    </span>
    <span class="alert-message">
      <?= $message ?>
    </span>
    <?php
      if($showButton) {
        print $this->element('notify/closeButton');
      }
    ?>
  </div>
</div>
