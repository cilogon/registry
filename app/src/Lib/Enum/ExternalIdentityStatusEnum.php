<?php
/**
 * COmanage Registry External Identity Status Enum
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

class ExternalIdentityStatusEnum extends StandardEnum {
  const Active              = 'A';
  const Archived            = 'D';
  const Deleted             = 'X';
  const Duplicate           = 'D2';
  const GracePeriod         = 'GP';
  const Suspended           = 'S';

  /**
   * Map a status value to its "preference" or "rank" for status recalculation.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $status ExternalIdentityStatusEnum
   * @return int            Preference Rank (larger numbers are more preferred)
   * @throws InvalidArgumentException
   */

  public static function rank(string $status): int {
    // This is basically a subset of StatusEnum::rank().

    $statusRanks = array(
      // Active statuses are most preferred
      self::Active                => 14,
      self::GracePeriod           => 13,

      // Next come expired statuses, since there may be provisioned skeletal records
      // that need to be maintained
      self::Suspended             => 12,

      // Finally, we generally don't want Deleted or Duplicate unless all roles are deleted or duplicates
      self::Archived              => 2,
      // "Deleted" is managed by Registry, not the EIS backend, but we'll basically treat
      // it the same as Archived
      self::Deleted               => 2,
      self::Duplicate             => 1
    );
    
    if(!isset($statusRanks[$status])) {
      throw new \InvalidArgumentException("Invalid status $status");
    }

    return $statusRanks[$status];
  }
}