<?php

/**
 * COmanage Registry Transmogrify Command / Action Code Mapper Trait
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace Transmogrify\Lib\Traits;

use SshKeyAuthenticator\Lib\Enum\SshKeyActionEnum;

trait ActionCodeMapperTrait
{
  /**
   * v4 ActionEnum codes that map directly to v5 ActionEnum codes (same code).
   *
   * Keys are v4 right-hand codes, values are v5 right-hand codes.
   *
   * @var array<string, string>
   */
  protected const ACTION_CODE_DIRECT_MAP = [
    // Authenticators
    'EAUT' => 'EAUT', // AuthenticatorEdited

    // Comments
    'CMNT' => 'CMNT', // CommentAdded

    // Email
    'EMLV' => 'EMLV', // EmailAddressVerified -> EmailVerified
    'EMLS' => 'EMLS', // EmailAddressVerifyReqSent -> EmailVerifyCodeSent

    // External Identity / Login env
    'EOIE' => 'EOIE', // OrgIdEditedLoginEnv -> ExternalIdentityLoginUpdate

    // Groups
    'ACGR' => 'ACGR', // CoGroupAdded -> GroupAdded
    'DCGR' => 'DCGR', // CoGroupDeleted -> GroupDeleted
    'ECGR' => 'ECGR', // CoGroupEdited -> GroupEdited

    // Group Members
    'ACGM' => 'ACGM', // CoGroupMemberAdded -> GroupMemberAdded
    'DCGM' => 'DCGM', // CoGroupMemberDeleted -> GroupMemberDeleted
    'ECGM' => 'ECGM', // CoGroupMemberEdited -> GroupMemberEdited

    // Identifiers / Matching
    'AIDA' => 'AIDA', // IdentifierAutoAssigned
    'UMAT' => 'UMAT', // MatchAttributesUpdated

    // Names
    'PNAM' => 'PNAM', // NamePrimary

    // Notifications
    'NOTA' => 'NOTA', // NotificationAcknowledged
    'NOTX' => 'NOTX', // NotificationCanceled
    'NOTD' => 'NOTD', // NotificationDelivered
    'NOTR' => 'NOTR', // NotificationResolved

    // Person pipeline
    'ACPP' => 'ACPP', // CoPersonAddedPetition -> PersonAddedPetition
    'ACPL' => 'ACPL', // CoPersonAddedPipeline -> PersonAddedPipeline
    'MCPL' => 'MCPL', // CoPersonMatchedPipeline -> PersonMatchedPipeline

    // Person status
    'RCPS' => 'RCPS', // CoPersonStatusRecalculated -> PersonStatusRecalculated

    // Petitions
    'CPPC' => 'CPPC', // CoPetitionCreated -> PetitionCreated
    'CPUP' => 'CPUP', // CoPetitionUpdated -> PetitionUpdated

    // Reference ID
    'OIDR' => 'OIDR', // ReferenceIdentifierObtained
  ];

  /**
   * v4 codes that map to v5 ActionEnum with a changed code.
   *
   * @var array<string, string>
   */
  protected const ACTION_CODE_RENAMED_MAP = [
    // CoPersonRoleRelinked (LCRM) -> PersonRoleRelinked (LCPR)
    'LCRM' => 'LCPR',
  ];

  /**
   * v4 ActionEnum codes that moved to PetitionActionEnum in v5.
   *
   * Keys are v4 codes, values are v5 PetitionActionEnum codes.
   *
   * @var array<string, string>
   */
  protected const ACTION_CODE_PETITION_MAP = [
    // Invitations moved into PetitionActionEnum
    'INVC' => 'IC', // InvitationConfirmed -> Accepted
    'INVD' => 'PX', // InvitationDeclined -> Declined
    'INVV' => 'IV', // InvitationViewed -> InvitationViewed
    // 'INVE' => null, // InvitationExpired (no explicit equivalent)
    // 'INVS' => null, // InvitationSent (no explicit equivalent)
  ];

  /**
   * Optional/opinionated mappings to collapse specific attribute events (names)
   * into v5’s generic MVEA* events. Disabled by default for correctness.
   *
   * @var array<string, string>
   */
  protected const ACTION_CODE_OPTIONAL_OPINIONATED_MAP = [
    // Names -> generic Multi-Valued Extended Attribute events
    'ANAM' => 'AMVE', // NameAdded    -> MVEAAdded
    'ENAM' => 'EMVE', // NameEdited   -> MVEAEdited
    'DNAM' => 'DMVE', // NameDeleted  -> MVEADeleted
  ];

  /**
   * Legacy SSH key history actions that should be normalized to SSHU.
   *
   * Keys are incoming v4 history action codes, values are the v5 code.
   *
   * @var array<string,string>
   */
  protected const HISTORY_ACTION_SSH_MAP = [
    // Legacy SSH key events that no longer exist as separate actions
    'SSHA'                         => SshKeyActionEnum::SshKeyUploaded, // Added -> Uploaded
    'SSHE'                         => SshKeyActionEnum::SshKeyUploaded, // Edited -> Uploaded
  ];


  /**
   * Map a v4 ActionEnum right-hand code to v5.
   *
   * Returns:
   * - enum: 'ActionEnum' | 'PetitionActionEnum' | 'SshKeyActionEnum' | null
   * - code: string|null
   *
   * When enum is null, there is no v5 equivalent; callers can log/skip.
   *
   * @param string $v4Code
   * @param bool   $enableOpinionated Enable optional generalized mappings (default=false)
   * @return array{enum: string|null, code: string|null}
   */
  protected function mapActionCode(string $v4Code, bool $enableOpinionated = false): array
  {
    $key = strtoupper(trim($v4Code));

    if ($key === '') {
      return ['enum' => null, 'code' => null];
    }

    // 1) Direct ActionEnum mappings (same code)
    if (isset(self::ACTION_CODE_DIRECT_MAP[$key])) {
      return ['enum' => 'ActionEnum', 'code' => self::ACTION_CODE_DIRECT_MAP[$key]];
    }

    // 2) Renamed ActionEnum mappings
    if (isset(self::ACTION_CODE_RENAMED_MAP[$key])) {
      return ['enum' => 'ActionEnum', 'code' => self::ACTION_CODE_RENAMED_MAP[$key]];
    }

    // 3) PetitionActionEnum mappings
    if (isset(self::ACTION_CODE_PETITION_MAP[$key])) {
      return ['enum' => 'PetitionActionEnum', 'code' => self::ACTION_CODE_PETITION_MAP[$key]];
    }

    // 4) Optional/opinionated ActionEnum mappings
    if ($enableOpinionated && isset(self::ACTION_CODE_OPTIONAL_OPINIONATED_MAP[$key])) {
      return ['enum' => 'ActionEnum', 'code' => self::ACTION_CODE_OPTIONAL_OPINIONATED_MAP[$key]];
    }

    // 5) Legacy SSH key actions (SSHA/SSHE) normalized to SSHU in SshKeyActionEnum
    if (isset(self::HISTORY_ACTION_SSH_MAP[$key])) {
      return ['enum' => 'SshKeyActionEnum', 'code' => self::HISTORY_ACTION_SSH_MAP[$key]];
    }

    // No known mapping
    return ['enum' => null, 'code' => null];
  }


  /**
   * Convenience: map from a row array. Tries 'action' first, then 'action_code'.
   *
   * @param array $row
   * @param bool  $enableOpinionated
   * @return array{enum: string|null, code: string|null}
   */
  protected function mapActionFromRow(array $row, bool $enableOpinionated = false): array
  {
    $code = null;

    if (isset($row['action']) && is_string($row['action'])) {
      $code = $row['action'];
    }

    if ($code === null) {
      return ['enum' => null, 'code' => null];
    }

    return $this->mapActionCode($code, $enableOpinionated);
  }


  /**
   * Map an SSH key history action to the current action code.
   *
   * Uses mapActionCode() so that legacy actions are normalized.
   * For all other actions, returns the original action value unchanged.
   *
   * @param array $row Row data containing an 'action' key
   * @return string|null Mapped action code or null if not set
   */
  protected function mapHistoryAction(array $row): ?string
  {
    if (!isset($row['action']) || !is_string($row['action'])) {
      return null;
    }

    $action = (string)$row['action'];

    // Delegate to the generic mapper
    $mapped = $this->mapActionCode($action);

    // If this is one of the SSH key legacy actions, use the mapped SSH key code
    if ($mapped['enum'] !== null && $mapped['code'] !== null) {
      return $mapped['code'];
    }

    // Otherwise, return the original action unchanged
    return $action;
  }


  /**
   * Map a cm_co_notifications row’s action code to a v5 ActionEnum code.
   * For the notifications we only accept ActionEnum; PetitionActionEnum mappings return null.
   */
  protected function mapNotificationAction(array $row): ?string
  {
    $m = $this->mapActionFromRow($row);

    if ($m['enum'] === 'ActionEnum') {
      return $m['code'];
    }

    // No ActionEnum equivalent (eg, invitation events that moved to PetitionActionEnum)
    $rawCode = null;
    if (isset($row['action']) && is_string($row['action'])) {
      $rawCode = $row['action'];
    }

    if ($rawCode !== null && isset($this->cmdPrinter)) {
      $unmapped = $this->listUnmappedActionCodes([$rawCode], false);
      if (!empty($unmapped)) {
        $this->cmdPrinter->warning(sprintf('Skipping notification with unmapped action code: %s', $unmapped[0]));
      }
    }

    return null;
  }

  /**
   * Report which v4 action codes won’t map under current settings.
   *
   * @param string[] $seenV4Codes
   * @param bool     $enableOpinionated
   * @return string[]
   */
  protected function listUnmappedActionCodes(array $seenV4Codes, bool $enableOpinionated = false): array
  {
    $unmapped = [];

    foreach ($seenV4Codes as $c) {
      $m = $this->mapActionCode((string)$c, $enableOpinionated);
      if ($m['enum'] === null) {
        $unmapped[] = strtoupper(trim((string)$c));
      }
    }

    return array_values(array_unique($unmapped));
  }
}
