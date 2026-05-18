<?php
/**
 * COmanage Registry Transmogrify Command / Type Mapper Trait
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

use App\Lib\Enum\PetitionStatusEnum;
use App\Lib\Enum\SyncModeEnum;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Transmogrify\Lib\Util\RawSqlQueries;
use App\Lib\Enum\MatchStrategyEnum;

/**
 * Encapsulates all type mapping logic and helpers (map_type + specific wrappers).
 *
 * @since  COmanage Registry v5.2.0
 */
trait TypeMapperTrait
{
  use CacheTrait;

  /**
   * Map server type codes to plugin paths
   *
   * HT = HTTP Server
   * KA = Kafka Server
   * KC = KDC Server
   * LD = LDAP Server
   * MT = Match Server
   * O2 = OAuth2 Server
   * SQ = SQL Server
   *
   * @var array<string,string>
   */

  public const SERVER_TYPE_MAP = [
    'HT' => 'CoreServer.HttpServers',
    'KA' => 'CoreServer.KafkaServers',
    'KC' => 'CoreServer.KdcServers',
    'LD' => 'CoreServer.LdapServers',
    'MT' => 'CoreServer.MatchServers',
    'O2' => 'CoreServer.Oauth2Servers',
    'SQ' => 'CoreServer.SqlServers',
  ];

  /**
   * Map MessageTemplateEnum (v4) codes to MessageTemplateContextEnum (v5) codes
   *
   * Keys are v4 right-hand codes, values are v5 right-hand codes.
   * Left-hand names are provided as context in comments.
   *
   * Notes:
   * - EnrollmentApprover (AP) does not exist in v5; the closest functional
   *   equivalent is EnrollmentHandoff (EH).
   * - ExpirationNotification (XN) is not present in v5; mapped to null.
   *
   * @var array<string, string|null>
   */
  public const MESSAGE_TEMPLATE_CONTEXT_MAP = [
    // Authenticator
    'AU' => 'AU',
    // EnrollmentApprover -> EnrollmentHandoff
    'AP' => 'EH',
    // EnrollmentApproval
    'EA' => 'EA',
    // EnrollmentFinalization
    'EF' => 'EF',
    // EnrollmentVerification -> Verification
    'EV' => 'V',
    // ExpirationNotification (not used in v5 yet)
    'XN' => null,
    // Plugin
    'PL' => 'PL',
  ];


  /**
   * Map match attribute types to corresponding model names
   *
   * @var array<string,string>
   * @since  COmanage Registry v5.2.0
   */
  public const MATCH_ATTRIBUTE_TYPE_MAP = [
    'emailAddress' => 'EmailAddresses',
    'name' => 'Names',
    'identifier' => 'Identifiers',
    'dateOfBirth' => '',
  ];


  /**
   * Find and set adopted person ID based on linked external/org identity ID
   *
   * Attempts to find the associated CO Person ID using either external_identity_id
   * or org_identity_id from either original or current row data. If found, updates
   * the co_person_id in the original row.
   *
   * @param array &$origRow Original row data by reference
   * @param array &$row Current row data by reference
   * @return void
   * @throws Exception
   * @since COmanage Registry v5.2.0
   */
  protected function findAdoptedPersonByLinkedOrgIdentityId(array &$origRow, array &$row): void
  {
    // Try to locate an external identity (or legacy org identity) id from either array
    $externalIdentityId = null;

    if (!empty($row['external_identity_id'])) {
      $externalIdentityId = (int)$row['external_identity_id'];
    } elseif (!empty($origRow['org_identity_id'])) {
      $externalIdentityId = (int)$origRow['org_identity_id'];
    } elseif (!empty($row['org_identity_id'])) {
      $externalIdentityId = (int)$row['org_identity_id'];
    }

    $personId = null;

    if ($externalIdentityId !== null) {
      $personId = $this->mapOrgIdentityCoPersonId(['id' => $externalIdentityId]);
    }


    $row['co_person_id'] = null;
    if ($personId !== null) {
      $row['co_person_id'] = $personId;
    }
  }

  /**
   * Map address type to corresponding type ID
   *
   * @param array $row Row data containing address type
   * @return int|null       Mapped type ID
   * @since  COmanage Registry v5.2.0
   */
  protected function mapAddressType(array $row): ?int
  {
    $type = 'type';
    if (isset($row['address_type'])) {
      $type = "address_type";
    }
    return $this->mapType(
      $row,
      'Addresses.type',
      $this->findCoId($row),
      $type
    );
  }

  /**
   * Map affiliation type to corresponding type ID
   *
   * @param array $row Row data containing affiliation
   * @param int|null $coId CO Id or null if not known
   * @return int|null       Mapped type ID
   * @since  COmanage Registry v5.2.0
   */
  protected function mapAffiliationType(array $row, ?int $coId = null): ?int
  {
    $type = 'affiliation';
    if (isset($row['default_affiliation'])) {
      $type = 'default_affiliation';
    } else if (isset($row['sync_affiliation'])) {
      $type = 'sync_affiliation';
    }

    // No value assigned for the affiliation, return null
    if (empty($row[$type])) {
      return null;
    }

    return $this->mapType(
      $row,
      'PersonRoles.affiliation_type',
      $coId ?? $this->findCoId($row),
      $type
    );
  }


  /**
   * Map API User ID to corresponding CO ID
   *
   * @param array $row Row data containing api_user_id
   * @return int|null  Mapped CO ID or null if not found
   * @since  COmanage Registry v5.2.0
   */
  protected function mapCoIdFromApiUserId(array $row): ?int {
    return $this->getCoIdFromApiUserId($row['api_user_id']);
  }


  /**
   * Maps organization identity source ID to API ID using cached API data.
   *
   * Looks up API ID from cached API data based on the organization identity source ID.
   * Returns null if cache is empty or mapping not found.
   *
   * @param array $row Row data containing org_identity_source_id
   * @return int|null Mapped API ID or null if not found
   * @since COmanage Registry v5.2.0
   */
  protected function mapApiIdFromCache(array $row): ?int {
    $apis = $this->cache['apis'] ?? null;

    if ($apis === null) {
      return null;
    }

    $orgIdentitySourceId = $row['org_identity_source_id'] ?? $row["external_identity_source_id"] ?? null;

    if ($orgIdentitySourceId === null) {
      null;
    }

    // [id.<api_id>.org_identity_source_id] => <org_identity_source_id>
    $flattenedApis = Hash::flatten($apis);
    $key = array_search($orgIdentitySourceId, $flattenedApis, true);

    if ($key === false) {
      return null;
    }

    $parts = explode('.', $key);
    return (int)$parts[1] ?? null;
  }


  /**
   * Generic mapper for v4 plugin names to v5 plugin model paths.
   *
   * Logic:
   * - Only process plugin names that end with the given $suffix (eg, "Source" or "Authenticator")
   * - If a plugin exists with the same name, return "Plugin.PluralTable"
   * - Else, if a "<Base>Connector" plugin exists, return "BaseConnector.PluralTable"
   *
   * @param array  $row     Row data containing 'plugin'
   * @param string $suffix  Required suffix for the plugin name (eg, "Source", "Authenticator")
   * @param string $context Human‑readable context for exception messages
   * @return string|null
   */
  protected function mapPlugin(array $row, string $suffix, string $context): ?string
  {
    $v4 = $row['plugin'] ?? null;
    if (!$v4) {
      return null;
    }

    // Only process names that end with the expected suffix
    if (!str_ends_with($v4, $suffix)) {
      return null;
    }

    $pluginsTable = TableRegistry::getTableLocator()->get('Plugins');

    $pluginRows = $pluginsTable
      ->find()
      ->select(['plugin'])
      ->where(fn($exp) => $exp->isNotNull('plugin'))
      ->distinct()
      ->disableHydration()
      ->all();

    $available = array_map(static fn(array $r) => (string)$r['plugin'], $pluginRows->toList());

    // Pluralized Table name for the model side (eg, EnvSource -> EnvSources)
    $pluralTable = Inflector::pluralize($v4);

    // 1) Direct plugin exists with the same name
    if (in_array($v4, $available, true)) {
      return $v4 . '.' . $pluralTable;
    }

    // 2) Check for "<Base>Connector"
    $base = substr($v4, 0, -strlen($suffix));
    $connector = $base . 'Connector';

    if ($base !== '' && in_array($connector, $available, true)) {
      if ($base !== '' && in_array($connector, $available, true)) {
        $mapped = $connector . '.' . $pluralTable;
        if (\Cake\Core\App::className($mapped, 'Model/Table', 'Table') !== null) {
          return $mapped;
        }
      }
    }

    // No mapping found
    throw new \InvalidArgumentException(
      "Unable to map {$context} plugin: {$v4}. Plugin not implemented in COmanage Registry v5."
    );
  }


  /**
   * Map v4 External Identity Source plugin name to v5 plugin model path.
   *
   * Examples:
   *   EnvSource           -> EnvSource.EnvSources
   *   FileSource          -> FileConnector.FileSources
   *
   * @param array $row A row from cm_org_identity_sources containing 'plugin'
   * @return string|null
   */
  protected function mapExternalIdentitySourcePlugin(array $row): ?string
  {
    return $this->mapPlugin($row, 'Source', 'External Identity Source');
  }


  /**
   * Map v4 Authenticator plugin name to v5 plugin model path.
   *
   * Examples:
   *   SshKeyAuthenticator -> SshKeyAuthenticator.SshKeyAuthenticators
   *
   * @param array $row Row data containing 'plugin'
   * @return string|null
   */
  protected function mapAuthenticatorPlugin(array $row): ?string
  {
    return $this->mapPlugin($row, 'Authenticator', 'Authenticator');
  }

  /**
   * Map v4 Provisioner plugin name to v5 plugin model path.
   *
   * Examples:
   *   LdapProvisioner -> LdapConnector.LdapProvisioner
   *
   * @param array $row Row data containing 'plugin'
   * @return string|null
   */
  protected function mapProvisionerPlugin(array $row): ?string
  {
    return $this->mapPlugin($row, 'Provisioner', 'Provisioner');
  }


  /**
   * Map email type to corresponding type ID
   *
   * @param array $row Row data containing email type
   * @return int|null       Mapped type ID
   * @since  COmanage Registry v5.2.0
   */
  protected function mapEmailType(array $row): ?int
  {
    $type = 'type';
    if (isset($row['match_type']) && $row['match_strategy'] === MatchStrategyEnum::EmailAddress) {
      $type = 'match_type';
    } else if (isset($row['email_address_type'])) {
      $type = 'email_address_type';
    }

    // No value assigned for the email address, return null
    if (empty($row[$type])) {
      return null;
    }

    return $this->mapType(
      $row,
      'EmailAddresses.type',
      $this->findCoId($row),
      $type
    );
  }

  /**
   * Map an Extended Type attribute name for model name changes.
   *
   * @since  COmanage Registry v5.2.0
   * @param  array  $row Row of table data
   * @return string      Updated attribute name
   */

  protected function mapExtendedType(array $row): string
  {
    switch($row['attribute']) {
      case 'CoDepartment.type':
        return 'Departments.type';
      case 'CoPersonRole.affiliation':
        return 'PersonRoles.affiliation_type';
    }

    // For everything else, we need to pluralize the model name
    $bits = explode('.', $row['attribute'], 2);

    return Inflector::pluralize($bits[0]) . "." . $bits[1];
  }


  /**
   * Map a petition/attribute record to its associated historic petition viewer ID
   *
   * The mapping is done by:
   * 1. Using enrollment_flow_id directly from row if available
   * 2. Otherwise looking up flow ID via petition_id foreign key
   * 3. Using flow ID to get viewer ID from enrollment_flows cache
   *
   * @param array $row Row data containing either enrollment_flow_id or petition_id
   * @return int|null Historic petition viewer ID if mapping found, null otherwise
   * @since COmanage Registry v5.2.0
   */
  protected function mapHistoricPetitionViewerId(array $row): ?int
  {
    // 1) Flow id available directly on the row (co_petitions)
    $flowId = null;
    if (!empty($row['enrollment_flow_id'])) {
      $flowId = (int)$row['enrollment_flow_id'];
    }

    // 2) Otherwise resolve via parent Petition (co_petition_attributes)
    if ($flowId === null && isset($row['petition_id'])) {
      $petitionId = (int)$row['petition_id'];
      if ($petitionId > 0) {
        // Query inbound petitions table to get its enrollment flow id
        $qualified = $this->inconn->qualifyTableName('cm_co_petitions');
        $sql = "SELECT co_enrollment_flow_id FROM {$qualified} WHERE id = ?";
        $flowId = (int)$this->inconn->fetchOne($sql, [$petitionId]) ?: null;
      }
    }

    if ($flowId === null) {
      // Not resolvable; the target column is nullable
      return null;
    }

    // 3) Map flow -> viewer id via cache created when steps/viewer row were inserted
    $viewerId = $this->cache['enrollment_flows']['id'][$flowId]['historic_petition_viewers']['id'] ?? null;

    return $viewerId !== null ? (int)$viewerId : null;
  }

  /**
   * Map identifier type to corresponding type ID
   *
   * @param array $row Row data containing identifier type
   * @return int|null       Mapped type ID
   * @since  COmanage Registry v5.2.0
   */
  protected function mapIdentifierType(array $row): ?int
  {
    $type = 'type';
    if (isset($row['sync_identifier_type'])) {
      $type = 'sync_identifier_type';
    } else if (isset($row['match_type']) && $row['match_strategy'] === MatchStrategyEnum::Identifier) {
      $type = 'match_type';
    } else if (isset($row['identifier_type'])) {
      $type = 'identifier_type';
    }

    // No value assigned for the identifier, return null
    if (empty($row[$type])) {
      return null;
    }

    return $this->mapType(
      $row,
      'Identifiers.type',
      $this->findCoId($row),
      $type
    );
  }


  /**
   * Map match attribute type to corresponding type ID based on attribute key
   *
   * @param array $row Row data containing match attribute
   * @return int|null  Mapped type ID or null if attribute not found/mapped
   * @since  COmanage Registry v5.2.0
   */
  protected function mapMatchAttributeTypeId(array $row): ?int
  {
    // Determine the model based on the match attribute map (eg, emailAddress -> EmailAddresses)
    $attributeKey = $row['attribute'] ?? null;
    if (!$attributeKey || !array_key_exists($attributeKey, self::MATCH_ATTRIBUTE_TYPE_MAP)) {
      return null;
    }

    $model = self::MATCH_ATTRIBUTE_TYPE_MAP[$attributeKey];

    // If the mapping is empty (eg, dateOfBirth), return null
    if ($model === '') {
      return null;
    }
    return $this->mapType($row, $model . '.type', $this->findCoId($row));
  }

  /**
   * Map v4 MessageTemplateEnum code in $row['context'] to v5 MessageTemplateContextEnum code
   *
   * @param array $row Row data containing 'context' (v4 record)
   * @return string|null Mapped v5 context code or null if not mapped
   * @since  COmanage Registry v5.2.0
   */
  protected function mapMessageTemplateContext(array $row): ?string
  {
    return (string)self::MESSAGE_TEMPLATE_CONTEXT_MAP[$row['context']] ?? null;
  }

  /**
   * Map login identifiers, in accordance with the configuration.
   *
   * @since  COmanage Registry v5.2.0
   * @param  array $origRow Row of table data (original data)
   * @param  array $row     Row of table data (post fixes)
   * @throws \InvalidArgumentException
   */
  protected function mapLoginIdentifiers(array $origRow, array &$row): void {
    // There might be multiple reasons to copy the row, but we only want to
    // copy it once.
    $copyRow = false;

    if(!empty($origRow['org_identity_id'])) {
      if($this->args->getOption('login-identifier-copy')
        && $origRow['login']) {
        $copyRow = true;
      }

      // Note the argument here is the old v4 string (eg "eppn") and not the
      // PE foreign key
      if($this->args->getOption('login-identifier-type')
        && $origRow['type'] == $this->args->getOption('login-identifier-type')) {
        $copyRow = true;
      }

      // Identifiers attached to External Identities do not have login flags in PE
      $row['login'] = false;
    }

    if($copyRow) {
      // Find the Person ID associated with this External Identity ID

      if(!empty($this->cache['external_identities']['id'][ $origRow['org_identity_id'] ]['person_id'])) {
        // Insert a new row attached to the Person, leave the original record
        // (ie: $row) untouched

        $copiedRow = [
          'person_id'   => $this->mapOrgIdentityCoPersonId(['id' => $origRow['org_identity_id']]),
          'identifier'  => $origRow['identifier'],
          'type_id'     => $this->mapIdentifierType($origRow),
          'status'      => $origRow['status'],
          'login'       => true,
          'created'     => $origRow['created'],
          'modified'    => $origRow['modified']
        ];

        // Set up the changelog and fix booleans
        $this->populateChangelogDefaults('identifiers', $copiedRow, true);
        $this->normalizeBooleanFieldsForDb('identifiers', $copiedRow);

        try {
          $tableName = 'identifiers';
          $qualifiedTableName = $this->outconn->qualifyTableName($tableName);

          // Check if the identifier already exists
          $identifierLookupSql = "SELECT id FROM {$qualifiedTableName}
                  WHERE person_id = ?
                    AND type_id = ?
                    AND identifier = ?
                    AND login = ?
                    AND source_identifier_id IS NULL
                  LIMIT 1";
          $identifierLookupData = [
            $copiedRow['person_id'],
            $copiedRow['type_id'],
            $copiedRow['identifier'],
            true
          ];
          $this->normalizeBooleanFieldsForDb('identifiers', $identifierLookupData);
          $existingIdentifierId = $this->outconn->fetchOne($identifierLookupSql, $identifierLookupData);

          if ($existingIdentifierId) {
            // Already present; skip insert regardless of differing metadata
            $this->cmdPrinter?->verbose("Duplicate login Identifier detected for person_id={$copiedRow['person_id']} type_id={$copiedRow['type_id']} identifier={$copiedRow['identifier']}; skipping copy.");
            return;
          }

          $this->outconn->insert($qualifiedTableName, $copiedRow);
        } catch (UniqueConstraintViolationException $e) {
          $this->cmdPrinter->warning("record already exists: " . print_r($copiedRow, true));
        } catch (\Doctrine\DBAL\Exception $e) {
          throw new \InvalidArgumentException("Failed to fetch identifier record: " . $e->getMessage());
        }
      }
    }
  }


  /**
   * Map name type to corresponding type ID
   *
   * @param array $row Row data containing name type
   * @return int|null       Mapped type ID
   * @since  COmanage Registry v5.2.0
   */
  protected function mapNameType(array $row): ?int
  {
    $type = 'type';
    if (isset($row['name_type'])) {
      $type = 'name_type';
    }

    return $this->mapType(
      $row,
      'Names.type',
      $this->findCoId($row),
      $type
    );
  }

  /**
   * Return a timestamp equivalent to now.
   *
   * @since  COmanage Registry v5.2.0
   * @param  array $row Row of table data (ignored)
   * @return string     Timestamp
   */

  protected function mapNow(array $row) {
    if(empty($this->cache['now'])) {
      $created = new \Datetime('now');
      $this->cache['now'] = $created->format('Y-m-d H:i:s');
    }

    return $this->cache['now'];
  }

  /**
   * Map an Org Identity ID to a CO Person ID
   *
   * @param array $row Row of Org Identity table data
   * @return int|null        CO Person ID
   * @throws Exception
   * @since  COmanage Registry v5.2.0
   */
  protected function mapOrgIdentityCoPersonId(array $row): ?int
  {
    // PE eliminates OrgIdentityLink, so we need to map each Org Identity to
    // a Person ID. Historically, an Org Identity could have been relinked and
    // had multiple historical mappings. We now fetch only the current row,
    // so we no longer need to track or select by revision.

    // Before Transmogrification, we require that Org Identities are unpooled.
    // (This is probably how most deployments are set up, but there may be some
    // legacy deployments out there.) This ensures whatever CO Person the Org
    // Identity currently maps to through CoOrgIdentityLink is in the same CO.

    // There may be multiple mappings if the Org Identity was relinked. Basically
    // we're going to lose the multiple mappings, since we can only return one
    // value here. (Ideally, we would inject multiple OrgIdentities into the new
    // table, but this ends up being rather tricky, since we have to figure out
    // what row id to assign, and for the moment we don't have a mechanism to
    // do that.) Historical information remains available in history_records,
    // and if the deployer keeps an archive of the old database.

    $rowId = (int)$row['id'];

    if (empty($this->cache['org_identities']['co_people'])) {
      $this->cache['org_identities']['co_people'] = [];

      // Build cache on first use
      $this->cmdPrinter->verbose('Populating org identity map...');

      $tableName = 'cm_co_org_identity_links';
      $changelogFK = 'co_org_identity_link_id';
      $qualifiedTableName = $this->inconn->qualifyTableName($tableName);

      // Only fetch current rows (historical/changelog rows are filtered out)
      $mapsql = RawSqlQueries::buildSelectAllWithNoChangelong(
        qualifiedTableName: $qualifiedTableName,
        changelogFK: $changelogFK
      );

      $stmt = $this->inconn->executeQuery($mapsql);

      while ($r = $stmt->fetchAssociative()) {
        $oid = $r['org_identity_id'] ?? null;

        if(!empty($oid)) {
          $rowRev = $r['revision'];
          if(isset($this->cache['org_identities']['co_people'][ $oid ][ $rowRev ])) {
            // If for some reason we already have a record, it's probably due to
            // improper unpooling from a legacy deployment. We'll accept only the
            // first record and throw warnings on the others.

            $this->cmdPrinter->verbose("Found existing CO Person for Org Identity " . $oid . ", skipping");
          } else {
            // Store as-is; we'll resolve the latest revision on lookup
            $this->cache['org_identities']['co_people'][ $oid ][ $rowRev ] = (int)$r['co_person_id'];
          }
        }
      }

      // Preserve keys while providing the deterministic order of revisions
      foreach ($this->cache['org_identities']['co_people'] as &$revisions) {
        if (is_array($revisions)) {
          ksort($revisions, SORT_NUMERIC);
        }
      }
      unset($revisions);
    }

    if (!empty($this->cache['org_identities']['co_people'][$rowId])) {
      // Return the record with the highest revision number
      $revisions = $this->cache['org_identities']['co_people'][$rowId];
      $rev = max(array_keys($revisions));

      return (int)$revisions[$rev];
    }

    // No current mapping found for this Org Identity
    return null;
  }


  /**
   * Maps v4 petition status codes to v5 PetitionStatusEnum values
   *
   * For in-progress/active v4 statuses (A,Y,C,CR,PA,PC,PV), maps to Terminated.
   * For final/non-active outcomes (F,X,N,D2), preserves equivalent v5 status.
   * Unknown/legacy values default to Terminated status.
   *
   * @param array $row Row data containing v4 petition status code in 'status' field
   * @return string|null Mapped v5 PetitionStatusEnum value or null if status empty/missing
   * @since COmanage Registry v5.2.0
   */
  protected function mapPetitionStatus(array $row): ?string
  {
    $v4 = $row['status'] ?? null;
    if ($v4 === null || $v4 === '') {
      return null;
    }

    // Treat any in-progress/active statuses as Terminated in v5
    // Active (A), Approved (Y), Confirmed (C), Created (CR),
    // Pending Approval (PA), Pending Confirmation (PC), Pending Vetting (PV)
    $active = ['A', 'Y', 'C', 'CR', 'PA', 'PC', 'PV'];
    if (in_array($v4, $active, true)) {
      return PetitionStatusEnum::Terminated; // 'CX'
    }

    // Preserve final/non-active outcomes
    return match ($v4) {
      'F' => PetitionStatusEnum::Finalized,
      'X' => PetitionStatusEnum::Declined,
      'N' => PetitionStatusEnum::Denied,
      'D2' => PetitionStatusEnum::Duplicate,
      default => PetitionStatusEnum::Terminated,
    };
  }


  /**
   * Get a default Pronoun type ID
   *
   * @param array $row Row data containing name type
   * @return int|null       Mapped type ID
   * @since  COmanage Registry v5.2.0
   */
  protected function mapPronounsTypeDefault(array $row): ?int
  {
    $row['type'] = 'default';

    return $this->mapType($row, 'Pronouns.type', $this->findCoId($row));
  }


  /**
   * Map v4 person status codes to v5 StatusEnum values
   *
   * Maps legacy/in-progress statuses to equivalent v5 statuses:
   * - Approved (Y), Confirmed (C), Declined (X), Invited (I),
   *   PendingApproval (PA), PendingConfirmation (PC) -> Pending (P)
   * - Denied (N) -> Suspended (S)
   * - Otherwise returns original code if allowed
   *
   * @param array $row Row data containing v4 status code in 'status' field
   * @return string|null Mapped v5 status code or null if status empty/missing
   * @since COmanage Registry v5.2.0
   */
  protected function mapPersonStatus(array $row): ?string
  {
    $code = $row['status'] ?? null;
    if ($code === null) {
      return null;
    }

    $code = strtoupper(trim((string)$code));

    // Approved, Confirmed, Declined, Invited, PendingApproval, PendingConfirmation -> Pending
    // Denied -> Suspended
    $map = [
      'Y'  => 'P',  // Approved
      'C'  => 'P',  // Confirmed
      'X'  => 'P',  // Declined
      'I'  => 'P',  // Invited
      'PA' => 'P',  // PendingApproval
      'PC' => 'P',  // PendingConfirmation
      'N'  => 'S',  // Denied
    ];

    if (isset($map[$code])) {
      return $map[$code];
    }

    // If it is allowed, just return it
    return $code;
  }


  /**
   * Maps v4 status/sync_mode combination to v5 status values
   *
   * v4:
   *   - status: 'A' (active) or 'S' (suspended)
   *   - sync_mode: 'F' (Full), 'M' (Manual), 'Q' (Query), 'U' (Update)
   *
   * v5:
   *   - sync_mode and status are merged into the single "status" column
   *   - "Active" is no longer a separate thing
   *   - v4 suspended ('S') becomes v5 Disabled ('X')
   *
   * Logic:
   *   - If v4 status is suspended ('S'), always map to Disabled.
   *   - Otherwise, copy the sync_mode over:
   *       F -> Full
   *       M -> Manual
   *       U -> Update
   *       Q -> Disabled (not yet supported; see CFM-372).
   *
   * @param array $row Row data containing 'status' and 'sync_mode' fields from v4
   * @return string|null Mapped v5 status value or null if sync_mode missing/empty
   * @since COmanage Registry v5.2.0
   */
  protected function mapStatusAndSyncToStatus(array $row): ?string
  {
    $v4Status   = $row['status']    ?? null;
    $v4SyncMode = $row['sync_mode'] ?? null;

    // Suspended in v4 becomes Disabled in v5
    if ($v4Status === 'S') {
      return SyncModeEnum::Disabled;
    }

    if ($v4SyncMode === null || $v4SyncMode === '') {
      return null;
    }

    return match ($v4SyncMode) {
      'F' => SyncModeEnum::Full,
      'M' => SyncModeEnum::Manual,
      'U' => SyncModeEnum::Update,
      'Q' => SyncModeEnum::Disabled, // XXX not yet supported; see CFM-372
      default => null,
    };
  }


  /**
   * Map server type code to corresponding plugin path
   *
   * @param array $row Row data containing server type
   * @return string|null Mapped plugin path or null if not found
   * @since  COmanage Registry v5.2.0
   */
  protected function mapServerTypeToPlugin(array $row): ?string
  {
    return (string)self::SERVER_TYPE_MAP[$row['server_type']] ?? null;
  }

  /**
   * Map telephone type to corresponding type ID
   *
   * @param array $row Row data containing telephone type
   * @return int|null       Mapped type ID
   * @since  COmanage Registry v5.2.0
   */
  protected function mapTelephoneType(array $row): ?int
  {
    $type = 'type';
    if (isset($row['telephone_number_type'])) {
      $type = 'telephone_number_type';
    }

    return $this->mapType(
      $row,
      'TelephoneNumbers.type',
      $this->findCoId($row),
      $type
    );
  }

  /**
   * Map v4 identifier assignment algorithm to v5 plugin name.
   *
   * v4 algorithm codes:
   *   R (Random)     -> CoreAssigner.FormatAssigners
   *   S (Sequential) -> CoreAssigner.FormatAssigners
   *   P (Plugin)     -> not handled here; requires separate plugin resolution
   *
   * @param array $row Row data containing 'algorithm' from cm_co_identifier_assignments
   * @return string|null Fully qualified v5 plugin name, or null for Plugin algorithm
   * @since COmanage Registry v5.2.0
   */
  protected function mapAlgorithmToPlugin(array $row): ?string
  {
    $algorithm = $row['algorithm'] ?? null;

    return match ($algorithm) {
      'R', 'S' => 'CoreAssigner.FormatAssigners',
      default  => null,
    };
  }

  /**
   * Map a type value to its corresponding ID
   *
   * @param array $row Row data containing the type value
   * @param string $type Type attribute name
   * @param int $coId CO ID
   * @param string $attr Attribute name in row data
   * @return int|null    Mapped type ID
   * @throws \InvalidArgumentException When type not found
   * @since  COmanage Registry v5.2.0
   */
  protected function mapType(array $row, string $type, int $coId, string $attr = 'type'): ?int
  {
    // If we delete a CO, we permanently delete the extended attributes. As a result, the type
    // is no longer available because there is no changelog. Types are used by Person Roles and
    // External Identity Roles.
    if(!$coId) {
      throw new \InvalidArgumentException("CO ID not provided for $type " . ($row['id'] ?? ''));
    }
    $value = $row[$attr] ?? null;
    $key = $coId . "+" . $type . "+" . $value . "+";
    if(empty($this->cache['types']['co_id+attribute+value+'][$key])) {
      if (
        !isset($this->cache['cos']['id'][$coId])
        || ($this->cache['cos']['id'][$coId]['status'] && in_array($this->cache['cos']['id'][$coId]['status'], ['TR']))
      ) {
        // This CO has been deleted, so we can't map the type. We will return null
        return null;
      }
      throw new \InvalidArgumentException("Type not found for " . $key);
    }
    return (int)$this->cache['types']['co_id+attribute+value+'][$key];
  }

  /**
   * Map URL type to corresponding type ID
   *
   * @param array $row Row data containing URL type
   * @return int|null       Mapped type ID
   * @since  COmanage Registry v5.2.0
   */
  protected function mapUrlType(array $row): ?int
  {
    $type = 'type';
    if (isset($row['url_type'])) {
      $type = 'url_type';
    }

    return $this->mapType(
      $row,
      'Urls.type',
      $this->findCoId($row),
      $type
    );
  }
}
