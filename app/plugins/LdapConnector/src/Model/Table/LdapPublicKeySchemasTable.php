<?php
/**
 * COmanage Registry LDAP Connector Schema LDAP Public Key Table
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

use App\Model\Entity\Person;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class LdapPublicKeySchemasTable extends Table {
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
   * and permissions for LDAP Public Key schema.
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
   * Default schema values for ldapPublicKey.
   *
   * Required attribute (ssh_public_key) is enabled by default.
   *
   * @return array<string, mixed> Column defaults
   * @since  COmanage Registry v5.3.0
   */
  public function getDefaults(): array {
    return [
      'ssh_public_key' => true,
    ];
  }

  /**
   * Hook to assemble LDAP attributes for the ldapPublicKey schema.
   *
   * The ldapPublicKey auxiliary objectclass exports public SSH keys to directory entries
   * for OpenSSH LDAP public-key authentication (People only).
   *
   * Supported attributes and rationale:
   * - sshPublicKey:
   *   - Multi-valued. Enabled via boolean flag `ssh_public_key` (enabled by default).
   *   - Extracted from active, non-deleted SSH key records (`$person->ssh_keys`) provisioned
   *     via the SshKeyAuthenticator plugin.
   *   - Formatted in OpenSSH wire format: "{type} {skey} [comment]" (eg 'ssh-ed25519 AAAAC3... jdoe@laptop').
   *   - Included to allow SSH servers configured with AuthorizedKeysCommand to look up user keys directly from LDAP.
   *
   * Operation semantics:
   * - On add ($op = 'add'): emits only attributes that have populated values.
   * - On modify or rename ($op = 'modify' | 'rename'): if `ssh_public_key` is enabled but no active
   *   keys exist on the person, emits an empty array (`sshPublicKey => []`) to request removal
   *   of previously provisioned keys in LDAP.
   * - Whenever attributes are contributed, ensures the `ldapPublicKey` objectclass is present in `objectClass`.
   *
   * Example (typical add for a Person with two SSH keys):
   * <code>
   * [
   *   'objectClass'  => ['ldapPublicKey'],
   *   'sshPublicKey' => [
   *     'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIGx... jdoe@laptop',
   *     'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC... jdoe@workstation'
   *   ]
   * ]
   * </code>
   *
   * Example (modify/rename where all keys have been revoked and must be cleared in LDAP):
   * <code>
   * [
   *   'objectClass'  => ['ldapPublicKey'],
   *   'sshPublicKey' => []
   * ]
   * </code>
   *
   * @param EntityInterface $schema Active ldapPublicKey schema configuration entity.
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
    $ret = [];

    // ldapPublicKey applies to People provisions only
    if ($className !== 'People' || !($data instanceof Person)) {
      return $ret;
    }

    $isModifyLike = ($op === 'modify' || $op === 'rename');

    if (!empty($schema->ssh_public_key)) {
      $keys = $this->assembleSshPublicKey($data, $isModifyLike);
      if ($keys !== null) {
        $ret['sshPublicKey'] = $keys;
      }
    }

    $this->ensureSchemaObjectClass($ret);

    return $ret;
  }

  /**
   * Assemble the sshPublicKey attribute from the Person's SSH keys.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null SSH public keys, empty array to clear on modify, or null if omitted on add.
   */
  protected function assembleSshPublicKey(Person $person, bool $isModifyLike): ?array
  {
    $values = [];

    foreach (($person->ssh_keys ?? []) as $sk) {
      if (!is_object($sk)) {
        continue;
      }

      // Best-effort "active" check: ignore deleted keys
      if (!empty($sk->deleted)) {
        continue;
      }

      $type = isset($sk->type) ? trim((string)$sk->type) : '';
      $skey = isset($sk->skey) ? trim((string)$sk->skey) : '';
      $comment = isset($sk->comment) ? trim((string)$sk->comment) : '';

      if ($type === '' || $skey === '') {
        continue;
      }

      // format: "{type} {skey} {comment}" (comment optional)
      $v = $type . ' ' . $skey;
      if ($comment !== '') {
        $v .= ' ' . $comment;
      }

      $values[] = $v;
    }

    $values = array_values(array_unique(array_filter($values, static fn($v) => $v !== '')));

    if (!empty($values)) {
      return $values;
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
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('ldap_schema_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('ldap_schema_id');

    $validator->add('ssh_public_key', [
      'content' => ['rule' => 'boolean']
    ]);
    $validator->allowEmptyString('ssh_public_key');

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
    return 'ldapPublicKey';
  }
}
