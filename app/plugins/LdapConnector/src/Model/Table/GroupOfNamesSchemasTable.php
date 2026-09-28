<?php
/**
 * COmanage Registry LDAP Connector Schema Group Of Names Table
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

use App\Model\Entity\Group;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

class GroupOfNamesSchemasTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \LdapConnector\Lib\Traits\LdapObjectClassSchemaTrait;

  /**
   * Perform Cake model initialization.
   *
   * Sets behaviors, associations, primary link, default contains (including filtered attributes),
   * and permissions for Group Of Names schema.
   *
   * @param array $config Table configuration options passed to the constructor.
   * @return void
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->belongsTo('LdapConnector.LdapSchemas');

    $this->setPrimaryLink(['LdapConnector.ldap_schema_id']);
    $this->setRequiresCO(true);

    $this->setViewContains(['LdapSchemas']);
    $this->setEditContains(['LdapSchemas']);
    $this->setIndexContains(['LdapSchemas']);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');

    $this->setPermissions([
      'entity' => [
        'delete' => ['platformAdmin', 'coAdmin'],
        'edit'   => ['platformAdmin', 'coAdmin'],
        'view'   => ['platformAdmin', 'coAdmin']
      ],
      'table' => [
        'add'     => ['platformAdmin', 'coAdmin'],
        'index'   => ['platformAdmin', 'coAdmin'],
        'deleted' => ['platformAdmin', 'coAdmin'],
      ]
    ]);
  }

  /**
   * @since COmanage Registry v5.3.0
   */
  public function supportsPeople(): bool
  {
    return false;
  }

  /**
   * @since COmanage Registry v5.3.0
   */
  public function supportsGroups(): bool
  {
    return true;
  }

  /**
   * Default schema values for groupOfNames.
   *
   * Required attributes (cn, member) are enabled by default.
   *
   * @return array<string, mixed> Column defaults
   * @since  COmanage Registry v5.3.0
   */
  public function getDefaults(): array {
    return [
      'cn'     => true,
      'member' => true,
    ];
  }

  /**
   * Hook to assemble LDAP attributes for the groupOfNames schema.
   *
   * The groupOfNames structural objectclass (RFC 4519) represents a group entry in the directory
   * (Groups only). Attributes are exported based on the direct column configuration of the schema entity:
   *
   * Supported attributes and rationale:
   * - cn (Common Name):
   *   - Single-valued. Enabled via boolean flag `cn` (required by RFC 4519).
   *   - Derived from the Group name (fallback: description, then '.').
   *     Included as the mandatory naming attribute for the group entry.
   * - description:
   *   - Single-valued. Enabled via boolean flag `description`.
   *   - Derived from the Group description. Included to publish the human-readable description.
   * - member:
   *   - Multi-valued. Enabled via boolean flag `member` (required by RFC 4519).
   *   - Contains the full Distinguished Names (DNs) of the group's active, valid members,
   *     resolved from `LdapProvisionerDns` for the current provisioner target.
   *     Included as the mandatory membership list for groupOfNames entries.
   *     Note: If enabled and no member DNs can be resolved, an UnderflowException('member') is
   *     thrown to prevent creating an RFC-invalid entry in LDAP.
   * - owner:
   *   - Multi-valued. Enabled via boolean flag `owner`.
   *   - Contains the Distinguished Names (DNs) of group owners, resolved from `LdapProvisionerDns`
   *     via the group's designated owners group (`groups.owners_group_id`).
   *     Included to publish administrative ownership of the group in LDAP.
   *
   * Operation semantics:
   * - On add ($op = 'add'): emits only attributes that have populated values.
   * - On modify or rename ($op = 'modify' | 'rename'): for optional attributes like `owner`,
   *   if configured but no owner DNs can be resolved, emits an empty array (`owner => []`)
   *   to clear previously set values in LDAP.
   * - Whenever attributes are contributed, ensures the `groupOfNames` objectclass is present in `objectClass`.
   *
   * Example (typical add for a Group):
   * <code>
   * [
   *   'objectClass' => ['groupOfNames'],
   *   'cn'          => 'Faculty Council',
   *   'description' => 'Elected faculty governance committee',
   *   'member'      => [
   *     'uid=jdoe,ou=people,dc=example,dc=org',
   *     'uid=asmith,ou=people,dc=example,dc=org'
   *   ],
   *   'owner'       => [
   *     'uid=jdoe,ou=people,dc=example,dc=org'
   *   ]
   * ]
   * </code>
   *
   * Example (modify/rename where owner group has no members and owner attribute is cleared):
   * <code>
   * [
   *   'objectClass' => ['groupOfNames'],
   *   'cn'          => 'Faculty Council',
   *   'member'      => [
   *     'uid=jdoe,ou=people,dc=example,dc=org'
   *   ],
   *   'owner'       => []
   * ]
   * </code>
   *
   * @param EntityInterface $schema Active groupOfNames schema configuration entity.
   * @param EntityInterface $ldapProvisioner The LDAP provisioner configuration entity.
   * @param string $className Name of the model being provisioned ('People' or 'Groups').
   * @param object $data Provisioned entity (eg: Group).
   * @param string $op Provisioning operation: 'add', 'modify', or 'rename'.
   * @return array<string,mixed> Assembled LDAP attributes for this schema.
   * @throws \UnderflowException If `member` is enabled but no member DNs can be resolved.
   * @throws \Throwable
   * @since COmanage Registry v5.3.0
   */
  public function assemblePluginAttributes(
    EntityInterface $schema,
    EntityInterface $ldapProvisioner,
    string $className,
    object $data,
    string $op
  ): array {
    $ret = [];

    if ($className !== 'Groups' || !($data instanceof Group)) {
      return $ret;
    }

    if (!empty($schema->cn)) {
      $ret['cn'] = $this->assembleCn($data);
    }

    if (!empty($schema->description)) {
      $desc = $this->assembleDescription($data);
      if ($desc !== null) {
        $ret['description'] = $desc;
      }
    }

    $ldapProvisionerId = (int)($ldapProvisioner->id ?? 0);
    if (!empty($ldapProvisionerId)) {
      if (!empty($schema->member)) {
        $ret['member'] = $this->assembleMember($data, $ldapProvisionerId);
      }

      if (!empty($schema->owner)) {
        $isModifyLike = ($op === 'modify' || $op === 'rename');
        $owners = $this->assembleOwner($data, $ldapProvisionerId, $isModifyLike);
        if ($owners !== null) {
          $ret['owner'] = $owners;
        }
      }
    }

    $this->ensureSchemaObjectClass($ret);

    return $ret;
  }

  /**
   * Assemble cn (Common Name) attribute from Group.
   *
   * @param Group $group Provisioned Group entity.
   * @return string Common name.
   */
  protected function assembleCn(Group $group): string
  {
    return (string)($group->name ?? '');
  }

  /**
   * Assemble description attribute from Group.
   *
   * @param Group $group Provisioned Group entity.
   * @return string|null Description, or null if empty.
   */
  protected function assembleDescription(Group $group): ?string
  {
    if (!empty($group->description)) {
      return (string)$group->description;
    }

    return null;
  }

  /**
   * Assemble member DNs for Group members.
   *
   * @param Group $group Provisioned Group entity.
   * @param int $ldapProvisionerId Provisioner ID for DN lookup.
   * @return array<string> List of member DNs.
   * @throws \UnderflowException If no member DNs can be resolved.
   */
  protected function assembleMember(Group $group, int $ldapProvisionerId): array
  {
    $dns = [];

    foreach (($group->group_members ?? []) as $gm) {
      if (!is_object($gm) || empty($gm->person_id)) {
        continue;
      }

      $dn = $this->resolvePersonDn((int)$gm->person_id, $ldapProvisionerId);
      if (!empty($dn)) {
        $dns[] = $dn;
      }
    }

    $dns = array_values(array_unique(array_filter($dns, static fn($v) => $v !== '')));

    if (empty($dns)) {
      throw new \UnderflowException('member');
    }

    return $dns;
  }

  /**
   * Assemble owner DNs for Group owners.
   *
   * @param Group $group Provisioned Group entity.
   * @param int $ldapProvisionerId Provisioner ID for DN lookup.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null Owner DNs, empty array to clear on modify, or null if omitted on add.
   */
  protected function assembleOwner(Group $group, int $ldapProvisionerId, bool $isModifyLike): ?array
  {
    $ownerDns = [];

    // If caller provided explicit owners on the entity
    foreach (($group->group_owners ?? []) as $go) {
      if (!is_object($go) || empty($go->person_id)) {
        continue;
      }

      $dn = $this->resolvePersonDn((int)$go->person_id, $ldapProvisionerId);
      if (!empty($dn)) {
        $ownerDns[] = $dn;
      }
    }

    // Else, best-effort: owners are members of owners_group_id
    if (empty($ownerDns) && !empty($group->owners_group_id)) {
      try {
        $GroupMembers = TableRegistry::getTableLocator()->get('GroupMembers');

        $rows = $GroupMembers->find()
          ->select(['person_id'])
          ->where([
            'GroupMembers.group_id' => (int)$group->owners_group_id,
            'GroupMembers.deleted' => false,
          ])
          ->enableHydration(false)
          ->all()
          ->toArray();

        foreach ($rows as $r) {
          if (empty($r['person_id'])) {
            continue;
          }

          $dn = $this->resolvePersonDn((int)$r['person_id'], $ldapProvisionerId);
          if (!empty($dn)) {
            $ownerDns[] = $dn;
          }
        }
      } catch (\Throwable $e) {
        // best-effort
      }
    }

    $ownerDns = array_values(array_unique(array_filter($ownerDns, static fn($v) => $v !== '')));

    if (!empty($ownerDns)) {
      return $ownerDns;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Resolve person DN via LdapProvisionerDns.
   *
   * @param int $personId Person ID.
   * @param int $ldapProvisionerId Provisioner ID.
   * @return string|null Resolved DN, or null.
   */
  protected function resolvePersonDn(int $personId, int $ldapProvisionerId): ?string
  {
    if ($personId < 1) {
      return null;
    }

    $LdapProvisionerDns = TableRegistry::getTableLocator()->get('LdapConnector.LdapProvisionerDns');

    $row = $LdapProvisionerDns->find()
      ->select(['dn'])
      ->where([
        'LdapProvisionerDns.ldap_provisioner_id' => $ldapProvisionerId,
        'LdapProvisionerDns.person_id' => $personId,
      ])
      ->enableHydration(false)
      ->first();

    return (!empty($row['dn']) ? (string)$row['dn'] : null);
  }

  /**
   * Default validation rules.
   *
   * @param Validator $validator Validator instance to be modified.
   * @return Validator Modified validator instance with additional rules.
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('ldap_schema_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('ldap_schema_id');

    foreach (['cn', 'member', 'owner', 'description'] as $f) {
      $validator->add($f, [
        'content' => ['rule' => 'boolean']
      ]);
      $validator->allowEmptyString($f);
    }

    return $validator;
  }

  /**
   * The LDAP objectclass this schema model manages.
   *
   * @return string Objectclass name managed by this schema.
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function ldapObjectClass(): string {
    return 'groupOfNames';
  }
}
