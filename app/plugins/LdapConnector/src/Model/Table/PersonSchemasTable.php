<?php
/**
 * COmanage Registry LDAP Connector Schema Person Table
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
use App\Model\Entity\Person;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class PersonSchemasTable extends Table {
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
   * and permissions for Person schema.
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
   * Default schema values for Person.
   *
   * Required attributes (cn, sn) are enabled by default.
   *
   * @return array<string, mixed> Column defaults
   * @since  COmanage Registry v5.3.0
   */
  public function getDefaults(): array {
    return [
      'cn' => true,
      'sn' => true,
    ];
  }

  /**
   * Hook to assemble LDAP attributes for the person schema.
   *
   * The person structural objectclass (RFC 4519) represents human individuals in the directory
   * (People only). Attributes are exported based on the direct column configuration of the schema entity:
   *
   * Supported attributes and rationale:
   * - cn (Common Name):
   *   - Single-valued. Enabled via boolean flag `cn` (required by RFC 4519).
   *   - Derived from the Person's primary name (prefers `full_name`; fallback to `given + ' ' + family`, then '.').
   *     Included as the mandatory common name attribute for person entries.
   * - sn (Surname):
   *   - Single-valued. Enabled via boolean flag `sn` (required by RFC 4519).
   *   - Derived from the Person's primary name family (fallback: '.').
   *     Included as the mandatory surname attribute for person entries.
   * - userPassword:
   *   - Multi-valued. Enabled via boolean flag `user_password`.
   *   - Derived from active password records (`$person->passwords`) provisioned via PasswordAuthenticator
   *     matching SSHA type (`type = 'SH'`), formatted with `{SSHA}` prefix.
   *     Included to support direct LDAP bind authentication.
   * - pwdAccountLockedTime:
   *   - Single-valued. Enabled via boolean flag `pwd_account_locked_time`.
   *   - When the Person's status is Suspended or Expired, set to the permanent lock timestamp '000001010000Z'
   *     (used by OpenLDAP slapo-ppolicy and compatible directory servers to prevent authentication).
   *     On modify/rename, if the account is active/unlocked, emits an empty array (`pwdAccountLockedTime => []`)
   *     to remove the lock attribute from the directory entry.
   *
   * Operation semantics:
   * - On add ($op = 'add'): emits only attributes that have populated values.
   * - On modify or rename ($op = 'modify' | 'rename'): for attributes like `userPassword` or
   *   `pwdAccountLockedTime`, emits an empty array (`attr => []`) when clearing is required.
   * - Whenever attributes are contributed, ensures the `person` objectclass is present in `objectClass`.
   *
   * Example (typical add for an active Person):
   * <code>
   * [
   *   'objectClass'  => ['person'],
   *   'cn'           => 'Robert Andrews',
   *   'sn'           => 'Andrews',
   *   'userPassword' => ['{SSHA}w9jG4r8...hash...']
   * ]
   * </code>
   *
   * Example (modify where a suspended Person is unlocked, and passwords removed):
   * <code>
   * [
   *   'objectClass'          => ['person'],
   *   'cn'                   => 'Robert Andrews',
   *   'sn'                   => 'Andrews',
   *   'userPassword'         => [],
   *   'pwdAccountLockedTime' => []
   * ]
   * </code>
   *
   * Example (modify where an active Person is suspended/locked):
   * <code>
   * [
   *   'objectClass'          => ['person'],
   *   'cn'                   => 'Robert Andrews',
   *   'sn'                   => 'Andrews',
   *   'pwdAccountLockedTime' => '000001010000Z'
   * ]
   * </code>
   *
   * @param EntityInterface $schema Active person schema configuration entity.
   * @param EntityInterface $ldapProvisioner The LDAP provisioner configuration entity.
   * @param string $className Provisioned model name ('People' or 'Groups').
   * @param object $data Provisioned entity (eg: Person).
   * @param string $op Provisioning operation: 'add', 'modify', or 'rename'.
   * @return array<string,mixed> LDAP attributes for this schema.
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

    if ($className !== 'People' || !($data instanceof Person)) {
      return $ret;
    }

    $isModifyLike = ($op === 'modify' || $op === 'rename');

    if (!empty($schema->cn)) {
      $ret['cn'] = $this->assembleCn($data);
    }

    if (!empty($schema->sn)) {
      $ret['sn'] = $this->assembleSn($data);
    }

    if (!empty($schema->user_password)) {
      $pw = $this->assembleUserPassword($data, $isModifyLike);
      if ($pw !== null) {
        $ret['userPassword'] = $pw;
      }
    }

    if (!empty($schema->pwd_account_locked_time)) {
      $lock = $this->assemblePwdAccountLockedTime($data, $isModifyLike);
      if ($lock !== null) {
        $ret['pwdAccountLockedTime'] = $lock;
      }
    }

    $this->ensureSchemaObjectClass($ret);

    return $ret;
  }

  /**
   * Assemble the cn (Common Name) attribute from the Person's primary name.
   *
   * @param Person $person Provisioned Person entity.
   * @return string Common name.
   */
  protected function assembleCn(Person $person): string
  {
    $primaryName = $person->primary_name ?? null;

    if (is_object($primaryName)) {
      return (string)($primaryName->full_name ?? '');
    }

    return '';
  }

  /**
   * Assemble the sn (Surname) attribute from the Person's primary name family.
   *
   * @param Person $person Provisioned Person entity.
   * @return string Surname, or '.' if missing/blank.
   */
  protected function assembleSn(Person $person): string
  {
    $primaryName = $person->primary_name ?? null;

    if (is_object($primaryName) && isset($primaryName->family)) {
      $sn = (string)$primaryName->family;
      if ($sn !== '') {
        return $sn;
      }
    }

    return '.';
  }

  /**
   * Assemble the userPassword attribute from salted SHA1 passwords.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null Password values, empty array to clear on modify, or null if unchanged on add.
   */
  protected function assembleUserPassword(Person $person, bool $isModifyLike): ?array
  {
    $values = [];

    foreach (($person->passwords ?? []) as $p) {
      if (!is_object($p)) {
        continue;
      }

      $ptype = (string)($p->type ?? '');
      $pval  = (string)($p->password ?? '');

      if ($ptype === 'SH' && $pval !== '') {
        $prefix = str_starts_with($pval, '{SSHA}') ? '' : '{SSHA}';
        $values[] = $prefix . $pval;
      }
    }

    $values = array_values(array_unique(array_filter($values, static fn($v) => $v !== '')));

    if (!empty($values)) {
      return $values;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble the pwdAccountLockedTime attribute based on Person status.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return string|array<mixed>|null Lock timestamp, empty array to clear on modify, or null if unchanged on add.
   */
  protected function assemblePwdAccountLockedTime(Person $person, bool $isModifyLike): string|array|null
  {
    $status = (string)($person->status ?? '');

    $isLocked = (
      $status === StatusEnum::Expired
      || $status === StatusEnum::Suspended
    );

    if ($isLocked) {
      return '000001010000Z';
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Default validation rules.
   *
   * @param Validator $validator Validator instance to be modified.
   * @return Validator Modified validator instance with additional rules.
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function validationDefault(Validator $validator): Validator
  {
    $schema = $this->getSchema();

    $validator->add('ldap_schema_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('ldap_schema_id');

    foreach (['cn', 'sn', 'user_password', 'pwd_account_locked_time'] as $f) {
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
  public function ldapObjectClass(): string
  {
    return 'person';
  }
}
