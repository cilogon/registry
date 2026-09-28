<?php

declare(strict_types=1);

/**
 * COmanage Registry LDAP Connector Schema VO POSIX Account Table
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


namespace LdapConnector\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class VoPosixAccountSchemasTable extends Table {
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
   * and permissions for VO POSIX Account schema.
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
   * Default schema values for voPosixAccount.
   *
   * Required attributes (cn, uid, vo_posix_account_uid_number, vo_posix_account_gid_number, vo_posix_account_home_directory) are enabled by default.
   *
   * @return array<string, mixed> Column defaults
   * @since  COmanage Registry v5.3.0
   */
  public function getDefaults(): array {
    return [
      'cn'                              => true,
      'uid'                             => true,
      'vo_posix_account_uid_number'     => true,
      'vo_posix_account_gid_number'     => true,
      'vo_posix_account_home_directory' => true,
    ];
  }

  /**
   * Hook to assemble LDAP attributes for the voPosixAccount schema.
   *
   * The voPosixAccount auxiliary objectclass defines Unix/POSIX account attributes
   * within a Virtual Organization context (People only). Attributes are enabled via the direct
   * boolean columns of the schema configuration:
   *
   * Supported attributes and rationale:
   * - cn (Common Name):
   *   - Single-valued. Enabled via boolean flag `cn` (required).
   *   - User's common name for POSIX account identification.
   * - uid (User Login Name):
   *   - Single-valued. Enabled via boolean flag `uid` (required).
   *   - Unix username used to authenticate and log into POSIX systems.
   * - voPosixAccountUidNumber:
   *   - Single-valued integer string. Enabled via boolean flag `vo_posix_account_uid_number` (required).
   *   - VO-scoped numeric user ID for Unix/cluster access.
   * - voPosixAccountGidNumber:
   *   - Single-valued integer string. Enabled via boolean flag `vo_posix_account_gid_number` (required).
   *   - VO-scoped primary numeric group ID.
   * - voPosixAccountHomeDirectory:
   *   - Single-valued. Enabled via boolean flag `vo_posix_account_home_directory` (required).
   *   - Path to the user's home directory within the VO cluster environment (eg '/vo/home/jdoe').
   * - voPosixAccountLoginShell:
   *   - Single-valued. Enabled via boolean flag `vo_posix_account_login_shell`.
   *   - Default login shell for VO POSIX sessions (eg '/bin/bash').
   * - voPosixAccountGecos:
   *   - Single-valued. Enabled via boolean flag `vo_posix_account_gecos`.
   *   - User information string for VO POSIX systems.
   *
   * Implementation note:
   * - VO POSIX account attribute assembly is currently a stub pending VO POSIX service integration.
   *   It returns an empty array until active data source mapping is connected.
   *
   * Example (expected return format when populated for a Person):
   * <code>
   * [
   *   'objectClass'                 => ['voPosixAccount'],
   *   'voPosixAccountUidNumber'     => '20005',
   *   'voPosixAccountGidNumber'     => '2000',
   *   'voPosixAccountHomeDirectory' => '/vo/home/jdoe',
   *   'voPosixAccountLoginShell'    => '/bin/bash',
   *   'voPosixAccountGecos'         => 'Jane Doe [VO Project]'
   * ]
   * </code>
   *
   * @param EntityInterface $schema Active voPosixAccount schema configuration entity.
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
      'uid',
      'vo_posix_account_uid_number',
      'vo_posix_account_gid_number',
      'vo_posix_account_home_directory',
      'vo_posix_account_login_shell',
      'vo_posix_account_gecos'
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
    return 'voPosixAccount';
  }
}
