<?php
/**
 * COmanage Registry LDAP Provisioner DNs Table
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
 * @package       registry-plugins
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace LdapConnector\Model\Table;

use App\Lib\Enum\StatusEnum;
use App\Model\Entity\Group;
use App\Model\Entity\Person;
use App\Model\Entity\ProvisioningTarget;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class LdapProvisionerDnsTable extends Table
{
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake model initialization.
   *
   * Sets up behaviors, associations, primary link scoping, and permissions.
   * This table is operational state for LDAP provisioning (DN registry/cache),
   * scoped per LdapProvisioner and per Person/Group.
   *
   * @param array $config Table configuration options passed to the constructor.
   * @return void
   * @throws \Throwable
   * @since COmanage Registry v5.3.0
   */
  public function initialize(array $config): void
  {
    parent::initialize($config);

    $this->setTable('ldap_provisioner_dns');
    $this->setPrimaryKey('id');
    $this->setDisplayField('dn');

    // Changelog-safe operational state
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->belongsTo('LdapConnector.LdapProvisioners', [
      'foreignKey' => 'ldap_provisioner_id',
      'joinType'   => 'INNER',
    ]);

    $this->belongsTo('People', [
      'foreignKey' => 'person_id',
    ]);

    $this->belongsTo('Groups', [
      'foreignKey' => 'group_id',
    ]);

    // Rows are always scoped to a specific LdapProvisioner instance.
    $this->setPrimaryLink(['LdapConnector.ldap_provisioner_id']);

    $this->setPermissions([
      'entity' => [
        'delete' => ['platformAdmin', 'coAdmin'],
        'edit'   => ['platformAdmin', 'coAdmin'],
        'view'   => ['platformAdmin', 'coAdmin'],
      ],
      'table' => [
        'add'   => ['platformAdmin', 'coAdmin'],
        'index' => ['platformAdmin', 'coAdmin'],
      ],
    ]);
  }

  /**
   * Default validation rules.
   *
   * Enforces:
   * - required ldap_provisioner_id
   * - dn length and non-empty constraint
   * - XOR constraint: exactly one of person_id or group_id must be set
   * - changelog-safe uniqueness (application-level) for:
   *   - (ldap_provisioner_id, person_id) among current rows
   *   - (ldap_provisioner_id, group_id) among current rows
   *
   * @param \Cake\Validation\Validator $validator Validator instance.
   * @return \Cake\Validation\Validator Modified validator instance.
   * @throws \Throwable
   * @since COmanage Registry v5.3.0
   */
  public function validationDefault(Validator $validator): Validator
  {
    $validator->add('ldap_provisioner_id', [
      'content' => ['rule' => 'isInteger'],
    ]);
    $validator->notEmptyString('ldap_provisioner_id');

    $validator->add('person_id', [
      'content' => ['rule' => 'isInteger'],
    ]);
    $validator->allowEmptyString('person_id');

    $validator->add('group_id', [
      'content' => ['rule' => 'isInteger'],
    ]);
    $validator->allowEmptyString('group_id');

    $validator->scalar('dn')
      ->maxLength('dn', 256)
      ->notEmptyString('dn');

    // Exactly one of person_id or group_id must be present (XOR).
    $validator->add('person_id', 'personXorGroup', [
      'rule' => function ($value, $context) {
        $data = $context['data'] ?? [];
        $hasPerson = !empty($data['person_id'] ?? null);
        $hasGroup  = !empty($data['group_id'] ?? null);

        return ($hasPerson xor $hasGroup);
      },
      'message' => __d('ldap_connector', 'error.LdapProvisionerDns.person_xor_group'),
    ]);

    // Changelog-safe uniqueness.
    //
    // Important: The scope includes:
    // - ldap_provisioner_id
    // - person_id OR group_id
    // - revision
    // - ldap_provisioner_dn_id (the Changelog parent FK for archived rows)
    //
    // This matches the general pattern used by other changelog-enabled tables.

    $validator->add('person_id', 'uniquePersonPerProvisionerCurrentRow', [
      'rule' => [
        'validateUnique',
        ['scope' => ['ldap_provisioner_id', 'person_id', 'revision', 'ldap_provisioner_dn_id']]
      ],
      'provider' => 'table',
      'message' => __d('ldap_connector', 'error.LdapProvisionerDns.person.exists'),
    ]);

    $validator->add('group_id', 'uniqueGroupPerProvisionerCurrentRow', [
      'rule' => [
        'validateUnique',
        ['scope' => ['ldap_provisioner_id', 'group_id', 'revision', 'ldap_provisioner_dn_id']]
      ],
      'provider' => 'table',
      'message' => __d('ldap_connector', 'error.LdapProvisionerDns.group.exists'),
    ]);

    return $validator;
  }

  /**
   * Build application integrity rules.
   *
   * Enforces changelog-safe uniqueness (application-level) for:
   * - (ldap_provisioner_id, person_id) among current rows
   * - (ldap_provisioner_id, group_id) among current rows
   *
   * Note: Without DB unique constraints, duplicates remain possible under concurrency.
   *
   * @param RulesChecker $rules Rules checker.
   * @return RulesChecker
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function buildRules(RulesChecker $rules): RulesChecker
  {
    $rules->add(
      $rules->isUnique(
        ['ldap_provisioner_id', 'person_id', 'revision', 'ldap_provisioner_dn_id'],
        __d('ldap_connector', 'error.LdapProvisionerDns.person.exists')
      ),
      'uniquePersonPerProvisionerCurrentRow',
      ['errorField' => 'person_id']
    );

    $rules->add(
      $rules->isUnique(
        ['ldap_provisioner_id', 'group_id', 'revision', 'ldap_provisioner_dn_id'],
        __d('ldap_connector', 'error.LdapProvisionerDns.group.exists')
      ),
      'uniqueGroupPerProvisionerCurrentRow',
      ['errorField' => 'group_id']
    );

    return $rules;
  }

  /**
   * Escape an RDN value for DN construction.
   *
   * @param string $value RDN value
   * @return string Escaped value
   * @since COmanage Registry v5.3.0
   */
  protected function escapeRdnValue(string $value): string
  {
    if (function_exists('ldap_escape')) {
      return ldap_escape($value, '', LDAP_ESCAPE_DN);
    }

    // Fallback: minimal RFC4514-ish escaping.
    $value = str_replace(
      ['\\', ',', '+', '"', '<', '>', ';', '='],
      ['\\\\', '\,', '\+', '\"', '\<', '\>', '\;', '\='],
      $value
    );

    if ($value !== '' && $value[0] === '#') {
      $value = '\#' . substr($value, 1);
    }

    $value = preg_replace('/^ /', '\ ', $value);
    $value = preg_replace('/ $/', '\ ', $value);

    return (string)$value;
  }

  /**
   * Assign a DN for a Person during provisioning.
   *
   * @param ProvisioningTarget $provisioningTarget Provisioning target entity (contains ldap_provisioner config).
   * @param Person $person Provisioned person entity (expects identifiers to be contained).
   * @param string $baseDn Base DN to append (eg: "ou=people,dc=example,dc=org").
   * @return string DN
   *
   * @throws \RuntimeException
   * @since COmanage Registry v5.3.0
   */
  public function assignPersonDn(
    ProvisioningTarget $provisioningTarget,
    Person $person,
    string $baseDn
  ): string {
    $dnAttributeName = $provisioningTarget->ldap_provisioner->dn_attribute_name ?? null;
    $dnIdentifierTypeId = $provisioningTarget->ldap_provisioner->dn_identifier_type_id ?? null;

    if (empty($dnAttributeName) || empty($dnIdentifierTypeId) || empty($baseDn)) {
      throw new \RuntimeException(__d('ldap_connector', 'DN configuration is incomplete'));
    }

    $identifiers = $person->identifiers ?? [];

    foreach ($identifiers as $identifier) {
      $typeId = $identifier->type_id ?? null;
      $value = $identifier->identifier ?? null;
      $status = $identifier->status ?? null;

      if (!empty($typeId)
        && (int)$typeId === (int)$dnIdentifierTypeId
        && !empty($value)
        && (string)$status === StatusEnum::Active) {
        $escaped = $this->escapeRdnValue((string)$value);
        return $dnAttributeName . '=' . $escaped . ',' . $baseDn;
      }
    }

    // We can't proceed without a DN.
    throw new \RuntimeException(
      __d(
        'ldap_connector',
        'No active identifier found for DN Identifier Type ID {0}',
        [(string)$dnIdentifierTypeId]
      )
    );
  }

  /**
   * Determine the RDN attributes used to generate a DN.
   *
   * @param string $dn Full DN (eg: "uid=alice,ou=people,dc=example,dc=org")
   * @param string $baseDn Base DN suffix (eg: "ou=people,dc=example,dc=org")
   * @return array<string,string> Attribute/value pairs (RDN components), not including the base DN
   * @since COmanage Registry v5.3.0
   */
  public function dnAttributes(string $dn, string $baseDn): array
  {
    $ret = [];

    if ($dn === '' || $baseDn === '') {
      return $ret;
    }

    $suffix = ',' . $baseDn;

    // Only strip baseDn if it is actually a suffix.
    if (strlen($dn) <= strlen($suffix) || strcasecmp(substr($dn, -strlen($suffix)), $suffix) !== 0) {
      return $ret;
    }

    $prefix = rtrim(substr($dn, 0, -strlen($suffix)), " ,");
    if ($prefix === '') {
      return $ret;
    }

    $attrs = explode(',', $prefix);

    foreach ($attrs as $a) {
      $a = trim($a);
      if ($a === '') {
        continue;
      }

      $av = explode('=', $a, 2);
      if (count($av) !== 2 || $av[0] === '') {
        continue;
      }

      $ret[$av[0]] = $av[1];
    }

    return $ret;
  }

  /**
   * Obtain a DN for a Person, possibly assigning or reassigning one.
   *
   * @param ProvisioningTarget $provisioningTarget Provisioning target entity (contains ldap_provisioner config).
   * @param Person $person Provisioned person entity (expects identifiers to be contained).
   * @param string $baseDn Base DN suffix (eg: "ou=people,dc=example,dc=org").
   * @param bool $assign Whether to assign a DN if one is not found and reassign if the DN should be changed.
   * @return array An array of the following:
   *               - olddn: Old (current) DN (may be null)
   *               - olddnid: Database row ID of old dn (may be null, to facilitate delete)
   *               - newdn: New DN (may be null)
   *               - newdnerr: Error message if new DN cannot be assigned
   * @throws \RuntimeException
   * @since COmanage Registry v5.3.0
   */
  public function obtainPersonDn(
    ProvisioningTarget $provisioningTarget,
    Person $person,
    string $baseDn,
    bool $assign = true
  ): array {
    $curDn = null;
    $curDnId = null;
    $newDn = null;
    $newDnErr = null;

    $ldapProvisionerId = $provisioningTarget->ldap_provisioner->id ?? null;
    if (empty($ldapProvisionerId) || empty($person->id)) {
      return [
        'olddn'    => null,
        'olddnid'  => null,
        'newdn'    => null,
        'newdnerr' => __d('ldap_connector', 'Unable to determine provisioner or person ID'),
      ];
    }

    $dnEntity = $this->find()
      ->where([
        'LdapProvisionerDns.ldap_provisioner_id' => (int)$ldapProvisionerId,
        'LdapProvisionerDns.person_id'          => (int)$person->id,
      ])
      ->first();

    if ($dnEntity) {
      $curDn = $dnEntity->dn ?? null;
      $curDnId = $dnEntity->id ?? null;
    }

    try {
      $newDn = $this->assignPersonDn($provisioningTarget, $person, $baseDn);
    } catch (\Throwable $e) {
      $newDnErr = $e->getMessage();
    }

    if ($assign) {
      if (!empty($newDn) && $curDn !== $newDn) {
        if ($dnEntity) {
          $dnEntity = $this->patchEntity($dnEntity, ['dn' => $newDn]);
        } else {
          $dnEntity = $this->newEntity([
            'ldap_provisioner_id' => (int)$ldapProvisionerId,
            'person_id'           => (int)$person->id,
            'dn'                  => $newDn,
          ]);
        }

        if (!$this->save($dnEntity)) {
          throw new \RuntimeException(__d('ldap_connector', 'Failed to save DN mapping'));
        }
      }
    }

    return [
      'olddn'    => $curDn,
      'olddnid'  => $curDnId,
      'newdn'    => $newDn,
      'newdnerr' => $newDnErr,
    ];
  }

  /**
   * Map a set of Person IDs to their provisioned DNs for a given LDAP provisioner.
   *
   * @param int $ldapProvisionerId LdapProvisioners.id
   * @param array<int,int|string> $personIds Person IDs to resolve
   * @return array<int,string> Array of DNs found (unordered, may have fewer entries than requested)
   * @since COmanage Registry v5.3.0
   */
  public function dnsForPeopleIds(int $ldapProvisionerId, array $personIds): array
  {
    $personIds = array_values(array_unique(array_map('intval', $personIds)));
    $personIds = array_values(array_filter($personIds, static fn($v) => $v > 0));

    if (empty($personIds)) {
      return [];
    }

    $rows = $this->find()
      ->select(['dn'])
      ->where([
        'LdapProvisionerDns.ldap_provisioner_id' => $ldapProvisionerId,
        'LdapProvisionerDns.person_id IN'       => $personIds,
      ])
      ->enableHydration(false)
      ->all()
      ->toArray();

    $dns = [];
    foreach ($rows as $r) {
      if (!empty($r['dn'])) {
        $dns[] = (string)$r['dn'];
      }
    }

    return $dns;
  }

  /**
   * Extract Person IDs from GroupMember entities/rows.
   *
   * @param array $groupMembers Array of GroupMember entities (or array-ish rows)
   * @return array<int,int> Person IDs
   * @since COmanage Registry v5.3.0
   */
  public function extractPersonIdsFromGroupMembers(array $groupMembers): array
  {
    $ids = [];

    foreach ($groupMembers as $m) {
      $personId = null;

      if (is_object($m)) {
        $personId = $m->person_id ?? null;
      } elseif (is_array($m)) {
        $personId = $m['person_id'] ?? null;
      }

      if ($personId !== null && $personId !== '') {
        $ids[] = (int)$personId;
      }
    }

    $ids = array_values(array_unique(array_filter($ids, static fn($v) => $v > 0)));

    return $ids;
  }

  /**
   * Assign a DN for a Group during provisioning.
   *
   * @param ProvisioningTarget $provisioningTarget Provisioning target entity (contains ldap_provisioner config).
   * @param Group $group Provisioned group entity.
   * @param string $groupBaseDn Base DN to append (eg: "ou=groups,dc=example,dc=org").
   * @return string DN
   *
   * @throws \RuntimeException
   * @since COmanage Registry v5.3.0
   */
  public function assignGroupDn(
    ProvisioningTarget $provisioningTarget,
    Group $group,
    string $groupBaseDn
  ): string {
    $groupName = $group->name ?? null;

    if (empty($groupName)) {
      throw new \RuntimeException(__d('ldap_connector', 'Group DN component is missing: {0}', ['cn']));
    }

    if (empty($groupBaseDn)) {
      throw new \RuntimeException(__d('ldap_connector', 'DN configuration is incomplete'));
    }

    $escaped = $this->escapeRdnValue((string)$groupName);

    return 'cn=' . $escaped . ',' . $groupBaseDn;
  }

  /**
   * Obtain a DN for a Group, possibly assigning or reassigning one.
   *
   * @param ProvisioningTarget $provisioningTarget Provisioning target entity (contains ldap_provisioner config).
   * @param Group $group Provisioned group entity.
   * @param string $groupBaseDn Base DN suffix (eg: "ou=groups,dc=example,dc=org").
   * @param bool $assign Whether to assign a DN if one is not found and reassign if the DN should be changed.
   * @return array An array of the following:
   *               - olddn: Old (current) DN (may be null)
   *               - olddnid: Database row ID of old dn (may be null, to facilitate delete)
   *               - newdn: New DN (may be null)
   *               - newdnerr: Error message if new DN cannot be assigned
   * @throws \RuntimeException
   * @since COmanage Registry v5.3.0
   */
  public function obtainGroupDn(
    ProvisioningTarget $provisioningTarget,
    Group $group,
    string $groupBaseDn,
    bool $assign = true
  ): array {
    $curDn = null;
    $curDnId = null;
    $newDn = null;
    $newDnErr = null;

    $ldapProvisionerId = $provisioningTarget->ldap_provisioner->id ?? null;
    if (empty($ldapProvisionerId) || empty($group->id)) {
      return [
        'olddn'    => null,
        'olddnid'  => null,
        'newdn'    => null,
        'newdnerr' => __d('ldap_connector', 'Unable to determine provisioner or group ID'),
      ];
    }

    $dnEntity = $this->find()
      ->where([
        'LdapProvisionerDns.ldap_provisioner_id' => (int)$ldapProvisionerId,
        'LdapProvisionerDns.group_id'           => (int)$group->id,
      ])
      ->first();

    if ($dnEntity) {
      $curDn = $dnEntity->dn ?? null;
      $curDnId = $dnEntity->id ?? null;
    }

    try {
      $newDn = $this->assignGroupDn($provisioningTarget, $group, $groupBaseDn);
    } catch (\Throwable $e) {
      $newDnErr = $e->getMessage();
    }

    if ($assign) {
      if (!empty($newDn) && $curDn !== $newDn) {
        if ($dnEntity) {
          $dnEntity = $this->patchEntity($dnEntity, ['dn' => $newDn]);
        } else {
          $dnEntity = $this->newEntity([
            'ldap_provisioner_id' => (int)$ldapProvisionerId,
            'group_id'            => (int)$group->id,
            'dn'                  => $newDn,
          ]);
        }

        if (!$this->save($dnEntity)) {
          throw new \RuntimeException(__d('ldap_connector', 'Failed to save DN mapping'));
        }
      }
    }

    return [
      'olddn'    => $curDn,
      'olddnid'  => $curDnId,
      'newdn'    => $newDn,
      'newdnerr' => $newDnErr,
    ];
  }

  /**
   * Map a set of Group IDs to their provisioned DNs for a given LDAP provisioner.
   *
   * @param int $ldapProvisionerId LdapProvisioners.id
   * @param array<int,int|string> $groupIds Group IDs to resolve
   * @return array<int,string> Array of DNs found (unordered, may have fewer entries than requested)
   * @since COmanage Registry v5.3.0
   */
  public function dnsForGroupIds(int $ldapProvisionerId, array $groupIds): array
  {
    $groupIds = array_values(array_unique(array_map('intval', $groupIds)));
    $groupIds = array_values(array_filter($groupIds, static fn($v) => $v > 0));

    if (empty($groupIds)) {
      return [];
    }

    $rows = $this->find()
      ->select(['dn'])
      ->where([
        'LdapProvisionerDns.ldap_provisioner_id' => $ldapProvisionerId,
        'LdapProvisionerDns.group_id IN'        => $groupIds,
      ])
      ->enableHydration(false)
      ->all()
      ->toArray();

    $dns = [];
    foreach ($rows as $r) {
      if (!empty($r['dn'])) {
        $dns[] = (string)$r['dn'];
      }
    }

    return $dns;
  }
}
