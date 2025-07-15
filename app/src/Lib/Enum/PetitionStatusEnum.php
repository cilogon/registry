<?php
/**
 * COmanage Registry Petition Status Enum
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

class PetitionStatusEnum extends StandardEnum {
  // Accepted replaces "Confirmed" from v4
  const Accepted            = 'C';
  const Active              = 'A';
  const Approved            = 'Y';
  const Created             = 'CR';
  const Declined            = 'X';
  const Denied              = 'N';
  const Duplicate           = 'D2';
  const Failed              = 'XX';
  const Finalized           = 'F';
  const Finalizing          = 'FI';
  const PendingAcceptance   = 'PC';
  const PendingApproval     = 'PA';
  const PendingVerification = 'PE';
  const PendingVetting      = 'PV';
  const Terminated          = 'CX';
  const Verified            = 'VE';
  const Vetted              = 'VT';
}