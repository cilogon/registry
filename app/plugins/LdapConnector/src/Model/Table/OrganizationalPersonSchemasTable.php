<?php
/**
 * COmanage Registry LDAP Connector Schema Organizational Person Table
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

class OrganizationalPersonSchemasTable extends Table
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
   * @param array $config Table configuration options passed to the constructor.
   * @return void
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function initialize(array $config): void
  {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->belongsTo('LdapConnector.LdapSchemas');
    $this->belongsTo('TelephoneNumberTypes')
      ->setClassName('Types')
      ->setForeignKey('telephone_number_type_id')
      ->setProperty('telephone_number_type');
    $this->belongsTo('FacsimileTelephoneNumberTypes')
      ->setClassName('Types')
      ->setForeignKey('facsimile_telephone_number_type_id')
      ->setProperty('facsimile_telephone_number_type');
    $this->belongsTo('AddressTypes')
      ->setClassName('Types')
      ->setForeignKey('address_type_id')
      ->setProperty('address_type');

    $this->hasManyPlugins([
      'Types' => [
        [
          'alias' => 'OrgPersonTelephoneNumberTypes',
          'targetModel' => 'LdapConnector.OrganizationalPersonSchemas',
          'config' => [
            'foreignKey' => 'telephone_number_type_id'
          ]
        ],
        [
          'alias' => 'OrgPersonFacsimileTelephoneNumberTypes',
          'targetModel' => 'LdapConnector.OrganizationalPersonSchemas',
          'config' => [
            'foreignKey' => 'facsimile_telephone_number_type_id'
          ]
        ],
        [
          'alias' => 'OrgPersonAddressTypes',
          'targetModel' => 'LdapConnector.OrganizationalPersonSchemas',
          'config' => [
            'foreignKey' => 'address_type_id'
          ]
        ]
      ]
    ]);

    $this->setPrimaryLink(['LdapConnector.ldap_schema_id']);
    $this->setRequiresCO(true);

    $this->setViewContains(['LdapSchemas', 'TelephoneNumberTypes', 'FacsimileTelephoneNumberTypes', 'AddressTypes']);
    $this->setEditContains(['LdapSchemas', 'TelephoneNumberTypes', 'FacsimileTelephoneNumberTypes', 'AddressTypes']);
    $this->setIndexContains(['LdapSchemas']);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');

    $this->setAutoViewVars([
      'telephoneNumberTypes' => [
        'type' => 'type',
        'attribute' => 'TelephoneNumbers.type'
      ],
      'facsimileTelephoneNumberTypes' => [
        'type' => 'type',
        'attribute' => 'TelephoneNumbers.type'
      ],
      'addressTypes' => [
        'type' => 'type',
        'attribute' => 'Addresses.type'
      ]
    ]);

    $this->setPermissions([
      'entity' => [
        'delete' => ['platformAdmin', 'coAdmin'],
        'edit'   => ['platformAdmin', 'coAdmin'],
        'view'   => ['platformAdmin', 'coAdmin'],
      ],
      'table' => [
        'add'     => ['platformAdmin', 'coAdmin'],
        'index'   => ['platformAdmin', 'coAdmin'],
        'deleted' => ['platformAdmin', 'coAdmin'],
      ],
    ]);
  }

  /**
   * Default schema values for OrganizationalPerson.
   *
   * Optional multi-valued attributes initialize with disable toggles set to true (disabled).
   *
   * @return array<string, mixed> Column defaults
   * @since  COmanage Registry v5.3.0
   */
  public function getDefaults(): array
  {
    return [
      'telephone_number'           => true,
      'facsimile_telephone_number' => true,
    ];
  }

  /**
   * Hook to assemble LDAP attributes for the organizationalPerson schema.
   *
   * The organizationalPerson structural objectclass (RFC 4519) represents organizational employees
   * and members (People only). All attributes in this schema are scoped to the Person's valid Person Roles
   * (`person_roles`).
   *
   * Supported attributes and rationale:
   * - title:
   *   - Multi-valued. Enabled via boolean flag `title`.
   *   - Derived from `person_roles.title`. Included to publish job title or organizational role.
   * - ou (Organizational Unit):
   *   - Multi-valued. Enabled via boolean flag `ou`.
   *   - Derived from `person_roles.department` (or associated COU name). Included to publish department or division.
   * - telephoneNumber:
   *   - Multi-valued. Enabled when `telephone_number_type_id` is configured.
   *   - Derived from TelephoneNumber records attached to valid Person Roles matching the selected telephone type (eg 'office').
   *     Included to publish role-specific office telephone numbers.
   * - facsimileTelephoneNumber:
   *   - Multi-valued. Enabled when `facsimile_telephone_number_type_id` is configured.
   *   - Derived from TelephoneNumber records attached to valid Person Roles matching the selected fax type (eg 'fax').
   *     Included to publish role-specific fax numbers.
   * - street:
   *   - Multi-valued. Enabled via boolean flag `street` with type filtering via `address_type_id`.
   *   - Derived from Address records attached to valid Person Roles matching the selected address type (eg 'office'),
   *     taking the `street` field. Included to publish office physical street address.
   * - l (Locality):
   *   - Multi-valued. Enabled via boolean flag `l` with type filtering via `address_type_id`.
   *   - Derived from Address records on valid Person Roles taking the `locality` field.
   *     Included to publish office city/locality.
   * - st (State or Province):
   *   - Multi-valued. Enabled via boolean flag `st` with type filtering via `address_type_id`.
   *   - Derived from Address records on valid Person Roles taking the `state` field.
   *     Included to publish office state or region.
   * - postalCode:
   *   - Multi-valued. Enabled via boolean flag `postal_code` with type filtering via `address_type_id`.
   *   - Derived from Address records on valid Person Roles taking the `postal_code` field.
   *     Included to publish office postal/ZIP code.
   *
   * Role Scoping and Attribute Options:
   * - When attribute options are enabled on the provisioning target (`attr_opts = true`), each role's values
   *   are tagged with the role identifier (eg `title;role-101`, `telephoneNumber;role-101`).
   * - When attribute options are disabled, attributes are aggregated under their standard base name.
   *
   * Operation semantics:
   * - On add ($op = 'add'): emits only attributes that have populated values.
   * - On modify or rename ($op = 'modify' | 'rename'): for any enabled attribute where no active role
   *   values exist, emits an empty array (`attr => []`) to request attribute removal/clearing in LDAP.
   * - Whenever attributes are contributed, ensures the `organizationalPerson` objectclass is present in `objectClass`.
   *
   * Example (typical add with attribute options disabled):
   * <code>
   * [
   *   'objectClass'              => ['organizationalPerson'],
   *   'title'                    => ['Associate Professor'],
   *   'ou'                       => ['Computer Science'],
   *   'telephoneNumber'          => ['+1 555 0199'],
   *   'facsimileTelephoneNumber' => ['+1 555 0198'],
   *   'street'                   => ['200 Science Lab'],
   *   'l'                        => ['Anytown'],
   *   'st'                       => ['CA'],
   *   'postalCode'               => ['90210']
   * ]
   * </code>
   *
   * Example (typical add with attribute options enabled for multi-role user):
   * <code>
   * [
   *   'objectClass'                  => ['organizationalPerson'],
   *   'title;role-701'               => ['Associate Professor'],
   *   'ou;role-701'                  => ['Computer Science'],
   *   'telephoneNumber;role-701'     => ['+1 555 0199'],
   *   'street;role-701'              => ['200 Science Lab'],
   *   'title;role-702'               => ['Lab Director'],
   *   'ou;role-702'                  => ['Robotics Institute']
   * ]
   * </code>
   *
   * Example (modify/rename where role address was removed and must be cleared in LDAP):
   * <code>
   * [
   *   'objectClass' => ['organizationalPerson'],
   *   'street'      => [],
   *   'l'           => [],
   *   'st'          => [],
   *   'postalCode'  => []
   * ]
   * </code>
   *
   * @param EntityInterface $schema Active organizationalPerson schema configuration entity.
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

    if ($className !== 'People' || !($data instanceof Person)) {
      return $ret;
    }

    $roles = $data->person_roles ?? null;

    $rolesEmpty = (
      $roles === null
      || is_scalar($roles)
      || ($roles instanceof \Countable && count($roles) === 0)
    );

    if ($rolesEmpty) {
      return $ret;
    }

    $isModifyLike = ($op === 'modify' || $op === 'rename');
    $attropts = $this->attributeOptionsEnabled($ldapProvisioner);

    $roleFields = [
      'title' => ['attr' => 'title', 'candidates' => ['title']],
      'ou'    => ['attr' => 'ou',    'candidates' => ['cou', 'department']],
    ];

    foreach ($roleFields as $flag => $cfg) {
      if (!empty($schema->$flag)) {
        $this->mergeAttributeMap(
          $ret,
          $this->assembleRoleScopedRoleField(
            person: $data,
            baseAttr: $cfg['attr'],
            fieldCandidates: $cfg['candidates'],
            attropts: $attropts,
            isModifyLike: $isModifyLike
          )
        );
      }
    }

    $telephoneFields = [
      'telephone_number'           => ['attr' => 'telephoneNumber',          'typeCol' => 'telephone_number_type_id'],
      'facsimile_telephone_number' => ['attr' => 'facsimileTelephoneNumber', 'typeCol' => 'facsimile_telephone_number_type_id'],
    ];

    foreach ($telephoneFields as $flag => $cfg) {
      if (empty($schema->$flag)) {
        $typeCol = $cfg['typeCol'];
        $targetTypeId = !empty($schema->$typeCol) ? (int)$schema->$typeCol : null;
        $this->mergeAttributeMap(
          $ret,
          $this->assembleRoleScopedTelephones(
            person: $data,
            baseAttr: $cfg['attr'],
            targetTypeId: $targetTypeId,
            attropts: $attropts,
            isModifyLike: $isModifyLike
          )
        );
      }
    }

    $addressTypeId = !empty($schema->address_type_id) ? (int)$schema->address_type_id : null;

    $addressFields = [
      'street'      => ['attr' => 'street',     'field' => 'street'],
      'l'           => ['attr' => 'l',          'field' => 'locality'],
      'st'          => ['attr' => 'st',         'field' => 'state'],
      'postal_code' => ['attr' => 'postalCode', 'field' => 'postal_code'],
    ];

    foreach ($addressFields as $flag => $cfg) {
      if (!empty($schema->$flag)) {
        $this->mergeAttributeMap(
          $ret,
          $this->assembleRoleScopedAddresses(
            person: $data,
            baseAttr: $cfg['attr'],
            entityField: $cfg['field'],
            targetTypeId: $addressTypeId,
            attropts: $attropts,
            isModifyLike: $isModifyLike
          )
        );
      }
    }

    $this->ensureSchemaObjectClass($ret);

    return $ret;
  }

  /**
   * Assemble a role-scoped attribute from a field on PersonRole (eg title/ou).
   *
   * @return array<string,mixed>
   * @since COmanage Registry v5.3.0
   */
  protected function assembleRoleScopedRoleField(
    Person $person,
    string $baseAttr,
    array $fieldCandidates,
    bool $attropts,
    bool $isModifyLike
  ): array {
    $out = [];
    $found = false;

    foreach (($person->person_roles ?? []) as $pr) {
      if (!is_object($pr) || empty($pr->id)) {
        continue;
      }

      $val = $this->roleFieldValue($pr, $fieldCandidates);

      if ($val === null) {
        continue;
      }

      $attrKey = $this->roleScopedAttrKey($baseAttr, (int)$pr->id, $attropts, null);
      $out[$attrKey][] = $val;
      $found = true;
    }

    $out = $this->normalizeAttributeMap($out);

    if (!$found && $isModifyLike) {
      $out[$baseAttr] = [];
    }

    return $out;
  }

  /**
   * Extract the value from a role using the ordered list of candidate fields.
   *
   * Supports:
   * - scalar/Stringable fields (eg "title", "department")
   * - object fields where we prefer ->name, then ->description (eg "cou")
   *
   * @param object $role PersonRole-like object
   * @param array<int,string> $fieldCandidates
   * @return string|null
   * @since COmanage Registry v5.3.0
   */
  private function roleFieldValue(object $role, array $fieldCandidates): ?string
  {
    foreach ($fieldCandidates as $f) {
      if (!isset($role->$f)) {
        continue;
      }

      $candidate = $role->$f;

      // Plain scalar or Stringable -> string
      if (is_scalar($candidate)) {
        $s = trim((string)$candidate);
        if ($s !== '') {
          return $s;
        }
        continue;
      }

      // Object -> prefer name, then description
      if (is_object($candidate)) {
        foreach (['name', 'description'] as $prop) {
          if (isset($candidate->$prop)) {
            $s = trim((string)$candidate->$prop);
            if ($s !== '') {
              return $s;
            }
          }
        }
      }
    }

    return null;
  }

  /**
   * Assemble a role-scoped telephone attribute (telephoneNumber, facsimileTelephoneNumber).
   *
   * @return array<string,mixed>
   * @since COmanage Registry v5.3.0
   */
  protected function assembleRoleScopedTelephones(
    Person $person,
    string $baseAttr,
    ?int $targetTypeId,
    bool $attropts,
    bool $isModifyLike
  ): array {
    $out = [];
    $found = false;

    foreach (($person->person_roles ?? []) as $pr) {
      if (!is_object($pr) || empty($pr->id)) {
        continue;
      }

      foreach (($pr->telephone_numbers ?? []) as $tn) {
        if (!is_object($tn)) {
          continue;
        }

        if ($targetTypeId !== null && $tn->type_id != $targetTypeId) {
          continue;
        }

        $val = $this->telephoneExportValue($tn);
        if ($val === null || $val === '') {
          continue;
        }

        $attrKey = $this->roleScopedAttrKey($baseAttr, (int)$pr->id, $attropts, null);

        $out[$attrKey][] = $val;
        $found = true;
      }
    }

    $out = $this->normalizeAttributeMap($out);

    if (!$found && $isModifyLike) {
      $out[$baseAttr] = [];
    }

    return $out;
  }

  /**
   * Assemble a role-scoped address attribute (street/l/st/postalCode).
   *
   * @return array<string,mixed>
   * @since COmanage Registry v5.3.0
   */
  protected function assembleRoleScopedAddresses(
    Person $person,
    string $baseAttr,
    string $entityField,
    ?int $targetTypeId,
    bool $attropts,
    bool $isModifyLike
  ): array {
    $out = [];
    $found = false;

    foreach (($person->person_roles ?? []) as $pr) {
      if (!is_object($pr) || empty($pr->id)) {
        continue;
      }

      foreach (($pr->addresses ?? []) as $addr) {
        if (!is_object($addr)) {
          continue;
        }

        if ($targetTypeId !== null && $addr->type_id != $targetTypeId) {
          continue;
        }

        $val = (string)($addr->$entityField ?? '');
        if ($val === '') {
          continue;
        }

        $lang = (!empty($addr->language) ? (string)$addr->language : null);
        $attrKey = $this->roleScopedAttrKey($baseAttr, (int)$pr->id, $attropts, $lang);

        $out[$attrKey][] = $val;
        $found = true;
      }
    }

    $out = $this->normalizeAttributeMap($out);

    if (!$found && $isModifyLike) {
      $out[$baseAttr] = [];
    }

    return $out;
  }

  /**
   * Build an attribute key honoring attribute options:
   * - baseAttr
   * - optionally ;role-{id}
   * - optionally ;lang-{code}
   *
   * @since COmanage Registry v5.3.0
   */
  private function roleScopedAttrKey(string $baseAttr, int $roleId, bool $attropts, ?string $lang): string
  {
    if (!$attropts) {
      return $baseAttr;
    }

    $k = $baseAttr . ';role-' . $roleId;

    $lang = ($lang !== null) ? trim($lang) : null;
    if (!empty($lang)) {
      $k .= ';lang-' . $lang;
    }

    return $k;
  }

  /**
   * Telephone export uses the entity virtual formatted_number when available.
   *
   * @since COmanage Registry v5.3.0
   */
  private function telephoneExportValue(object $tn): ?string
  {
    if (isset($tn->formatted_number) && (string)$tn->formatted_number !== '') {
      return (string)$tn->formatted_number;
    }

    if (isset($tn->number) && (string)$tn->number !== '') {
      return (string)$tn->number;
    }

    return null;
  }

  /**
   * Normalize per-attribute arrays (dedupe, remove empty strings).
   *
   * @param array<string,mixed> $attrMap
   * @return array<string,mixed>
   * @since COmanage Registry v5.3.0
   */
  private function normalizeAttributeMap(array $attrMap): array
  {
    foreach (array_keys($attrMap) as $k) {
      if (!is_array($attrMap[$k])) {
        continue;
      }

      $vals = array_map(static fn($v) => (string)$v, $attrMap[$k]);
      $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

      $attrMap[$k] = $vals;
    }

    // Drop any empty arrays here; callers may re-add baseAttr => [] for modify-like clearing.
    foreach (array_keys($attrMap) as $k) {
      if (is_array($attrMap[$k]) && empty($attrMap[$k])) {
        unset($attrMap[$k]);
      }
    }

    return $attrMap;
  }

  /**
   * Merge attribute maps while preserving existing values.
   *
   * @param array<string,mixed> $into
   * @param array<string,mixed> $from
   * @return void
   * @since COmanage Registry v5.3.0
   */
  private function mergeAttributeMap(array &$into, array $from): void
  {
    foreach ($from as $k => $v) {
      if (!array_key_exists($k, $into)) {
        $into[$k] = $v;
        continue;
      }

      if (is_array($into[$k]) && is_array($v)) {
        $into[$k] = array_values(array_unique(array_merge($into[$k], $v)));
      } else {
        $into[$k] = $v;
      }
    }
  }

  /**
   * Default validation rules.
   *
   * @param Validator $validator Validator instance.
   * @return Validator
   * @since  COmanage Registry v5.3.0
   */
  public function validationDefault(Validator $validator): Validator
  {
    $schema = $this->getSchema();

    $validator->add('ldap_schema_id', [
      'content' => ['rule' => 'isInteger'],
    ]);
    $validator->notEmptyString('ldap_schema_id');

    foreach (['title', 'ou', 'telephone_number', 'facsimile_telephone_number', 'street', 'l', 'st', 'postal_code'] as $f) {
      $validator->add($f, [
        'content' => ['rule' => 'boolean']
      ]);
      $validator->allowEmptyString($f);
    }

    foreach (['telephone_number_type_id', 'facsimile_telephone_number_type_id', 'address_type_id'] as $f) {
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
   * @return string
   * @since  COmanage Registry v5.3.0
   */
  public function ldapObjectClass(): string
  {
    return 'organizationalPerson';
  }
}
