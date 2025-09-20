<?php
/**
 * COmanage Registry Petition Entity
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

namespace App\Model\Entity;

use Cake\ORM\Entity;
use \App\Lib\Enum\EnrollmentActorEnum;
use \App\Lib\Enum\PetitionStatusEnum;

class Petition extends Entity {
  use \App\Lib\Traits\EntityMetaTrait {
    isReadOnly as traitIsReadOnly;
  }
  
  protected array $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false, 
  ];

  /**
   * Determine if this Petition is complete, ie in a state where no further changes
   * are permitted.
   * 
   * @since  COmanage Registry v5.0.0
   * @return bool     True if this Petition is complete, false otherwise
   */

  public function isComplete(): bool {
    return in_array($this->status, [
      PetitionStatusEnum::Declined,
      PetitionStatusEnum::Denied,
      PetitionStatusEnum::Duplicate,
      PetitionStatusEnum::Failed,
      PetitionStatusEnum::Finalized,
      // A Finalizing Petition is NOT complete
      // PetitionStatusEnum::Finalizing
      PetitionStatusEnum::Terminated
    ]);
  }

  /**
   * Determine if this Petition can be resumed, ie: is not complete.
   * 
   * @since  COmanage Registry v5.0.0
   * @return bool     True if this Petition can be resumed, false otherwise
   */
  
  public function isResumable(): bool {
    return !$this->isComplete();
  }

  /**
   * Determine if this entity is Read Only.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity Cake Entity
   * @return bool            true if the entity is read only, false otherwise
   */

  public function isReadOnly(): bool {
    // Completed petitions are read only, along with the usual stuff
    
    return $this->isComplete() || $this->traitIsReadOnly();
  }

  /**
   * Determine whether or not a token should be used to authenticate this Petition
   * for the requested Actor Role. Note token validation is NOT handled by this call.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  EnrollmentActorEnum $actorRole Requested Actor Role
   * @return bool                           true if a token should be used, false otherwise
   */

  public function useToken(string $actorRole): bool {
    // A token should be used when the current role does not have an associated
    // authenticated identifier or person ID
    if(($actorRole == EnrollmentActorEnum::Petitioner
        && empty($this->petitioner_identifier)
        && empty($this->petitioner_person_id))
        ||
        ($actorRole == EnrollmentActorEnum::Enrollee
        && empty($this->enrollee_identifier)
        // Once finalization begins, we'll have an enrollee_person_id but they
        // most likel won't be able to authenticate
        && (empty($this->enrollee_person_id) || $this->status == PetitionStatusEnum::Finalizing))) {
      // Note presence of a token is not an indicator as to whether a token should be used
      return true;
    }

    return false;
  }
}