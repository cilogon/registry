<?php
/**
 * COmanage Registry Status Enum
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

namespace App\Lib\Enum;

class StatusEnum extends StandardEnum {
  const Active              = 'A';
  const Approved            = 'Y';
  // Archived was Deleted in v4, so we reuse "D" to simplify upgrading
  const Archived            = 'D';
  const Confirmed           = 'C';
  const Denied              = 'N';
  const Duplicate           = 'D2';
  const Expired             = 'XP';
  const GracePeriod         = 'GP';
  const Invited             = 'I';
  const Locked              = 'LK';
  const Pending             = 'P';
  const PendingActivation   = 'PS';
  const PendingApproval     = 'PA';
  const PendingConfirmation = 'PC';
  const Suspended           = 'S';
  const Declined            = 'X';

  /**
   * Map a status value to its "preference" or "rank" for status recalculation.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $status StatusEnum
   * @return int            Preference Rank (larger numbers are more preferred)
   * @throws InvalidArgumentException
   */

  public static function rank(string $status): int {
    // We rank status by "preference". More "preferred" statuses rank higher.
    // To facilitate comparison, we'll convert the status to an integer value.
    // Most preferred numbers are larger so we can say things like
    // Active > Expired.

    // Note a similar chart is defined in ExternalIdentityStatusEnum.

    $statusRanks = array(
      // Active statuses are most preferred
      self::Active                => 15,
      self::GracePeriod           => 14,

      // Next come expired statuses, since there may be provisioned skeletal records
      // that need to be maintained
      self::Suspended             => 13,
      self::Expired               => 12,

      // Then invitation statuses
      self::Approved              => 11,
      self::PendingApproval       => 10,
      self::Confirmed             => 9,
      self::PendingConfirmation   => 8,
      self::Invited               => 7,
      self::PendingActivation     => 6,
      self::Pending               => 5,  // It's not clear this is used for anything

      // Denied and Declined are below expired since other roles are more likely to have been used
      self::Denied                => 4,
      self::Declined              => 3,

      // Finally, we generally don't want Archived or Duplicate unless all roles are deleted or duplicates
      self::Archived              => 2,
      self::Duplicate             => 1
    );

    if($status == self::Locked) {
      // Locked status should only apply to the Person and not Person Roles, so it
      // shouldn't be valid for ranking.

      throw new \InvalidArgumentException("Cannot calculate Rank for Locked status");
    }

    if(!isset($statusRanks[$status])) {
      throw new \InvalidArgumentException("Invalid status $status");
    }

    return $statusRanks[$status];
  }
}