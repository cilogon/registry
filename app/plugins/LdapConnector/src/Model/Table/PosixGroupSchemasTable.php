<?php
/**
 * COmanage Registry LDAP Connector Schema POSIX Group Table
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

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class PosixGroupSchemasTable extends Table {
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
   * and permissions for POSIX Group schema.
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
   * Default schema values for POSIX Group.
   *
   * Required attributes (cn, gid_number) are enabled by default.
   *
   * @return array<string, mixed> Column defaults
   * @since  COmanage Registry v5.3.0
   */
  public function getDefaults(): array {
    return [
      'cn'         => true,
      'gid_number' => true,
    ];
  }

  /**
   * Hook to assemble LDAP attributes for the posixGroup schema.
   *
   * The posixGroup structural objectclass (RFC 2307) represents POSIX/Unix system groups
   * (Groups only). Attributes are enabled via the direct boolean columns of the schema configuration:
   *
   * Supported attributes and rationale:
   * - cn (Common Name):
   *   - Single-valued. Enabled via boolean flag `cn` (required by RFC 2307).
   *   - POSIX system group name used by operating system lookups (eg 'faculty', 'sysadmins').
   * - gidNumber (Numeric Group ID):
   *   - Single-valued integer string. Enabled via boolean flag `gid_number` (required by RFC 2307).
   *   - Unique numeric identifier for the POSIX group.
   * - memberUid:
   *   - Multi-valued. Enabled via boolean flag `member_uid`.
   *   - List of member login usernames (UIDs) belonging to this POSIX group.
   *
   * Implementation note:
   * - POSIX group attribute assembly is currently a stub pending POSIX service / Unix cluster
   *   integration. It returns an empty array until active data source mapping is connected.
   *
   * Example (expected return format when populated for a Group):
   * <code>
   * [
   *   'objectClass' => ['posixGroup'],
   *   'cn'          => 'sysadmins',
   *   'gidNumber'   => '1000',
   *   'memberUid'   => ['jdoe', 'asmith', 'bjones']
   * ]
   * </code>
   *
   * @param EntityInterface $schema Active posixGroup schema configuration entity.
   * @param EntityInterface $ldapProvisioner The LDAP provisioner configuration entity.
   * @param string $className Provisioned model name ('People' or 'Groups').
   * @param object $data Provisioned entity (eg: Group).
   * @param string $op Provisioning operation: 'add', 'modify', or 'rename'.
   * @return array<string,mixed> LDAP attributes contributed by this schema.
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
    return [];
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

    foreach (['cn', 'gid_number', 'member_uid'] as $f) {
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
    return 'posixGroup';
  }
}
