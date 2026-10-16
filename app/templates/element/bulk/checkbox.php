<?php
/**
 * COmanage Registry Bulk Action From Checkbox
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


/*
 * Parameters:
 * $entity                 : object, optional
 * $label                  : string, required
 */

declare(strict_types = 1);

use App\Lib\Enum\PetitionStatusEnum;

// Set up defaults
$readOnly = false;
$permissionSet = $vv_permission_set ?? null;

if(empty($entity)) {
  $id = 'bulk-action-select-all';
} else {
  $id = "bulk-action-id-{$entity->id}";
  
  // Special handling for specific models
  // GROUP MEMBERS
  if ($entity instanceof \App\Model\Entity\GroupMember) {
    // If the member came from a Group Nesting, currently no bulk actions (just delete) are allowed 
    if(isset($entity->group_nesting)) {
      $readOnly = true;
    }
  }
  // PETITIONS
  if ($entity instanceof \App\Model\Entity\Petition) {
    // Currently, we support bulk termination of Petitions.
    // A petition can be terminated only if it is not in a completed status.
    // Also cross-reference the $permissionsSet for the entity to see if termination is disallowed.
    if(in_array($entity->status, PetitionStatusEnum::getCompleted(), true) 
       || ($permissionSet[$entity->id]['terminate'] ?? null) === false) {
      $readOnly = true;
    }
  }
}

?>

<div class="form-check bulk-action-checkbox-container">
  <input class="form-check-input"
         type="checkbox"
         value=""
         id="<?= $id ?>"
         <?= $readOnly ? "disabled" : "" ?>
         <?php if(!empty($entity)):?>
         data-entity="<?= htmlspecialchars(json_encode($entity), ENT_QUOTES, 'UTF-8') ?>"
         data-entity-id="<?= $entity->id ?>"
         <?php endif ?>>
  <label class="form-check-label" for="<?= $id ?>">
    <?= $label ?>
    <?= $readOnly ? '<em class="material-symbols-outlined read-only-icon">edit_off</em>' : '' ?>
  </label>
</div>