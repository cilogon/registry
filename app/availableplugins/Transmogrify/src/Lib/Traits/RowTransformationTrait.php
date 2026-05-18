<?php
/**
 * COmanage Registry Transmogrify Command / Row Transformation Trait
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

use App\Command\Util\InvalidArgumentException;
use App\Lib\Enum\GroupTypeEnum;
use Transmogrify\Lib\Util\RawSqlQueries;
use Doctrine\DBAL\Exception;

trait RowTransformationTrait
{
  use CacheTrait;

  /**
   * Apply validation rules for CoGroup names during transformation
   *
   * @param array $origRow Original row data from source database
   * @param array $row Row data to be transformed, passed by reference
   * @return void
   * @throws \InvalidArgumentException If group name validation fails
   */
  protected function applyCheckGroupNameARRule(array $origRow, array &$row): void
  {
    // Default selection rule for CoGroups (AR-Group-9):
    // For any Standard ("S") group:
    //  - If name equals 'CO' (case-insensitive, trimmed), throw and skip migration (always).
    //  - If name contains ':', by default throw and skip migration.
    //    If the optional --groups-colon-replacement flag is set, replace ':' with the provided string instead.

    $gtype = $origRow['group_type'] ?? null;
    $name = (string)($origRow['name'] ?? '');

    if ($gtype === GroupTypeEnum::Standard) {
      // Disallow exact "CO" (case-insensitive, trimmed) unconditionally
      if ($name !== '' && strtoupper(trim($name)) === 'CO') {
        throw new \InvalidArgumentException('Standard CoGroup name "CO" is invalid by default and cannot be auto-renamed');
      }

      // Handle colon rule
      if ($name !== '' && str_contains($name, ':')) {
        // Optional, opt-in replacement
        $replacement = (string)($this->args?->getOption('groups-colon-replacement') ?? '');
        if ($replacement === '' && ($this->args?->getOption('groups-colon-replacement-dash') === true)) {
          $replacement = '-';
        }
        if ($replacement !== '') {
          $newName = str_replace(':', $replacement, $name);
          if ($newName === '') {
            // Guard against accidental empty name
            throw new \InvalidArgumentException('Replacing ":" produced an empty Standard CoGroup name; adjust --groups-colon-replacement');
          }
          $row['name'] = $newName;
          $this->cmdPrinter?->verbose(sprintf('Replaced ":" in Standard CoGroup name "%s" -> "%s"', $name, $newName));
        } else {
          // Default: error out (no auto-replacement)
          throw new \InvalidArgumentException('Standard CoGroup names cannot contain a colon by default: ' . $name);
        }
      }
    }
  }


  /**
   * Check if a group membership is actually asserted, and reassign ownerships.
   *
   * @since  COmanage Registry v5.2.0
   * @param  array $origRow Row of table data (original data)
   * @param  array $row     Row of table data (post fixes)
   * @throws InvalidArgumentException
   */
  protected function reconcileGroupMembershipOwnership(array $origRow, array &$row): void
  {
    // We need to handle the various member+owner scenarios, but basically
    // (1) If 'owner' is set, manually create a Group Membership in the appropriate
    //     Owners Group (we need to be called via preRow to do this)
    // (2) If 'member' is NOT set, throw an exception so we don't create
    //     in invalid membership
    // (3) Otherwise just return so the Membership gets created

    $tableName = 'group_members';
    if($origRow['owner'] && !$origRow['deleted'] && !$origRow['co_group_member_id']) {
      // Create a membership in the appropriate owners group, but not
      // on changelog entries

      if(!empty($this->cache['groups']['id'][ $origRow['co_group_id'] ]['owners_group_id'])) {
        $ownerRow = [
          'group_id' => $this->cache['groups']['id'][ $origRow['co_group_id'] ]['owners_group_id'],
          'person_id' => $origRow['co_person_id'],
          'created' => $origRow['created'],
          'modified' => $origRow['modified'],
          'group_member_id' => null,
          'revision' => 0,
          'deleted' => 'f',
          'actor_identifier' => $origRow['actor_identifier']
        ];

        $qualifiedTableName = $this->outconn->qualifyTableName($tableName);
        $this->outconn->insert($qualifiedTableName, $ownerRow);
      } else {
        $this->cmdPrinter->error("Could not find owners group for CoGroupMember " . $origRow['id']);
        $this->cache['error'] += 1;
      }
    }

    if(!$row['member'] && !$row['owner']) {
      // we are caching the rejected rows so we can reject the rows that reference them as well.
      $this->cache['rejected'][$tableName][$row['id']] = $row;
      throw new \InvalidArgumentException('member not set on GroupMember');
    }
  }

  /**
   * Filter Jobs.
   *
   * @since  COmanage Registry v5.2.0
   * @param  array $origRow Row of table data (original data)
   * @param  array $row     Row of table data (post fixes)
   * @throws InvalidArgumentException
   */
  protected function validateJobIsTransmogrifiable(array $origRow, array &$row): void
  {
    // We don't update any of the attributes, but for rows with unsupported data
    // we throw an exception so they don't transmogrify.

    if($row['status'] == 'GO' || $row['status'] == 'Q') {
      throw new \InvalidArgumentException("Job is Queued or In Progress");
    }

    if($row['job_type'] == 'EX' || $row['job_type'] == 'OS') {
      throw new \InvalidArgumentException("Legacy Job types cannot be transmogrified");
    }
  }

  /**
   * Translate booleans to string literals to work around DBAL Postgres boolean handling.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string $table Table Name
   * @param  array  $row   Row of attributes, fixed in place
   */
  protected function normalizeBooleanFieldsForDb(string $table, array &$row): void
  {
    $attrs = ['deleted'];

    // We could introspect this from the schema file...
    if(!empty($this->tables[$table]['booleans'])) {
      $attrs = array_merge($attrs, $this->tables[$table]['booleans']);
    }

    foreach($attrs as $a) {
      if(isset($row[$a]) && gettype($row[$a]) == 'boolean') {
        // DBAL Postgres boolean handling seems to be somewhat buggy, see history in
        // this issue: https://github.com/doctrine/dbal/issues/1847
        // We need to (more generically than this hack) convert from boolean to char
        // to avoid errors on insert
        if($this->outconn->isMySQL()) {
          $row[$a] = ($row[$a] ? '1' : '0');
        } else {
          $row[$a] = ($row[$a] ? 't' : 'f');
        }
      }
    }
  }

  /**
   * Populate empty Changelog data from legacy records
   *
   * @since  COmanage Registry v5.2.0
   * @param  string $table Table Name
   * @param  array  $row   Row of attributes, fixed in place
   * @param  bool   $force If true, always create keys
   */
  protected function populateChangelogDefaults(string $table, array &$row, bool $force=false): void
  {
    if ($force || (array_key_exists('deleted', $row) && is_null($row['deleted']))) {
      $row['deleted'] = false;
    }

    if ($force || (array_key_exists('revision', $row) && is_null($row['revision']))) {
      $row['revision'] = 0;
    }

    if ($force || (array_key_exists('actor_identifier', $row) && is_null($row['actor_identifier']))) {
      $row['actor_identifier'] = 'Transmogrification';
    }
  }

  /**
   * Map fields that have been renamed from Registry Classic to Registry PE.
   *
   * IMPORTANT!!!
   *
   * When mapping legacy type fields, call the function-based mapper (eg, &map...Type) before configuring null
   * for the old column name. The mapper still needs the original source column value, and performNoMapping()
   * will unset that column once it sees a null mapping. In other words, always place the null mapping for
   * the old column after the new field’s function mapping so the mapper can read the legacy value
   * before it is removed.
   * In general, keep all the `unset`, the keys with value null, at the bottom of the tables.json configuration
   *
   * @since  COmanage Registry v5.2.0
   * @param  string $table Table Name
   * @param  array  $row   Row of attributes, fixed in place
   * @throws InvalidArgumentException
   */
  protected function mapLegacyFieldNames(string $table, array &$row): void
  {
    // oldname => newname, or &newname, which is a function to call.
    // Note functions can return more than one mapping

    if(empty($this->tables[$table]['fieldMap'])) {
      return;
    }

    $fields = $this->tables[$table]['fieldMap'];

    foreach ($fields as $oldname => $newname) {
      // Determine the first character only if $newname is a non-empty string
      $first = is_string($newname) && $newname !== '' ? $newname[0] : null;

      match (true) {
        // No mapping: unset the legacy field
        !$newname => $this->performNoMapping($row, $oldname),

        // Function mapping: compute value by helper method named after the &-prefixed token
        $first === '&' => $this->performFunctionMapping($row, $oldname, substr((string)$newname, 1), $table),

        // Default value mapping: set only if current value is null
        $first === '?' => $this->applyDefaultIfNull($row, $oldname, substr((string)$newname, 1)),

        // Default value mapping: force set the value to the current value
        $first === '=' => $row[$oldname] = substr((string)$newname, 1),

        // Direct rename: copy to new name and unset the old one
        default => $this->renameField($row, $oldname, (string)$newname),
      };
    }
  }

  /**
   * Process Extended Attributes by converting them to Ad Hoc Attributes.
   *
   * @since  COmanage Registry v5.2.0
   */
  protected function migrateExtendedAttributesToAdHocAttributes(): void
  {
    // This is intended to run AFTER AdHocAttributes so that we don't stomp on
    // the row identifiers.

    // First, pull the old Extended Attribute configuration.
    $extendedAttrs = [];

    $tableName = "cm_co_extended_attributes";
    $qualifiedTableName = $this->inconn->qualifyTableName($tableName);
    $insql = RawSqlQueries::buildSelectAllOrderedById($qualifiedTableName);
    $stmt = $this->inconn->query($insql);

    while($row = $stmt->fetch()) {
      $extendedAttrs[ $row['co_id'] ][] = $row['name'];
    }

    if(empty($extendedAttrs)) {
      // No need to do anything further if no attributes are configured
      return;
    }

    foreach(array_keys($extendedAttrs) as $coId) {
      $tableName = "cm_co" . $coId . "_person_extended_attributes";
      $qualifiedTableName = $this->inconn->qualifyTableName($tableName);
      $insql = RawSqlQueries::buildSelectAll($qualifiedTableName);
      $stmt = $this->inconn->query($insql);

      while($eaRow = $stmt->fetch()) {
        // If we didn't transmogrify the parent row for some reason then trying
        // to insert the ad_hoc_attributes will throw an error.
        if(!empty($this->cache['person_roles']['id'][ $eaRow['co_person_role_id'] ])) {
          foreach($extendedAttrs[$coId] as $ea) {
            $adhocRow = [
              'person_role_id'      => $eaRow['co_person_role_id'],
              'tag'                 => $ea,
              'value'               => $eaRow[$ea],
              'created'             => $eaRow['created'],
              'modified'            => $eaRow['modified']
            ];

            // Extended Attributes were not changelog enabled
            $this->populateChangelogDefaults('ad_hoc_attributes', $adhocRow, true);
            $this->normalizeBooleanFieldsForDb('ad_hoc_attributes', $adhocRow);

            try {
              $tableName = 'ad_hoc_attributes';
              $qualifiedTableName = $this->outconn->qualifyTableName($tableName);
              $this->outconn->insert($qualifiedTableName, $adhocRow);
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
              $this->cmdPrinter->warning("record already exists: " . print_r($adhocRow, true));
            }
          }
        }
      }
    }
  }

  /**
   * Create a core API row for each ApiSource plugin row.
   *
   * Intended as a postRow hook for the "api_sources" table.
   *
   * @param array $origRow Original cm_api_sources row
   * @param array $row Inserted api_sources row (already mapped)
   * @return void
   * @throws Exception
   */
  protected function createApiForApiSource(array $origRow, array $row): void
  {
    // We need the target ApiSource ID on the plugin table
    if (empty($origRow['id'])) {
      return;
    }
    $apiSourceId = (int)$origRow['id'];

    // api_user_id is optional; if missing we can't derive co_id via API user
    $apiUserId = $origRow['api_user_id'] ?? null;
    if ($apiUserId === null) {
      return;
    }

    // Derive CO ID from api_user_id; may return null if user not mapped
    $coId = $this->mapCoIdFromApiUserId(['api_user_id' => $apiUserId]);
    if ($coId === null) {
      return;
    }

    $apisTable = $this->outconn->qualifyTableName('apis');

    // Build API row
    $apiRow = [
      'co_id'       => $coId,
      'description' => '(Transmogrify) API Source Plugin',
      'plugin'      => 'ApiConnector.ApiSourceEndpoints',
      'status'      => 'A',
      'api_user_id' => $apiUserId,
      'created'     => $this->mapNow([]),
      'modified'    => $this->mapNow([]),
    ];

    // Apply changelog defaults and boolean normalization
    $this->populateChangelogDefaults('apis', $apiRow, true);
    $this->normalizeBooleanFieldsForDb('apis', $apiRow);

    $this->outconn->beginTransaction();
    try {
      // Insert into apis and cache mapping for later lookup
      $this->outconn->insert($apisTable, $apiRow);

      if (!method_exists($this->outconn, 'lastInsertId')) {
        throw new \RuntimeException('Could not retrieve API ID');
      }
      $apiId = (int)$this->outconn->lastInsertId();

      $this->outconn->commit();
      // Cache relation so mapApiIdFromCache can resolve api_id by source ID
      $externalSourceId = $row['external_identity_source_id'] ?? $row['org_identity_source_id'] ?? null;
      if ($externalSourceId !== null) {
        $this->cache['apis']['id'][$apiId]['org_identity_source_id'] = $externalSourceId;
      }
    } catch (\Throwable $e) {
      $this->outconn->rollBack();
      throw new \RuntimeException("Failed to create API record for API Source($apiSourceId): " . $e->getMessage());
    }
  }

  /**
   * Creates a new enrollment flow step for the given enrollment flow
   *
   * @param array $origRow Original row data from source database
   * @param array $row Current row data for target database
   * @return void
   * @throws Exception When database operation fails
   * @since COmanage Registry v5.2.0
   */
  protected function createEnrollmentFlowStep(array $origRow, array $row): void {
    // Only for non-deleted, non-revision rows
    if (!empty($origRow['deleted'])) {
      return;
    }
    if (!empty($origRow['co_enrollment_flow_id'])) {
      return;
    }

    // We need the target Enrollment Flow ID (assumes ID is preserved/mapped on insert)
    if (empty($row['id'])) {
      // If the target ID is not present, we can't safely create the step
      return;
    }
    $flowId = (int)$row['id'];

    // Qualified table names
    $stepsTable  = $this->outconn->qualifyTableName('enrollment_flow_steps');
    $viewerTable = $this->outconn->qualifyTableName('historic_petition_viewers');

    // Build step row (bypass ORM behaviors -> set timestamps + changelog fields)
    $step = [
      'enrollment_flow_id' => $flowId,
      'description'        => 'Petition Historic Data',
      'status'             => 'A', // Active
      'actor_type'         => 'E', // Enrollee
      'plugin'             => 'HistoricPetitionViewer.HistoricPetitionViewers',
      'ordr'               => 1,
      'created'            => $this->mapNow([]),
      'modified'           => $this->mapNow([]),
    ];
    $this->populateChangelogDefaults('enrollment_flow_steps', $step, true);
    $this->normalizeBooleanFieldsForDb('enrollment_flow_steps', $step);

    $this->outconn->beginTransaction();
    try {
      // Insert Enrollment Flow Step
      $this->outconn->insert($stepsTable, $step);

      if (!method_exists($this->outconn, 'lastInsertId')) {
        throw new \RuntimeException('Could not retrieve Enrollment Flow Step ID');
      }
      $stepId = (int)$this->outconn->lastInsertId();

      // Create the Historic Petition Viewer row pointing to the step
      $viewer = [
        'enrollment_flow_step_id' => $stepId,
        'created'                 => $this->mapNow([]),
        'modified'                => $this->mapNow([]),
      ];
      $this->populateChangelogDefaults('historic_petition_viewers', $viewer, true);
      $this->normalizeBooleanFieldsForDb('historic_petition_viewers', $viewer);

      $this->outconn->insert($viewerTable, $viewer);

      // Retrieve viewer ID
      $viewerId = (int)$this->outconn->lastInsertId();

      $this->outconn->commit();

      // Cache both IDs under enrollment_flows
      $this->cache['enrollment_flows']['id'][$flowId]['enrollment_flow_steps']['id'] = $stepId;
      $this->cache['enrollment_flows']['id'][$flowId]['historic_petition_viewers']['id'] = $viewerId;
    } catch (\Throwable $e) {
      $this->outconn->rollBack();
      throw new \RuntimeException("Failed to create enrollment flow step for enrollment flow $flowId: " . $e->getMessage());
    }
  }

  /**
   * Post-row hook: create the FormatAssigner plugin record for an identifier assignment.
   *
   * Called after the base identifier_assignments row has been inserted.
   * Reads format-related fields from the original v4 row and inserts a
   * corresponding format_assigners record linked to the newly created
   * identifier_assignment_id.
   *
   * Only runs for algorithm R (Random) or S (Sequential); skipped otherwise.
   *
   * @param array $originRow Original v4 row from cm_co_identifier_assignments
   * @param array $row       Mapped v5 row (post field mapping), must contain 'id'
   * @return void
   * @since COmanage Registry v5.2.0
   */
  protected function createIdentifierAssignmentPluginRecord(array $originRow, array $row): void
  {
    $algorithm = $originRow['algorithm'] ?? null;

    // Only FormatAssigners are handled here; Plugin algorithm is out of scope
    if (!in_array($algorithm, ['R', 'S'], true)) {
      return;
    }

    $identifierAssignmentId = $row['id'] ?? null;

    if ($identifierAssignmentId === null) {
      throw new \InvalidArgumentException(
        'createIdentifierAssignmentPluginRecord: missing id in mapped row'
      );
    }

    // Manual normalization because 'format_assigners' is not in tables.json['booleans']
    $transliterate = !empty($originRow['transliterate']);

    // Convert PHP boolean to DB-friendly string
    if ($this->outconn->isMySQL()) {
      $transliterateVal = ($transliterate ? '1' : '0');
    } else {
      $transliterateVal = ($transliterate ? 't' : 'f');
    }

    // Map timestamps to preserve history
    $created = $originRow['created'] ?? $this->mapNow([]);
    $modified = $originRow['modified'] ?? $this->mapNow([]);

    $formatAssignerRow = [
      'identifier_assignment_id' => (int)$identifierAssignmentId,
      'format'                   => $originRow['format'] ?? null,
      'minimum_length'           => isset($originRow['minimum_length'])
        ? (int)$originRow['minimum_length']
        : null,
      'minimum'                  => isset($originRow['minimum'])
        ? (int)$originRow['minimum']
        : null,
      'maximum'                  => isset($originRow['maximum'])
        ? (int)$originRow['maximum']
        : null,
      'collision_mode'           => $algorithm,
      'permitted_characters'     => $originRow['permitted'] ?? null,
      'enable_transliteration'   => $transliterateVal,
      'created'                  => $created,
      'modified'                 => $modified
    ];

    $this->populateChangelogDefaults('format_assigners', $formatAssignerRow, true);
    $this->normalizeBooleanFieldsForDb('format_assigners', $formatAssignerRow);

    $qualifiedTableName = $this->outconn->qualifyTableName('format_assigners');

    try {
      $this->outconn->beginTransaction();
      $this->outconn->insert($qualifiedTableName, $formatAssignerRow);
      // Get the ID of the inserted record
      if (!method_exists($this->outconn, 'lastInsertId')) {
        throw new \RuntimeException('Could not retrieve Format Assigner ID');
      }
      $formatAssignerId = (int)$this->outconn->lastInsertId();

      $this->outconn->commit();

      // Cache the result so we can map it later (e.g. for format_assigner_sequences)
      // We manually populate the cache since this insertion happens outside the main loop

      // Cache by ID
      $this->cacheResults(
        'format_assigners',
        array_merge(['id' => $formatAssignerId], $formatAssignerRow),
        $formatAssignerRow,
        ['identifier_assignment_id', 'id'],
      );
    } catch (\Throwable $e) {
      $this->outconn->rollBack();
      throw new \RuntimeException("Failed to create Format Assigner record for IdentifierAssignment $identifierAssignmentId: " . $e->getMessage());
    }
  }

  /**
   * Split an External Identity into an External Identity Role.
   *
   * @param array $origRow Row of table data (original data)
   * @param array $row Row of table data (post fixes)
   * @throws Exception
   * @since  COmanage Registry v5.2.0
   */

  protected function mapExternalIdentityToExternalIdentityRole(array $origRow, array $row): void
  {
    // the original row has the co_id
    $roleRow = [];

    // We could set the row ID to be the same as the original parent, but then
    // we'd have to reset the sequence after the table is finished migrating.

    foreach([
              // Parent Key
              'id' => 'external_identity_id',
              'o' => 'organization',
              'ou' => 'department',
              'manager_identifier' => 'manager_identifier',
              'sponsor_identifier' => 'sponsor_identifier',
              'status' => 'status',
              'title' => 'title',
              'valid_from' => 'valid_from',
              'valid_through' => 'valid_through',
              // Fix up changelog
              'org_identity_id' => 'external_identity_role_id',
              'revision' => 'revision',
              'deleted' => 'deleted',
              'actor_identifier' => 'actor_identifier',
              'created' => 'created',
              'modified' => 'modified'
            ] as $oldKey => $newKey) {
      $roleRow[$newKey] = $origRow[$oldKey];
    }

    // Rationale: mapOrgIdentityCoPersonId accepts only the first mapping it finds
    // (later mappings are treated as legacy/unpooled anomalies and ignored).
    // Therefore, each ExternalIdentity produces at most one ExternalIdentityRole.
    // To avoid inserting a bogus self-referential changelog link, do not carry any
    // legacy key into external_identity_role_id. Start a fresh changelog chain.
    $roleRow['external_identity_role_id'] = null;
    $roleRow['revision'] = 0;


    try {
      if (!empty($origRow['affiliation'])) {
        $row['affiliation'] = $origRow['affiliation'];
        $roleRow['affiliation_type_id'] = $this->mapAffiliationType(
          row: $row,
          coId: $origRow['co_id'] ?? null
        );
      }
    } catch (\Exception $e) {
      $this->cmdPrinter->warning("Failed to map affiliation type: " . $e->getMessage());
      if (
        isset($origRow['co_id'])
        && (!isset($this->cache['cos'][$origRow['co_id']])
          || ($this->cache['cos'][$origRow['co_id']]['status'])
          && $this->cache['cos'][$origRow['co_id']]['status'] == 'TR')
      ) {
        // This CO has been deleted, so we can't map the type. We will return null
        $roleRow['affiliation_type_id'] = null;
      } else {
        // Rethrow the exception
        throw $e;
      }
    }

    $tableName = 'external_identity_roles';

    // Fix up changelog and booleans prior to insert
    $this->populateChangelogDefaults($tableName, $roleRow, true);
    $this->normalizeBooleanFieldsForDb($tableName, $roleRow);

    $qualifiedTableName = $this->outconn->qualifyTableName($tableName);

    $this->outconn->insert($qualifiedTableName, $roleRow);
  }

  /**
   * Unset a legacy field when it has no mapping.
   *
   * @param array  &$row Row data to modify
   * @param string $oldname Name of field to remove
   * @return void
   */
  private function performNoMapping(array &$row, string $oldname): void
  {
    unset($row[$oldname]);
  }


  /**
   * Compute value for a field via a mapping function name (without the leading &).
   * Reuses the original field name in-place. Throws if the mapping yields a falsy value.
   *
   * @param array  &$row Row data to modify
   * @param string $oldname Name of field to map
   * @param string $funcName Name of mapping function to call
   * @param string $table Table name for error reporting
   * @return void
   * @throws \InvalidArgumentException When mapping returns the falsy value or function not found
   */
  private function performFunctionMapping(array &$row, string $oldname, string $funcName, string $table): void
  {
    if (!method_exists($this, $funcName)) {
      throw new \InvalidArgumentException("Mapping function {$funcName} does not exist for {$table} {$oldname}");
    }

    // We always pass the entire row so the mapping function can implement arbitrary logic
    $row[$oldname] = $this->$funcName($row);

    // Allow specific mapping functions to return null (target columns are nullable)
    $nullableFuncs = [
      'mapAffiliationType',
      'mapHistoricPetitionViewerId',
      'mapCoIdFromApiUserId',
    ];

    // Tables that allow null types
    $tablesWithNullableTypes = [
      'pipelines',
      'identifier_assignments',
    ];
    if (in_array($table, $tablesWithNullableTypes, true)) {
      $nullableFuncs[] = "mapIdentifierType";
      $nullableFuncs[] = "mapEmailType";
    }

    if (!$row[$oldname] && !in_array($funcName, $nullableFuncs, true)) {
      throw new \InvalidArgumentException("Could not find value for {$table} {$oldname}");
    }
  }


  /**
   * Apply a default value only when the current value is strictly null.
   *
   * @param array  &$row Row data to modify
   * @param string $oldname Name of field to check/update
   * @param string $default Default value to apply if field is null
   * @return void
   */
  private function applyDefaultIfNull(array &$row, string $oldname, string $default): void
  {
    if (array_key_exists($oldname, $row) && $row[$oldname] === null) {
      $row[$oldname] = $default;
    }
  }


  /**
   * Rename a field by copying its value to a new key and removing the old key.
   *
   * @param array  &$row Row data to modify
   * @param string $oldname Original field name
   * @param string $newname New field name
   * @return void
   */
  private function renameField(array &$row, string $oldname, string $newname): void
  {
    // Only copy if the old field exists to avoid notices
    if (array_key_exists($oldname, $row)) {
      $row[$newname] = $row[$oldname];
      unset($row[$oldname]);
    }
  }
}