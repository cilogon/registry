<?php
/**
 * COmanage Registry LDAP Connector Schema inetOrgPerson Table
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

class InetOrgPersonSchemasTable extends Table
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
   * Sets behaviors, associations, primary link, default contains (including filtered attributes),
   * view vars for type pickers, and permissions for inetOrgPerson schema.
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
    $this->belongsTo('GivenNameTypes')
      ->setClassName('Types')
      ->setForeignKey('given_name_type_id')
      ->setProperty('given_name_type');
    $this->belongsTo('DisplayNameTypes')
      ->setClassName('Types')
      ->setForeignKey('display_name_type_id')
      ->setProperty('display_name_type');
    $this->belongsTo('LabeledUriTypes')
      ->setClassName('Types')
      ->setForeignKey('labeled_uri_type_id')
      ->setProperty('labeled_uri_type');
    $this->belongsTo('MailTypes')
      ->setClassName('Types')
      ->setForeignKey('mail_type_id')
      ->setProperty('mail_type');
    $this->belongsTo('MobileTypes')
      ->setClassName('Types')
      ->setForeignKey('mobile_type_id')
      ->setProperty('mobile_type');
    $this->belongsTo('EmployeeNumberTypes')
      ->setClassName('Types')
      ->setForeignKey('employee_number_type_id')
      ->setProperty('employee_number_type');
    $this->belongsTo('AddressTypes')
      ->setClassName('Types')
      ->setForeignKey('address_type_id')
      ->setProperty('address_type');
    $this->belongsTo('UidTypes')
      ->setClassName('Types')
      ->setForeignKey('uid_type_id')
      ->setProperty('uid_type');

    $this->hasManyPlugins([
      'Types' => [
        [
          'alias' => 'InetGivenNameTypes',
          'targetModel' => 'LdapConnector.InetOrgPersonSchemas',
          'config' => [
            'foreignKey' => 'given_name_type_id'
          ]
        ],
        [
          'alias' => 'InetDisplayNameTypes',
          'targetModel' => 'LdapConnector.InetOrgPersonSchemas',
          'config' => [
            'foreignKey' => 'display_name_type_id'
          ]
        ],
        [
          'alias' => 'InetLabeledUriTypes',
          'targetModel' => 'LdapConnector.InetOrgPersonSchemas',
          'config' => [
            'foreignKey' => 'labeled_uri_type_id'
          ]
        ],
        [
          'alias' => 'InetMailTypes',
          'targetModel' => 'LdapConnector.InetOrgPersonSchemas',
          'config' => [
            'foreignKey' => 'mail_type_id'
          ]
        ],
        [
          'alias' => 'InetMobileTypes',
          'targetModel' => 'LdapConnector.InetOrgPersonSchemas',
          'config' => [
            'foreignKey' => 'mobile_type_id'
          ]
        ],
        [
          'alias' => 'InetEmployeeNumberTypes',
          'targetModel' => 'LdapConnector.InetOrgPersonSchemas',
          'config' => [
            'foreignKey' => 'employee_number_type_id'
          ]
        ],
        [
          'alias' => 'InetAddressTypes',
          'targetModel' => 'LdapConnector.InetOrgPersonSchemas',
          'config' => [
            'foreignKey' => 'address_type_id'
          ]
        ],
        [
          'alias' => 'InetUidTypes',
          'targetModel' => 'LdapConnector.InetOrgPersonSchemas',
          'config' => [
            'foreignKey' => 'uid_type_id'
          ]
        ]
      ]
    ]);

    $this->setPrimaryLink(['LdapConnector.ldap_schema_id']);
    $this->setRequiresCO(true);

    $this->setViewContains([
      'LdapSchemas',
      'GivenNameTypes',
      'DisplayNameTypes',
      'LabeledUriTypes',
      'MailTypes',
      'MobileTypes',
      'EmployeeNumberTypes',
      'AddressTypes',
      'UidTypes'
    ]);
    $this->setEditContains([
      'LdapSchemas',
      'GivenNameTypes',
      'DisplayNameTypes',
      'LabeledUriTypes',
      'MailTypes',
      'MobileTypes',
      'EmployeeNumberTypes',
      'AddressTypes',
      'UidTypes'
    ]);
    $this->setIndexContains(['LdapSchemas']);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');

    // View vars for type pickers (Types-backed).
    $this->setAutoViewVars([
      'givenNameTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type',
      ],
      'displayNameTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type',
      ],
      'labeledUriTypes' => [
        'type' => 'type',
        'attribute' => 'Urls.type',
      ],
      'mailTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type',
      ],
      'mobileTypes' => [
        'type' => 'type',
        'attribute' => 'TelephoneNumbers.type',
      ],
      'employeeNumberTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type',
      ],
      'addressTypes' => [
        'type' => 'type',
        'attribute' => 'Addresses.type'
      ],
      'uidTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type',
      ],
      'identifierTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type',
      ],
      'nameTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type',
      ],
      'emailAddressTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type',
      ],
      'telephoneNumberTypes' => [
        'type' => 'type',
        'attribute' => 'TelephoneNumbers.type',
      ],
      'urlTypes' => [
        'type' => 'type',
        'attribute' => 'Urls.type',
      ],
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
   * Default schema values for inetOrgPerson.
   *
   * Optional multi-valued attributes initialize with disable toggles set to true (disabled).
   *
   * @return array<string, mixed> Column defaults
   * @since  COmanage Registry v5.3.0
   */
  public function getDefaults(): array
  {
    return [
      'given_name'  => true,
      'labeled_uri' => true,
      'mail'        => true,
      'mobile'      => true,
      'uid'         => true,
    ];
  }

  /**
   * Hook to assemble LDAP attributes for the inetOrgPerson schema.
   *
   * The inetOrgPerson structural objectclass (RFC 2798) represents people in directory services
   * (People only). Attributes are exported based on the direct column configuration of the schema entity:
   *
   * Supported attributes and rationale:
   * - givenName:
   *   - Multi-valued. Enabled when `given_name_type_id` is configured.
   *   - Derived from Name records matching the selected name type (eg 'official', 'preferred').
   *     Included to publish the person's given/first name.
   * - displayName:
   *   - Single-valued. Enabled when `display_name_type_id` is configured.
   *   - Derived from Name records matching the selected type (fallback: primary name), rendered
   *     as a full name string. Included to provide preferred display formatting in address books.
   * - o (Organization):
   *   - Multi-valued. Enabled via boolean flag `o`.
   *   - Derived from valid Person Roles `organization` values.
   *     Included to publish institutional or employing organization names.
   * - labeledURI:
   *   - Multi-valued. Enabled when `labeled_uri_type_id` is configured.
   *   - Derived from Url records matching the selected URL type, formatted as RFC 2079
   *     "URI [label]" (eg 'https://example.org/profile Profile').
   *     Included to publish website or profile links with human-readable labels.
   * - mail:
   *   - Multi-valued. Enabled when `mail_type_id` is configured.
   *   - Derived from EmailAddress records matching the selected email type (eg 'official').
   *     Included to publish primary contact email addresses.
   * - mobile:
   *   - Multi-valued. Enabled when `mobile_type_id` is configured.
   *   - Derived from TelephoneNumber records matching the selected telephone type (eg 'mobile'),
   *     searched across Person and contained Person Roles.
   *     Included to publish mobile/cellular phone numbers.
   * - employeeNumber:
   *   - Single-valued. Enabled when `employee_number_type_id` is configured.
   *   - Derived from Identifier records matching the configured type (eg 'enterprise' or 'eppn').
   *     Included to publish unique employee or institutional ID numbers.
   * - employeeType:
   *   - Multi-valued. Enabled via boolean flag `employee_type`.
   *   - Derived from valid Person Roles `affiliation_type->value` (unmapped string, eg 'staff', 'faculty').
   *     Included to publish employment classifications.
   * - roomNumber:
   *   - Multi-valued. Enabled via boolean flag `room_number` with type filtering via `address_type_id`.
   *   - Derived from Address records matching the configured address type (taking the `room` field).
   *     Included for physical office or room location routing.
   * - uid:
   *   - Multi-valued. Enabled unless `uid` disable flag is set; filtered by `uid_type_id` (or all if unselected).
   *   - Derived from Identifier records matching the selected identifier type (eg 'uid').
   *     Included as the user's primary login account username/handle in LDAP.
   *
   * Operation semantics:
   * - On add ($op = 'add'): emits only attributes that have populated values.
   * - On modify or rename ($op = 'modify' | 'rename'): for any enabled attribute that cannot be
   *   populated (e.g. mobile number deleted, room vacated), emits an empty array (`attr => []`)
   *   to request attribute removal/clearing in LDAP.
   * - Whenever attributes are contributed, ensures the `inetOrgPerson` objectclass is present in `objectClass`.
   *
   * Example (typical add for a Person):
   * <code>
   * [
   *   'objectClass'    => ['inetOrgPerson'],
   *   'givenName'      => ['Robert'],
   *   'displayName'    => 'Robert Andrews',
   *   'o'              => ['Example University'],
   *   'mail'           => ['robert.andrews@example.org'],
   *   'mobile'         => ['+1 555 0100'],
   *   'employeeNumber' => 'E1002345',
   *   'employeeType'   => ['staff'],
   *   'roomNumber'     => ['Bldg 1, Room 203'],
   *   'labeledURI'     => ['https://example.org/profile/ra Profile'],
   *   'uid'            => ['randrews']
   * ]
   * </code>
   *
   * Example (modify/rename where previously set values are now absent and must be cleared in LDAP):
   * <code>
   * [
   *   'objectClass' => ['inetOrgPerson'],
   *   'mobile'      => [],
   *   'roomNumber'  => [],
   *   'labeledURI'  => []
   * ]
   * </code>
   *
   * @param EntityInterface $schema Active inetOrgPerson schema configuration entity.
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

    // inetOrgPerson only applies to People provisions
    if ($className !== 'People' || !($data instanceof Person)) {
      return $ret;
    }

    $isModifyLike = ($op === 'modify' || $op === 'rename');
    $coId = !empty($data->co_id) ? (int)$data->co_id : null;

    if (empty($schema->given_name)) {
      $typeId = !empty($schema->given_name_type_id) ? (int)$schema->given_name_type_id : null;
      $val = $this->assembleGivenName($data, $typeId, $isModifyLike);
      if ($val !== null) {
        $ret['givenName'] = $val;
      }
    }

    if (!empty($schema->display_name_type_id)) {
      $val = $this->assembleDisplayName($data, (int)$schema->display_name_type_id, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['displayName'] = $val;
      }
    }

    if (!empty($schema->o)) {
      $val = $this->assembleO($data, $isModifyLike);
      if ($val !== null) {
        $ret['o'] = $val;
      }
    }

    if (empty($schema->labeled_uri)) {
      $typeId = !empty($schema->labeled_uri_type_id) ? (int)$schema->labeled_uri_type_id : null;
      $val = $this->assembleLabeledUri($data, $typeId, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['labeledURI'] = $val;
      }
    }

    if (empty($schema->mail)) {
      $typeId = !empty($schema->mail_type_id) ? (int)$schema->mail_type_id : null;
      $val = $this->assembleMail($data, $typeId, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['mail'] = $val;
      }
    }

    if (empty($schema->mobile)) {
      $typeId = !empty($schema->mobile_type_id) ? (int)$schema->mobile_type_id : null;
      $val = $this->assembleMobile($data, $typeId, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['mobile'] = $val;
      }
    }

    if (!empty($schema->employee_number_type_id)) {
      $val = $this->assembleEmployeeNumber($data, (int)$schema->employee_number_type_id, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['employeeNumber'] = $val;
      }
    }

    if (!empty($schema->employee_type)) {
      $val = $this->assembleEmployeeType($data, $isModifyLike);
      if ($val !== null) {
        $ret['employeeType'] = $val;
      }
    }

    if (!empty($schema->room_number)) {
      $addressTypeId = !empty($schema->address_type_id) ? (int)$schema->address_type_id : null;
      $val = $this->assembleRoomNumber($data, $addressTypeId, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['roomNumber'] = $val;
      }
    }

    if (empty($schema->uid)) {
      $typeId = !empty($schema->uid_type_id) ? (int)$schema->uid_type_id : null;
      $val = $this->assembleUid($data, $typeId, $coId, $isModifyLike);
      if ($val !== null) {
        $ret['uid'] = $val;
      }
    }

    $this->ensureSchemaObjectClass($ret);

    return $ret;
  }

  /**
   * Assemble givenName attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Name type ID.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleGivenName(Person $person, ?int $targetTypeId, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->names ?? []) as $n) {
      if (!is_object($n) || empty($n->given)) {
        continue;
      }

      if (!empty($targetTypeId)) {
        $nTypeId = (!empty($n->type_id) ? (int)$n->type_id : null);
        if (empty($nTypeId) || $nTypeId !== $targetTypeId) {
          continue;
        }
      }

      $vals[] = (string)$n->given;
    }

    if (empty($vals) && is_object($person->primary_name ?? null) && !empty($person->primary_name->given)) {
      $vals[] = (string)$person->primary_name->given;
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble displayName attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Name type ID.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return string|array<mixed>|null
   */
  protected function assembleDisplayName(Person $person, ?int $targetTypeId, ?int $coId, bool $isModifyLike): string|array|null
  {
    if (empty($targetTypeId)) {
      return $isModifyLike ? [] : null;
    }

    $chosen = null;

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

      $chosen = $n;
      break;
    }

    if ($chosen === null && is_object($person->primary_name ?? null)) {
      $chosen = $person->primary_name;
    }

    if ($chosen !== null) {
      $rendered = $this->renderName($chosen);
      if ($rendered !== '') {
        return $rendered;
      }
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble o attribute from Person roles.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleO(Person $person, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->person_roles ?? []) as $pr) {
      if (!is_object($pr)) {
        continue;
      }

      $org = $pr->organization ?? null;
      if ($org !== null && (string)$org !== '') {
        $vals[] = (string)$org;
      }
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble labeledURI attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Url type ID.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleLabeledUri(Person $person, ?int $targetTypeId, ?int $coId, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->urls ?? []) as $u) {
      if (!is_object($u) || empty($u->url)) {
        continue;
      }

      if (!empty($targetTypeId)) {
        $uTypeId = (!empty($u->type_id) ? (int)$u->type_id : null);
        if (empty($uTypeId) || $uTypeId !== $targetTypeId) {
          continue;
        }
      }

      $v = (string)$u->url;

      if (!empty($u->description)) {
        $v .= ' ' . (string)$u->description;
      }

      if ($v !== '') {
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
   * Assemble mail attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target EmailAddress type ID.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleMail(Person $person, ?int $targetTypeId, ?int $coId, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->email_addresses ?? []) as $em) {
      if (!is_object($em) || empty($em->mail)) {
        continue;
      }

      if (!empty($targetTypeId)) {
        $emTypeId = (!empty($em->type_id) ? (int)$em->type_id : null);
        if (empty($emTypeId) || $emTypeId !== $targetTypeId) {
          continue;
        }
      }

      $vals[] = (string)$em->mail;
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble mobile attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target TelephoneNumber type ID.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleMobile(Person $person, ?int $targetTypeId, ?int $coId, bool $isModifyLike): ?array
  {
    $vals = [];

    $consumeTelList = static function (array $tels) use (&$vals, $targetTypeId): void {
      foreach ($tels as $tn) {
        if (!is_object($tn) || empty($tn->number)) {
          continue;
        }

        if (!empty($targetTypeId)) {
          $tnTypeId = (!empty($tn->type_id) ? (int)$tn->type_id : null);
          if (empty($tnTypeId) || $tnTypeId !== $targetTypeId) {
            continue;
          }
        }

        $vals[] = (string)$tn->number;
      }
    };

    $consumeTelList((array)($person->telephone_numbers ?? []));

    foreach (($person->person_roles ?? []) as $pr) {
      if (!is_object($pr)) {
        continue;
      }
      $consumeTelList((array)($pr->telephone_numbers ?? []));
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble employeeNumber attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Identifier type ID.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return string|array<mixed>|null
   */
  protected function assembleEmployeeNumber(Person $person, ?int $targetTypeId, ?int $coId, bool $isModifyLike): string|array|null
  {
    if (empty($targetTypeId)) {
      return $isModifyLike ? [] : null;
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

    if ($val !== null && $val !== '') {
      return $val;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble employeeType attribute from Person roles.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleEmployeeType(Person $person, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->person_roles ?? []) as $pr) {
      if (!is_object($pr)) {
        continue;
      }

      $v = null;

      if (!empty($pr->affiliation_type)
        && is_object($pr->affiliation_type)
        && !empty($pr->affiliation_type->value)) {
        $v = (string)$pr->affiliation_type->value;
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
   * Assemble roomNumber attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Address type ID.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleRoomNumber(Person $person, ?int $targetTypeId, ?int $coId, bool $isModifyLike): ?array
  {
    $vals = [];

    $consumeAddrList = static function (array $addrs) use (&$vals, $targetTypeId): void {
      foreach ($addrs as $a) {
        if (!is_object($a)) {
          continue;
        }

        if (!empty($targetTypeId)) {
          $aTypeId = (!empty($a->type_id) ? (int)$a->type_id : null);
          if (empty($aTypeId) || $aTypeId !== $targetTypeId) {
            continue;
          }
        }

        if (!empty($a->room)) {
          $vals[] = (string)$a->room;
        }
      }
    };

    $consumeAddrList((array)($person->addresses ?? []));

    foreach (($person->person_roles ?? []) as $pr) {
      if (!is_object($pr)) {
        continue;
      }
      $consumeAddrList((array)($pr->addresses ?? []));
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble uid attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Identifier type ID.
   * @param int|null $coId CO ID for fallback type resolution.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleUid(Person $person, ?int $targetTypeId, ?int $coId, bool $isModifyLike): ?array
  {
    $vals = [];

    $consumeIdentifierList = static function (array $idents) use (&$vals, $targetTypeId): void {
      foreach ($idents as $id) {
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
    };

    $consumeIdentifierList((array)($person->identifiers ?? []));

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
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
      'content' => ['rule' => 'isInteger'],
    ]);
    $validator->notEmptyString('ldap_schema_id');

    foreach (['given_name', 'o', 'labeled_uri', 'mail', 'mobile', 'employee_type', 'room_number', 'uid'] as $f) {
      $validator->add($f, [
        'content' => ['rule' => 'boolean']
      ]);
      $validator->allowEmptyString($f);
    }

    foreach ([
      'given_name_type_id',
      'display_name_type_id',
      'labeled_uri_type_id',
      'mail_type_id',
      'mobile_type_id',
      'employee_number_type_id',
      'address_type_id',
      'uid_type_id'
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
    return 'inetOrgPerson';
  }
}
