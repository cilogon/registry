<?php
/**
 * COmanage Registry Transmogrify Command / Cache Trait
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

trait CacheTrait
{
  /**
   * Cache for type mappings and related data
   *
   * @var array
   */
  protected array $cache = [];

  /**
   * Cache a composite key for a list of fields, pointing to the row ID.
   *
   * This is used for entries like ["co_id", "attribute", "value"] where we
   * want a lookup of the form:
   *   cache[table]["co_id+attribute+value+"]["2+Identifier.type+eppn+"] = row_id
   *
   * @param string $table   Logical table name
   * @param array  $row     Current row data
   * @param array  $fields  List of field names that form the composite key
   * @return void
   */
  protected function cacheCompositeKey(string $table, array $row, array $fields): void
  {
    // This is a list of fields, create a composite key that point to the row ID
    $label = "";
    $key = "";

    foreach ($fields as $subfield) {
      // eg: co_id+attribute+value+
      $label .= $subfield . "+";

      // eg: 2+Identifier.type+eppn+
      $value = $row[$subfield] ?? '';
      $key .= $value . "+";
    }

    $this->cache[$table][$label] ??= [];
    if (isset($row['id'])) {
      $this->cache[$table][$label][$key] = $row['id'];
    }
  }

  /**
   * Cache a simple field value under the row ID bucket (with merge semantics).
   *
   * If the id is missing, this is a no-op.
   *
   * @param string $table Logical table name
   * @param array  $row   Current row data
   * @param string $field Field name to cache
   * @return void
   */
  protected function cacheFieldById(string $table, array $row, string $field): void
  {
    if (!array_key_exists($field, $row)) {
      return;
    }

    $id = $row['id'] ?? null;
    if ($id === null) {
      return;
    }

    // Ensure the id bucket is initialized
    $this->cache[$table]['id'] ??= [];
    $this->cache[$table]['id'][$id] ??= [];

    // If a value exists:
    // - If both existing and incoming are arrays, merge recursively.
    // - Otherwise, set only when the key is not present to avoid clobbering
    //   previously injected/nested data (like `enrollment_flow_steps`).
    if (array_key_exists($field, $this->cache[$table]['id'][$id])) {
      $existing = $this->cache[$table]['id'][$id][$field];
      $incoming = $row[$field];

      if (is_array($existing) && is_array($incoming)) {
        $this->cache[$table]['id'][$id][$field] = array_replace_recursive($existing, $incoming);
      } else {
        // Do not overwrite an existing non-array value or structure
        // If you do want to force an overwrite for specific fields, handle by name here.
        // e.g., if (in_array($field, ['co_id', ...], true)) { $this->cache[...] = $incoming; }
      }
    } else {
      $this->cache[$table]['id'][$id][$field] = $row[$field];
    }
  }

  /**
   * Cache results as configured for the specified table.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string     $table       Table to cache
   * @param  array      $row         Row of table data
   * @param  array      $orinRow     Original Row of table data
   * @param  array      $cacheConfig Optional cache configuration (overrides tables.json)
   */

  protected function cacheResults(string $table, array $row, array $orinRow, ?array $cacheConfig = []): void
  {
    $config = !empty($cacheConfig) ? $cacheConfig : ($this->tables[$table]['cache'] ?? []);

    if (empty($config)) {
      return;
    }

    // Cache the requested fields. For now, at least, we key on row ID only.
    foreach ($config as $field) {
      if (is_array($field)) {
        $this->cacheCompositeKey($table, $row, $field);
      } else {
        $effectiveRow = array_key_exists($field, $row) ? $row : $orinRow;
        $this->cacheFieldById($table, $effectiveRow, $field);
      }
    }
  }

  /**
   * Find CO ID from related record data
   *
   * @param array $row Row data containing person_id, external_identity_id, group_id etc
   * @return int       Mapped CO ID
   * @throws \InvalidArgumentException When CO not found
   * @since  COmanage Registry v5.2.0
   */
  protected function findCoId(array $row): int
  {
    // By the time we're called, we should have transmogrified the Org Identity
    // and CO Person data, so we can just walk the caches

    if (isset($row['co_id'])) {
      return (int)$row['co_id'];
    }

    // Choose the resolution path by precedence using match(true)
    $coId = match (true) {
      isset($row['person_id']) => $this->getCoIdFromPersonId((int)$row['person_id']),

      // Map External Identity -> Person -> CO
      isset($row['external_identity_id']) => $this->getCoIdFromExternalIdentityId((int)$row['external_identity_id']),

      isset($row['external_identity_source_id']) => $this->getCoIdFromExternalIdentitySourceId((int)$row['external_identity_source_id']),

      isset($row['group_id']) => $this->getCoIdFromGroupId((int)$row['group_id']),

      isset($row['match_server_id']) => $this->getCoIdFromMatchServer((int)$row['match_server_id']),

      isset($row['api_user_id']) => $this->getCoIdFromApiUserId((int)$row['api_user_id']),
      
      // Legacy/preRow: org_identity_id follows the same External Identity path
      isset($row['org_identity_id']) => $this->getCoIdFromExternalIdentityId((int)$row['org_identity_id']),

      isset($row['org_identity_source_id']) => $this->getCoIdFromExternalIdentitySourceId((int)$row['org_identity_source_id']),

      isset($row['co_person_id']) => $this->getCoIdFromPersonId((int)$row['co_person_id']),

      isset($row['co_group_id']) => $this->getCoIdFromGroupId((int)$row['co_group_id']),

      isset($row['co_person_role_id']) => $this->getCoIdFromPersonRoleId((int)$row['co_person_role_id']),

      default => null,
    };

    if ($coId !== null) {
      return $coId;
    }

    // For the multiple value attributes we are going to get a lot of misses
    // because we only move one org identity and only the latest one. No revisions.
    // Which means that all the values that belong to deleted org identities are going
    // to be misses.
    throw new \InvalidArgumentException("CO not found for record");
  }

  /**
   * Resolve a CO ID from a CO Person ID via cache.
   *
   * @param int $personId
   * @return int|null
   */
  private function getCoIdFromPersonId(int $personId): ?int
  {
    if (isset($this->cache['people']['id'][$personId]['co_id'])) {
      return (int)$this->cache['people']['id'][$personId]['co_id'];
    }
    return null;
  }

  /**
   * Resolve a CO Person ID from an External Identity (or legacy OrgIdentity) ID via cache.
   *
   * @param int $externalIdentityId
   * @return int|null
   */
  private function getPersonIdFromExternalIdentity(int $externalIdentityId): ?int
  {
    $personId = $this->cache['external_identities']['id'][$externalIdentityId]['person_id'] ?? null;
    return $personId !== null ? (int)$personId : null;
  }

  /**
   * Resolve a CO ID from an API User ID via cache.
   *
   * @param int $apiUserId API User ID to resolve
   * @return int|null CO ID if found, null otherwise
   * @since COmanage Registry v5.2.0
   */
  private function getCoIdFromApiUserId(int $apiUserId): ?int
  {
    if (isset($this->cache['api_users']['id'][$apiUserId]['co_id'])) {
      return (int)$this->cache['api_users']['id'][$apiUserId]['co_id'];
    }
    return null;
  }

  /**
   * Resolve a CO ID from a Group ID via cache.
   *
   * @param int $groupId
   * @return int|null
   */
  private function getCoIdFromGroupId(int $groupId): ?int
  {
    if (isset($this->cache['groups']['id'][$groupId]['co_id'])) {
      return (int)$this->cache['groups']['id'][$groupId]['co_id'];
    }
    return null;
  }

  /**
   * Resolve a CO ID starting from an External Identity (or legacy OrgIdentity) ID via cache.
   *
   * @param int $externalIdentityId
   * @return int|null
   */
  private function getCoIdFromExternalIdentityId(int $externalIdentityId): ?int
  {
    $personId = $this->getPersonIdFromExternalIdentity($externalIdentityId);
    return $personId !== null ? $this->getCoIdFromPersonId($personId) : null;
  }

    /**
     * Resolve a CO ID starting from an External Identity Source (or legacy OrgIdentitySource) ID via cache.
     *
     * @param int $externalIdentitySourceId
     * @return int|null
     */
    private function getCoIdFromExternalIdentitySourceId(int $externalIdentitySourceId): ?int
    {
        $coId = $this->cache['external_identity_sources']['id'][$externalIdentitySourceId]['co_id'] ?? null;
        return $coId !== null ? (int)$coId : null;
    }

  /**
   * Resolve a CO ID from a Match Server ID via cache.
   *
   * @param int $matchServerId Match Server ID to resolve
   * @return int|null CO ID if found, null otherwise
   * @since COmanage Registry v5.2.0
   */
  private function getCoIdFromMatchServer(int $matchServerId): ?int
  {
    if (isset($this->cache['match_servers']['id'][$matchServerId]['server_id'])) {
      $serverId = (int)$this->cache['match_servers']['id'][$matchServerId]['server_id'];
      return $this->cache['servers']['id'][$serverId]['co_id'] ?? null;
    }
    return null;
  }

  /**
   * Resolve a CO ID from a Person Role ID via cache.
   *
   * @param int $personRoleId Person Role ID to resolve
   * @return int|null CO ID if found, null otherwise
   * @since COmanage Registry v5.2.0
   */
  private function getCoIdFromPersonRoleId(int $personRoleId): ?int
  {
    if (isset($this->cache['person_roles']['id'][$personRoleId]['person_id'])) {
      $personId = (int)$this->cache['person_roles']['id'][$personRoleId]['person_id'];
      return $this->getCoIdFromPersonId($personId);
    }
    return null;
  }
}