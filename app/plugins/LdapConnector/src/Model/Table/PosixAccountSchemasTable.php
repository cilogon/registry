<?php
/**
 * COmanage Registry LDAP Connector Schema POSIX Account Table
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

class PosixAccountSchemasTable extends Table {
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
   * and permissions for POSIX Account schema.
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
   * Default schema values for POSIX Account.
   *
   * Required attributes (cn, uid, uid_number, gid_number, home_directory) are enabled by default.
   *
   * @return array<string, mixed> Column defaults
   * @since  COmanage Registry v5.3.0
   */
  public function getDefaults(): array {
    return [
      'cn'             => true,
      'uid'            => true,
      'uid_number'     => true,
      'gid_number'     => true,
      'home_directory' => true,
    ];
  }

  /**
   * Hook to assemble LDAP attributes for the posixAccount schema.
   *
   * The posixAccount auxiliary objectclass (RFC 2307) provides Unix/POSIX system account
   * information for individuals (People only). Attributes are enabled via the direct boolean
   * columns of the schema configuration:
   *
   * Supported attributes and rationale:
   * - cn (Common Name):
   *   - Single-valued. Enabled via boolean flag `cn` (required by RFC 2307).
   *   - User's common name for Unix account identification.
   * - uid (User Login Name):
   *   - Single-valued. Enabled via boolean flag `uid` (required by RFC 2307).
   *   - Unix username used to authenticate and log into POSIX systems.
   * - uidNumber (Numeric User ID):
   *   - Single-valued integer string. Enabled via boolean flag `uid_number` (required by RFC 2307).
   *   - Unique numeric identifier for the POSIX user account.
   * - gidNumber (Numeric Primary Group ID):
   *   - Single-valued integer string. Enabled via boolean flag `gid_number` (required by RFC 2307).
   *   - Numeric identifier of the user's primary POSIX group.
   * - homeDirectory:
   *   - Single-valued. Enabled via boolean flag `home_directory` (required by RFC 2307).
   *   - Absolute path to the user's home directory (eg '/home/jdoe').
   * - loginShell:
   *   - Single-valued. Enabled via boolean flag `login_shell`.
   *   - Default Unix shell path (eg '/bin/bash').
   * - gecos:
   *   - Single-valued. Enabled via boolean flag `gecos`.
   *   - General user account information string (full name, office, phone).
   * - userPassword:
   *   - Multi-valued. Enabled via boolean flag `user_password`.
   *   - Password hash for Unix authentication.
   *
   * Implementation note:
   * - POSIX account attribute assembly is currently a stub pending POSIX service / Unix cluster
   *   integration. It returns an empty array until active data source mapping is connected.
   *
   * Example (expected return format when populated for a Person):
   * <code>
   * [
   *   'objectClass'   => ['posixAccount'],
   *   'cn'            => 'Robert Andrews',
   *   'uid'           => 'randrews',
   *   'uidNumber'     => '10001',
   *   'gidNumber'     => '1000',
   *   'homeDirectory' => '/home/randrews',
   *   'loginShell'    => '/bin/bash',
   *   'gecos'         => 'Robert Andrews,Bldg 1 Room 203'
   * ]
   * </code>
   *
   * @param EntityInterface $schema Active posixAccount schema configuration entity.
   * @param EntityInterface $ldapProvisioner The LDAP provisioner configuration entity.
   * @param string $className Provisioned model name ('People' or 'Groups').
   * @param object $data Provisioned entity (eg: Person).
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

    foreach ([
      'cn',
      'uid_number',
      'gid_number',
      'user_password',
      'gecos',
      'login_shell',
      'home_directory'
    ] as $f) {
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
    return 'posixAccount';
  }
}
