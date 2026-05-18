<?php
/**
 * COmanage Registry Transmogrify Command/ Manage Defaults Trait
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

use App\Lib\Enum\PermittedTelephoneNumberFieldsEnum;
use App\Lib\Util\PaginatedSqlIterator;
use Cake\ORM\TableRegistry;

trait ManageDefaultsTrait
{
  /**
   * Create an Owners Group for an existing Group.
   *
   * @since  COmanage Registry v5.2.0
   */

  protected function createOwnersGroups(): void
  {
    // Pull all Groups and create Owners Group for them. Deployments generally
    // don't have so many Groups that we need PaginatedSqlIterator, but we'll
    // use it here anyway just in case.

    // By doing this once for the table we avoid having to sort through
    // changelog metadata to figure out which rows to actually create owners
    // groups for.

    $Groups = TableRegistry::getTableLocator()->get('Groups');

    $iterator = new PaginatedSqlIterator($Groups, []);

    foreach($iterator as $k => $group) {
      try {
        // Because PaginatedSqlIterator will pick up new Groups as we create them,
        // we need to check for any Owners groups (that we just created) and skip them.
        if(!$group->isOwners()) {
          $ownersGid = $Groups->createOwnersGroup($group);

          // We need to manually populate the cache
          $this->cache['groups']['id'][$group->id]['owners_group_id'] = $ownersGid;
        }
      }
      catch(\Exception $e) {
        $this->cache['error'] += 1;
        $this->cmdPrinter->error("Failed to create owners group for "
          . $group->name . " (" . $group->id . "): "
          . $e->getMessage());
      }
    }
  }

  /**
   * Map to the default affiliation type ID ("member") for the row's CO.
   *
   * Used when a row does not carry an explicit affiliation value but we want to
   * refer to the standard "member" affiliation type within the same CO.
   *
   * @param array $row Row data from which CO ID can be derived
   * @return int|null
   */
  protected function mapToDefaultAffiliationTypeId(array $row): ?int
  {
    // Let CacheTrait::findCoId decide how to resolve the CO ID
    $coId = $this->findCoId($row);

    // Build a synthetic row with the default affiliation value
    $defaultRow = [
      'affiliation' => 'member',
    ];

    // Use the generic type mapper to get the extended type ID
    return $this->mapType(
      $defaultRow,
      'PersonRoles.affiliation_type',
      $coId,
      'affiliation'
    );
  }

  /**
   * Default Address type ID (adjust value if a different default is desired).
   *
   * @param array $row
   * @return int|null
   */
  protected function mapToDefaultAddressTypeId(array $row): ?int
  {
    $coId = $this->findCoId($row);

    $defaultRow = [
      'type' => 'office',
    ];

    return $this->mapType(
      $defaultRow,
      'Addresses.type',
      $coId
    );
  }

  /**
   * Default Email Address type ID.
   *
   * @param array $row
   * @return int|null
   */
  protected function mapToDefaultEmailAddressTypeId(array $row): ?int
  {
    $coId = $this->findCoId($row);

    $defaultRow = [
      'type' => 'official',
    ];

    return $this->mapType(
      $defaultRow,
      'EmailAddresses.type',
      $coId
    );
  }

  /**
   * Default Name type ID.
   *
   * @param array $row
   * @return int|null
   */
  protected function mapToDefaultNameTypeId(array $row): ?int
  {
    $coId = $this->findCoId($row);

    $defaultRow = [
      'type' => 'official',
    ];

    return $this->mapType(
      $defaultRow,
      'Names.type',
      $coId
    );
  }

  /**
   * Default Telephone Number type ID.
   *
   * @param array $row
   * @return int|null
   */
  protected function mapToDefaultTelephoneNumberTypeId(array $row): ?int
  {
    $coId = $this->findCoId($row);

    $defaultRow = [
      'type' => 'office',
    ];

    return $this->mapType(
      $defaultRow,
      'TelephoneNumbers.type',
      $coId
    );
  }

  
  /**
   * Determine the subject model ID ("People" or "Groups") for a given row.
   *
   * This method evaluates whether the row corresponds to a person or a group
   * based on the presence of either `co_person_id` or `co_group_id`, ensuring
   * that exactly one of these fields is populated.
   *
   * @param array $row Row data containing subject identifiers
   * @return string Either "People" or "Groups" based on the input data
   * @throws \RuntimeException If neither or both `co_person_id` and `co_group_id` are set
   * @since  COmanage Registry v5.2.0
   */
  protected function mapToSubjectModel(array $row): string
  {
    $personId = $row['co_person_id'] ?? null;
    $groupId  = $row['co_group_id'] ?? null;

    $hasPerson = ($personId !== null && $personId !== '');
    $hasGroup  = ($groupId !== null && $groupId !== '');

    if($hasPerson xor $hasGroup) {
      return $hasPerson ? 'People' : 'Groups';
    }

    $exportId = $row['id'] ?? '(unknown)';

    throw new \RuntimeException(
      "Invalid cm_co_provisioning_exports row {$exportId}: exactly one of co_person_id or co_group_id must be set"
    );
  }


  /**
   * Determine the subject ID ("People" or "Groups") for a given row.
   *
   * This method evaluates whether the row corresponds to a person or a group
   * based on the presence of either `co_person_id` or `co_group_id`, ensuring
   * that exactly one of these fields is populated, and returns the corresponding ID.
   *
   * @param array $row Row data containing subject identifiers
   * @return int|null ID of the person or group, or null if neither can be determined
   * @throws \RuntimeException If neither or both `co_person_id` and `co_group_id` are set
   * @since  COmanage Registry v5.2.0
   */
  protected function mapToSubjectId(array $row): ?int
  {
    $personId = $row['co_person_id'] ?? null;
    $groupId  = $row['co_group_id'] ?? null;

    $hasPerson = ($personId !== null && $personId !== '');
    $hasGroup  = ($groupId !== null && $groupId !== '');

    // Exactly one of co_person_id / co_group_id must be set.
    if($hasPerson xor $hasGroup) {
      return $hasPerson ? (int)$personId : (int)$groupId;
    }

    $exportId = $row['id'] ?? '(unknown)';

    throw new \RuntimeException(
      "Invalid provisioning export row {$exportId}: exactly one of co_person_id or co_group_id must be set"
    );
  }


  /**
   * Insert default CO Settings for COs that don't have settings.
   *
   * @since  COmanage Registry v5.2.0
   * @return void
   */
  protected function insertDefaultSettings(): void
  {
    // Create a CoSetting for any CO that didn't previously have one.

    $createdSettings = [];
    $createdCos = array_keys($this->cache['cos']['id']);

    foreach($this->cache['co_settings']['id'] as $co_setting_id => $cached) {
      $createdSettings[] = $cached['co_id'];
    }

    $emptySettings = array_values(array_diff($createdCos, $createdSettings));

    if(!empty($emptySettings)) {
      $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');

      foreach($emptySettings as $coId) {
        // Insert a default row into CoSettings for this CO ID
        try {
          $CoSettings->addDefaults($coId);
        } catch (\ConflictException $e) {
          // skip
        }
      }
    }
  }

  /**
   * Insert default Pronoun types for all COs.
   *
   * @since  COmanage Registry v5.2.0
   * @return void
   */
  protected function insertPronounTypes(): void
  {
    // Since the Pronoun MVEA didn't exist in v4, we'll need to create the
    // default types for all COs.

    $Types = TableRegistry::getTableLocator()->get('Types');

    foreach(array_keys($this->cache['cos']['id']) as $coId) {
      $Types->addDefault($coId, 'Pronouns.type');
    }

    // After inserting the default Pronoun types, populate the type cache so that
    // subsequent calls to mapType() for Pronouns.type can resolve the IDs.
    $pronounTypes = $Types->find()
      ->where([
        'attribute' => 'Pronouns.type',
        'co_id IN'  => array_keys($this->cache['cos']['id']),
      ])
      ->all();

    foreach ($pronounTypes as $type) {
      $row = [
        'id'        => $type->id,
        'co_id'     => $type->co_id,
        'attribute' => $type->attribute,
        'value'     => $type->value,
      ];

      // This will populate:
      // $this->cache['types']['co_id+attribute+value+']["<co>+Pronouns.type+<value>+"] = <id>
      $this->cacheCompositeKey('types', $row, ['co_id', 'attribute', 'value']);
    }
  }

  /**
   * Set a default value for CO Settings Permitted Telephone Number Fields.
   * Returns CANE as the default permitted telephone number field value.
   *
   * @param array $row Row of table data
   * @return string       Default value CANE
   * @since  COmanage Registry v5.2.0
   */

  protected function populateCoSettingsPhone(array $row): string
  {
    return PermittedTelephoneNumberFieldsEnum::CANE;
  }
}
