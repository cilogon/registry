<?php
/**
 * COmanage Registry Action Enum
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

class ActionEnum extends StandardEnum {
  // Codes beginning with 'X' (eg: 'XABC') are reserved for local use
  // Codes beginning with a lowercase 'p' (eg: 'pABC') are reserved for plugin use
  const CommentAdded                  = 'CMNT';
  const EmailForceVerified            = 'EMFV';
  const EmailVerified                 = 'EMLV';
  const EmailVerifyCodeSent           = 'EMLS';
  const ExternalIdentityAdopted       = 'EOIA';
  const ExternalIdentityLoginUpdate   = 'EOIE';
  const ExternalIdentityRelinked      = 'LEOI';
  const GroupAdded                    = 'ACGR';
  const GroupDeleted                  = 'DCGR';
  const GroupEdited                   = 'ECGR';
  const GroupMemberAdded              = 'ACGM';
  const GroupMemberDeleted            = 'DCGM';
  const GroupMemberEdited             = 'ECGM';
  const GroupOwnerAdded               = 'ACGO';
  const GroupOwnerDeleted             = 'DCGO';
  const IdentifierAutoAssigned        = 'AIDA';
  const MVEAAdded                     = 'AMVE';
  const MVEADeleted                   = 'DMVE';
  const MVEAEdited                    = 'EMVE';
  const NamePrimary                   = 'PNAM';
  const NotificationAcknowledged      = 'NOTA';
  const NotificationCanceled          = 'NOTX';
  const NotificationDelivered         = 'NOTD';
  const NotificationResolved          = 'NOTR';
  const PersonAddedPetition           = 'ACPP';
  const PersonAddedPipeline           = 'ACPL';
  const PersonMatchedPipeline         = 'MCPL';
  const PersonPipelineComplete        = 'CCPL';
  const PersonPipelineStarted         = 'SCPL';
  const PersonRoleRelinked            = 'LCPR';
  const PersonStatusRecalculated      = 'RCPS';
}