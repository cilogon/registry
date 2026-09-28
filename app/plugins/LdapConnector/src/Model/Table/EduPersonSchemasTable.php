<?php
/**
 * COmanage Registry LDAP Connector Schema eduPerson Table
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
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

class EduPersonSchemasTable extends Table
{
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
   * Sets up behaviors, associations, primary link, default contains, view vars for type pickers,
   * and permissions for eduPerson schema.
   *
   * @param array $config Table configuration options passed to the constructor.
   * @return void
   * @throws \Throwable
   * @since COmanage Registry v5.3.0
   */
  public function initialize(array $config): void
  {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->belongsTo('LdapConnector.LdapSchemas');
    $this->belongsTo('EduPersonNicknameTypes')
      ->setClassName('Types')
      ->setForeignKey('edu_person_nickname_type_id')
      ->setProperty('edu_person_nickname_type');
    $this->belongsTo('EduPersonPrincipalNameTypes')
      ->setClassName('Types')
      ->setForeignKey('edu_person_principal_name_type_id')
      ->setProperty('edu_person_principal_name_type');
    $this->belongsTo('EduPersonPrincipalNamePriorTypes')
      ->setClassName('Types')
      ->setForeignKey('edu_person_principal_name_prior_type_id')
      ->setProperty('edu_person_principal_name_prior_type');
    $this->belongsTo('EduPersonUniqueIdTypes')
      ->setClassName('Types')
      ->setForeignKey('edu_person_unique_id_type_id')
      ->setProperty('edu_person_unique_id_type');

    $this->hasManyPlugins([
      'Types' => [
        [
          'alias' => 'EduPersonNicknameTypes',
          'targetModel' => 'LdapConnector.EduPersonSchemas',
          'config' => [
            'foreignKey' => 'edu_person_nickname_type_id'
          ]
        ],
        [
          'alias' => 'EduPersonPrincipalNameTypes',
          'targetModel' => 'LdapConnector.EduPersonSchemas',
          'config' => [
            'foreignKey' => 'edu_person_principal_name_type_id'
          ]
        ],
        [
          'alias' => 'EduPersonPrincipalNamePriorTypes',
          'targetModel' => 'LdapConnector.EduPersonSchemas',
          'config' => [
            'foreignKey' => 'edu_person_principal_name_prior_type_id'
          ]
        ],
        [
          'alias' => 'EduPersonUniqueIdTypes',
          'targetModel' => 'LdapConnector.EduPersonSchemas',
          'config' => [
            'foreignKey' => 'edu_person_unique_id_type_id'
          ]
        ]
      ]
    ]);

    $this->setPrimaryLink(['LdapConnector.ldap_schema_id']);
    $this->setRequiresCO(true);

    $this->setViewContains([
      'LdapSchemas',
      'EduPersonNicknameTypes',
      'EduPersonPrincipalNameTypes',
      'EduPersonPrincipalNamePriorTypes',
      'EduPersonUniqueIdTypes'
    ]);
    $this->setEditContains([
      'LdapSchemas',
      'EduPersonNicknameTypes',
      'EduPersonPrincipalNameTypes',
      'EduPersonPrincipalNamePriorTypes',
      'EduPersonUniqueIdTypes'
    ]);
    $this->setIndexContains(['LdapSchemas']);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');

    // View vars for type pickers (Types-backed)
    $this->setAutoViewVars([
      'eduPersonNicknameTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type'
      ],
      'eduPersonPrincipalNameTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'eduPersonPrincipalNamePriorTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'eduPersonUniqueIdTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
    ]);

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
   * Default schema values for eduPerson.
   *
   * Optional multi-valued attributes initialize with disable toggles set to true (disabled).
   *
   * @return array<string, mixed> Column defaults
   * @since  COmanage Registry v5.3.0
   */
  public function getDefaults(): array
  {
    return [
      'edu_person_nickname'             => true,
      'edu_person_principal_name_prior' => true,
      'edu_person_unique_id_export'     => true,
    ];
  }

  /**
   * Hook to assemble LDAP attributes for the eduPerson schema.
   *
   * The eduPerson schema defines attributes for Higher Education and Research identity
   * management (People only). Attributes are exported based on the direct column configuration
   * of the schema entity:
   *
   * Supported attributes and rationale:
   * - eduPersonAffiliation:
   *   - Multi-valued. Enabled via boolean flag `edu_person_affiliation`.
   *   - Derived from valid Person Roles, mapping `affiliation_type` to `edupersonaffiliation`
   *     (or falling back to `value`). Included to describe the person's relationship to the
   *     institution (eg 'faculty', 'staff', 'student').
   * - eduPersonScopedAffiliation:
   *   - Multi-valued. Enabled via boolean flag `edu_person_scoped_affiliation`.
   *   - Derived from valid Person Roles, mapping `affiliation_type` to `edupersonaffiliation`
   *     (or falling back to `value`), formatted with the target scope suffix appended
   *     (eg 'staff@example.org'). Included to provide domain-scoped affiliation suitable for cross-realm federated access.
   * - eduPersonEntitlement:
   *   - Multi-valued. Enabled via boolean flag `edu_person_entitlement`.
   *   - Derived from the Person's active, valid group memberships (eg 'CO:members:active').
   *     Included to assert memberships and entitlements for federated authorization.
   * - eduPersonNickname:
   *   - Multi-valued. Enabled when `edu_person_nickname_type_id` is configured.
   *   - Derived from Name records matching the selected name type (eg 'preferred'), rendered as
   *     a common-name-like string (eg 'John Doe'). Included for preferred/informal name display.
   * - eduPersonOrcid:
   *   - Multi-valued. Enabled via boolean flag `edu_person_orcid`.
   *   - Derived from Identifier records of type 'orcid' (eg 'https://orcid.org/0000-0002-1825-0097').
   *     Included to link researcher identity with scholarly persistent identifiers.
   * - eduPersonPrincipalName:
   *   - Single-valued. Enabled when `edu_person_principal_name_type_id` is configured.
   *   - Derived from the primary matching Identifier record (eg 'eppn', formatted as 'user@scope').
   *     Included as the primary persistent, scoped identifier for federated authentication.
   * - eduPersonPrincipalNamePrior:
   *   - Multi-valued. Enabled when `edu_person_principal_name_prior_type_id` is configured.
   *   - Derived from Identifier records matching the configured type.
   *     Included to retain historical or superseded principal identifiers across account renames.
   * - eduPersonUniqueId:
   *   - Multi-valued. Enabled unless `edu_person_unique_id_export` disable flag is set; filtered by `edu_person_unique_id_type_id`.
   *   - Derived from Identifier records matching the configured type (or all if unselected), formatted with the
   *     scope suffix appended. Included to provide an opaque, non-reassignable unique identifier.
   *
   * Operation semantics:
   * - On add ($op = 'add'): emits only attributes that have populated values.
   * - On modify or rename ($op = 'modify' | 'rename'): for any enabled attribute that cannot be
   *   populated (e.g. nickname removed, affiliation expired), emits an empty array (`attr => []`)
   *   to request attribute removal/clearing in LDAP.
   * - Whenever attributes are contributed, ensures the `eduPerson` objectclass is present in `objectClass`.
   *
   * Example (typical add for a Person with scope "example.org"):
   * <code>
   * [
   *   'objectClass'                 => ['eduPerson'],
   *   'eduPersonAffiliation'        => ['staff', 'member'],
   *   'eduPersonScopedAffiliation'  => ['staff@example.org', 'member@example.org'],
   *   'eduPersonEntitlement'        => ['CO:members:active', 'CO:COU:Faculty:members:active'],
   *   'eduPersonNickname'           => ['John Doe'],
   *   'eduPersonOrcid'              => ['https://orcid.org/0000-0002-1825-0097'],
   *   'eduPersonPrincipalName'      => 'jdoe@example.org',
   *   'eduPersonPrincipalNamePrior' => ['john.doe@example.org'],
   *   'eduPersonUniqueId'           => ['12345678@example.org']
   * ]
   * </code>
   *
   * Example (modify/rename where previously set values are now absent and must be cleared in LDAP):
   * <code>
   * [
   *   'objectClass'                 => ['eduPerson'],
   *   'eduPersonNickname'           => [],
   *   'eduPersonOrcid'              => [],
   *   'eduPersonPrincipalNamePrior' => [],
   *   'eduPersonUniqueId'           => []
   * ]
   * </code>
   *
   * @param EntityInterface $schema Active eduPerson schema configuration entity.
   * @param EntityInterface $ldapProvisioner The LDAP provisioner configuration entity.
   * @param string $className Name of the model being provisioned ('People' or 'Groups').
   * @param object $data Provisioned entity (eg: Person).
   * @param string $op Provisioning operation: 'add', 'modify', or 'rename'.
   * @return array<string,mixed> LDAP attributes contributed by this schema (wire attribute names).
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

    // eduPerson only applies to People provisions
    if ($className !== 'People' || !($data instanceof Person)) {
      return $ret;
    }

    $isModifyLike = ($op === 'modify' || $op === 'rename');
    $coId = !empty($data->co_id) ? (int)$data->co_id : null;

    $scopeSuffix = $this->resolveScopeSuffix($ldapProvisioner);
    $scope = (!empty($scopeSuffix) ? ('@' . $scopeSuffix) : '');

    if (!empty($schema->edu_person_affiliation)) {
      $val = $this->assembleEduPersonAffiliation($data, $isModifyLike);
      if ($val !== null) {
        $ret['eduPersonAffiliation'] = $val;
      }
    }

    if (!empty($schema->edu_person_scoped_affiliation)) {
      $val = $this->assembleEduPersonScopedAffiliation($data, $scope, $isModifyLike);
      if ($val !== null) {
        $ret['eduPersonScopedAffiliation'] = $val;
      }
    }

    if (!empty($schema->edu_person_entitlement)) {
      $val = $this->assembleEduPersonEntitlement($data, $isModifyLike);
      if ($val !== null) {
        $ret['eduPersonEntitlement'] = $val;
      }
    }

    if (empty($schema->edu_person_nickname)) {
      $typeId = !empty($schema->edu_person_nickname_type_id) ? (int)$schema->edu_person_nickname_type_id : null;
      $val = $this->assembleEduPersonNickname($data, $typeId, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['eduPersonNickname'] = $val;
      }
    }

    if (!empty($schema->edu_person_orcid)) {
      $val = $this->assembleEduPersonOrcid($data, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['eduPersonOrcid'] = $val;
      }
    }

    if (!empty($schema->edu_person_principal_name_type_id)) {
      $val = $this->assembleEduPersonPrincipalName($data, (int)$schema->edu_person_principal_name_type_id, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['eduPersonPrincipalName'] = $val;
      }
    }

    if (empty($schema->edu_person_principal_name_prior)) {
      $typeId = !empty($schema->edu_person_principal_name_prior_type_id) ? (int)$schema->edu_person_principal_name_prior_type_id : null;
      $val = $this->assembleEduPersonPrincipalNamePrior($data, $typeId, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['eduPersonPrincipalNamePrior'] = $val;
      }
    }

    if (empty($schema->edu_person_unique_id_export)) {
      $typeId = !empty($schema->edu_person_unique_id_type_id) ? (int)$schema->edu_person_unique_id_type_id : null;
      $val = $this->assembleEduPersonUniqueId($data, $typeId, $scope, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['eduPersonUniqueId'] = $val;
      }
    }

    $this->ensureSchemaObjectClass($ret);

    return $ret;
  }

  /**
   * Assemble eduPersonAffiliation attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleEduPersonAffiliation(Person $person, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->person_roles ?? []) as $pr) {
      if (!is_object($pr)) {
        continue;
      }

      $v = null;

      if (!empty($pr->affiliation_type) && is_object($pr->affiliation_type)) {
        if (!empty($pr->affiliation_type->edupersonaffiliation)) {
          $v = (string)$pr->affiliation_type->edupersonaffiliation;
        } elseif (!empty($pr->affiliation_type->value)) {
          $v = (string)$pr->affiliation_type->value;
        }
      }

      if ($v !== null && $v !== '') {
        $vals[] = $v;
      }
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble eduPersonScopedAffiliation attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param string $scope Configured scope suffix with "@" prefix.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleEduPersonScopedAffiliation(Person $person, string $scope, bool $isModifyLike): ?array
  {
    $vals = [];

    if (!empty($scope)) {
      foreach (($person->person_roles ?? []) as $pr) {
        if (!is_object($pr)) {
          continue;
        }

        $v = null;

        if (!empty($pr->affiliation_type) && is_object($pr->affiliation_type)) {
          if (!empty($pr->affiliation_type->edupersonaffiliation)) {
            $v = (string)$pr->affiliation_type->edupersonaffiliation;
          } elseif (!empty($pr->affiliation_type->value)) {
            $v = (string)$pr->affiliation_type->value;
          }
        }

        if ($v !== null && $v !== '') {
          $vals[] = $v . $scope;
        }
      }
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble eduPersonEntitlement attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleEduPersonEntitlement(Person $person, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->group_members ?? []) as $gm) {
      if (!is_object($gm)) {
        continue;
      }

      if (!empty($gm->group) && is_object($gm->group) && !empty($gm->group->name)) {
        $vals[] = (string)$gm->group->name;
      }
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble eduPersonNickname attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Name type ID.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleEduPersonNickname(Person $person, ?int $targetTypeId, ?int $coId, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->names ?? []) as $n) {
      if (!is_object($n)) {
        continue;
      }

      if (!empty($targetTypeId)) {
        $nTypeId = (!empty($n->type_id) ? (int)$n->type_id : null);
        if (empty($nTypeId) || $nTypeId !== $targetTypeId) {
          continue;
        }
      }

      $rendered = $this->renderName($n);
      if ($rendered !== '') {
        $vals[] = $rendered;
      }
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble eduPersonOrcid attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleEduPersonOrcid(Person $person, ?int $coId, bool $isModifyLike): ?array
  {
    $targetTypeId = $this->resolveTypeId($coId, 'Identifiers.type', 'orcid');

    $vals = [];

    foreach (($person->identifiers ?? []) as $id) {
      if (!is_object($id) || empty($id->identifier)) {
        continue;
      }

      if (!empty($targetTypeId)) {
        $idTypeId = (!empty($id->type_id) ? (int)$id->type_id : null);
        if (empty($idTypeId) || $idTypeId !== $targetTypeId) {
          continue;
        }
      }

      $vals[] = (string)$id->identifier;
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble eduPersonPrincipalName attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Identifier type ID.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return string|array<mixed>|null
   */
  protected function assembleEduPersonPrincipalName(Person $person, ?int $targetTypeId, ?int $coId, bool $isModifyLike): string|array|null
  {
    if (empty($targetTypeId)) {
      $targetTypeId = $this->resolveTypeId($coId, 'Identifiers.type', 'eppn');
    }

    $val = null;

    foreach (($person->identifiers ?? []) as $id) {
      if (!is_object($id) || empty($id->identifier)) {
        continue;
      }

      if (!empty($targetTypeId)) {
        $idTypeId = (!empty($id->type_id) ? (int)$id->type_id : null);
        if (empty($idTypeId) || $idTypeId !== $targetTypeId) {
          continue;
        }
      }

      $val = (string)$id->identifier;
      break;
    }

    if (!empty($val)) {
      return $val;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble eduPersonPrincipalNamePrior attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Identifier type ID.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleEduPersonPrincipalNamePrior(Person $person, ?int $targetTypeId, ?int $coId, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->identifiers ?? []) as $id) {
      if (!is_object($id) || empty($id->identifier)) {
        continue;
      }

      if (!empty($targetTypeId)) {
        $idTypeId = (!empty($id->type_id) ? (int)$id->type_id : null);
        if (empty($idTypeId) || $idTypeId !== $targetTypeId) {
          continue;
        }
      }

      $vals[] = (string)$id->identifier;
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble eduPersonUniqueId attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Identifier type ID.
   * @param string $scope Configured scope suffix with "@" prefix.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleEduPersonUniqueId(Person $person, ?int $targetTypeId, string $scope, ?int $coId, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->identifiers ?? []) as $id) {
      if (!is_object($id) || empty($id->identifier)) {
        continue;
      }

      if (!empty($targetTypeId)) {
        $idTypeId = (!empty($id->type_id) ? (int)$id->type_id : null);
        if (empty($idTypeId) || $idTypeId !== $targetTypeId) {
          continue;
        }
      }

      $identifierValue = trim((string)$id->identifier);
      if ($identifierValue === '') {
        continue;
      }

      // If the identifier already includes a scope (contains "@"), use it as-is.
      if (str_contains($identifierValue, '@')) {
        $vals[] = $identifierValue;
        continue;
      }

      // Otherwise, append the configured scope (if available).
      if (!empty($scope)) {
        $vals[] = $identifierValue . $scope;
        continue;
      }
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Helper: resolve scope suffix for scoped eduPerson attributes (if configured).
   *
   * @param EntityInterface $ldapProvisioner The LDAP provisioner configuration entity.
   * @return string|null Scope suffix (without leading "@"), or null if not configured.
   */
  protected function resolveScopeSuffix(EntityInterface $ldapProvisioner): ?string
  {
    $v = $ldapProvisioner->scope_suffix ?? null;
    $v = ($v !== null ? trim((string)$v) : '');
    return ($v !== '' ? $v : null);
  }

  /**
   * Default validation rules.
   *
   * @param Validator $validator Validator instance to be modified.
   * @return Validator Modified validator instance with additional rules.
   * @throws \Throwable
   * @since COmanage Registry v5.3.0
   */
  public function validationDefault(Validator $validator): Validator
  {
    $schema = $this->getSchema();

    $validator->add('ldap_schema_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('ldap_schema_id');

    foreach ([
      'edu_person_affiliation',
      'edu_person_entitlement',
      'edu_person_nickname',
      'edu_person_orcid',
      'edu_person_principal_name_prior',
      'edu_person_scoped_affiliation',
      'edu_person_unique_id_export'
    ] as $f) {
      $validator->add($f, [
        'content' => ['rule' => 'boolean']
      ]);
      $validator->allowEmptyString($f);
    }

    foreach ([
      'edu_person_nickname_type_id',
      'edu_person_principal_name_type_id',
      'edu_person_principal_name_prior_type_id',
      'edu_person_unique_id_type_id'
    ] as $f) {
      $validator->add($f, [
        'content' => ['rule' => 'isInteger']
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
   * @since COmanage Registry v5.3.0
   */
  public function ldapObjectClass(): string
  {
    return 'eduPerson';
  }
}
