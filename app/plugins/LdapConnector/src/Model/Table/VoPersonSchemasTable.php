<?php
/**
 * COmanage Registry LDAP Connector Schema voPerson Table
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

class VoPersonSchemasTable extends Table
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
    $this->belongsTo('VoPersonApplicationUidTypes')
      ->setClassName('Types')
      ->setForeignKey('vo_person_application_uid_type_id')
      ->setProperty('vo_person_application_uid_type');
    $this->belongsTo('VoPersonAuthorNameTypes')
      ->setClassName('Types')
      ->setForeignKey('vo_person_author_name_type_id')
      ->setProperty('vo_person_author_name_type');
    $this->belongsTo('VoPersonExternalIdTypes')
      ->setClassName('Types')
      ->setForeignKey('vo_person_external_id_type_id')
      ->setProperty('vo_person_external_id_type');
    $this->belongsTo('VoPersonIdTypes')
      ->setClassName('Types')
      ->setForeignKey('vo_person_id_type_id')
      ->setProperty('vo_person_id_type');
    $this->belongsTo('VoPersonSoridTypes')
      ->setClassName('Types')
      ->setForeignKey('vo_person_sorid_type_id')
      ->setProperty('vo_person_sorid_type');

    $this->hasManyPlugins([
      'Types' => [
        [
          'alias' => 'VoPersonApplicationUidTypes',
          'targetModel' => 'LdapConnector.VoPersonSchemas',
          'config' => [
            'foreignKey' => 'vo_person_application_uid_type_id'
          ]
        ],
        [
          'alias' => 'VoPersonAuthorNameTypes',
          'targetModel' => 'LdapConnector.VoPersonSchemas',
          'config' => [
            'foreignKey' => 'vo_person_author_name_type_id'
          ]
        ],
        [
          'alias' => 'VoPersonExternalIdTypes',
          'targetModel' => 'LdapConnector.VoPersonSchemas',
          'config' => [
            'foreignKey' => 'vo_person_external_id_type_id'
          ]
        ],
        [
          'alias' => 'VoPersonIdTypes',
          'targetModel' => 'LdapConnector.VoPersonSchemas',
          'config' => [
            'foreignKey' => 'vo_person_id_type_id'
          ]
        ],
        [
          'alias' => 'VoPersonSoridTypes',
          'targetModel' => 'LdapConnector.VoPersonSchemas',
          'config' => [
            'foreignKey' => 'vo_person_sorid_type_id'
          ]
        ]
      ]
    ]);

    $this->setPrimaryLink(['LdapConnector.ldap_schema_id']);
    $this->setRequiresCO(true);

    $this->setViewContains([
      'LdapSchemas',
      'VoPersonApplicationUidTypes',
      'VoPersonAuthorNameTypes',
      'VoPersonExternalIdTypes',
      'VoPersonIdTypes',
      'VoPersonSoridTypes'
    ]);
    $this->setEditContains([
      'LdapSchemas',
      'VoPersonApplicationUidTypes',
      'VoPersonAuthorNameTypes',
      'VoPersonExternalIdTypes',
      'VoPersonIdTypes',
      'VoPersonSoridTypes'
    ]);
    $this->setIndexContains(['LdapSchemas']);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');

    // View vars for type pickers (Types-backed)
    $this->setAutoViewVars([
      'voPersonApplicationUidTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type',
      ],
      'voPersonAuthorNameTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type',
      ],
      'voPersonExternalIdTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type',
      ],
      'voPersonIdTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type',
      ],
      'voPersonSoridTypes' => [
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
   * Default schema values for voPerson.
   *
   * @return array<string, mixed> Column defaults
   * @since  COmanage Registry v5.3.0
   */
  public function getDefaults(): array
  {
    return [
      'vo_person_application_uid'    => true,
      'vo_person_author_name'        => true,
      'vo_person_external_id_export' => true,
      'vo_person_id_export'          => true,
      'vo_person_sorid'              => true,
    ];
  }

  /**
   * Hook to assemble LDAP attributes for the voPerson schema.
   *
   * The voPerson auxiliary objectclass (REFEDS voPerson specification) defines attributes
   * for Virtual Organization (VO) identity management and research collaborations (People only).
   * Attributes are exported based on the direct column configuration of the schema entity:
   *
   * Supported attributes and rationale:
   * - voPersonAffiliation:
   *   - Multi-valued. Enabled via boolean flag `vo_person_affiliation`.
   *   - Derived from valid Person Roles `affiliation_type` (`edupersonaffiliation` or `value`).
   *     Included to describe the person's affiliation roles within the Virtual Organization.
   * - voPersonAuthorName:
   *   - Multi-valued. Enabled when `vo_person_author_name_type_id` is configured.
   *   - Derived from Name records matching the selected name type, rendered in bibliographic citation
   *     format (eg 'J. Doe'). Included for publication attribution and scholarly author identification.
   * - voPersonApplicationUID:
   *   - Multi-valued, application-scoped (eg 'voPersonApplicationUID;app-globus').
   *     Enabled unless `vo_person_application_uid` disable flag is set; filtered by `vo_person_application_uid_type_id`.
   *   - Derived from Identifier records matching the selected identifier type (or all if unselected).
   *     Included for service-specific account logins and cluster usernames.
   * - voPersonApplicationPassword:
   *   - Multi-valued, application-scoped (eg 'voPersonApplicationPassword;app-vpn').
   *     Enabled via boolean flag `vo_person_application_password`.
   *   - Derived from PasswordAuthenticator credentials mapped to services.
   *     Included for application-specific authentication credentials.
   * - voPersonExternalID:
   *   - Multi-valued. Enabled unless `vo_person_external_id_export` disable flag is set; filtered by `vo_person_external_id_type_id`.
   *   - Derived from Identifier records matching the configured type (or all if unselected).
   *     Included to link external federated identity provider identifiers.
   * - voPersonID:
   *   - Multi-valued. Enabled unless `vo_person_id_export` disable flag is set; filtered by `vo_person_id_type_id`.
   *   - Derived from Identifier records matching the configured type (or all if unselected).
   *     Included as the persistent, unique VO identifier for the individual.
   * - voPersonSoRID:
   *   - Multi-valued. Enabled unless `vo_person_sorid` disable flag is set; filtered by `vo_person_sorid_type_id`.
   *   - Derived from Identifier records matching the configured type (or all if unselected).
   *     Included to publish System of Record (SoR) identifiers.
   * - voPersonCertificateDN:
   *   - Multi-valued. Enabled via boolean flag `vo_person_certificate_dn`.
   *   - Derived from client certificate credentials. Included for X.509 / grid authentication subject DNs.
   * - voPersonCertificateIssuerDN:
   *   - Multi-valued. Enabled via boolean flag `vo_person_certificate_issuer_dn`.
   *   - Derived from client certificate issuer information. Included to identify issuing Certificate Authorities.
   * - voPersonPolicyAgreement:
   *   - Multi-valued. Enabled via boolean flag `vo_person_policy_agreement`.
   *   - Derived from accepted Terms and Conditions agreements (`$person->t_and_c_agreements`), resolving T&C URLs.
   *     Included to assert compliance with VO acceptable use and data protection policies.
   * - voPersonStatus:
   *   - Single-valued. Enabled via boolean flag `vo_person_status`.
   *   - Derived from the Person's status (eg 'active', 'suspended'). Included to assert VO membership status.
   * - voPersonToken:
   *   - Multi-valued. Enabled via boolean flag `vo_person_token`.
   *   - Derived from active authentication tokens. Included for token-based credential mapping.
   *
   * Operation semantics:
   * - On add ($op = 'add'): emits only attributes that have populated values.
   * - On modify or rename ($op = 'modify' | 'rename'): for any enabled attribute that cannot be
   *   populated (e.g. certificates revoked, policy agreements expired), emits an empty array (`attr => []`)
   *   to request attribute removal/clearing in LDAP.
   * - Whenever attributes are contributed, ensures the `voPerson` objectclass is present in `objectClass`.
   *
   * Example return (typical add; attribute options disabled):
   * <code>
   * [
   *   'objectClass'               => ['voPerson'],
   *   'voPersonAffiliation'       => ['member', 'staff'],
   *   'voPersonAuthorName'        => ['J. Doe'],
   *   'voPersonExternalID'        => ['epuid:12345'],
   *   'voPersonID'                => ['epuid:12345'],
   *   'voPersonSoRID'             => ['sorid:HR:998877'],
   *   'voPersonPolicyAgreement'   => ['https://example.org/tc/acceptable-use'],
   *   'voPersonCertificateDN'     => ['CN=John Doe,OU=People,O=Example'],
   *   'voPersonCertificateIssuerDN' => ['CN=Example CA,O=Example'],
   *   'voPersonStatus'            => 'active',
   *   'voPersonToken'             => ['abcdef123456']
   * ]
   * </code>
   *
   * Example return (typical add; attribute options enabled):
   * <code>
   * [
   *   'objectClass' => ['voPerson'],
   *
   *   // Role-scoped values (one value set per role)
   *   'voPersonAffiliation;role-101' => ['member'],
   *   'voPersonAffiliation;role-102' => ['staff'],
   *
   *   // Language-tagged name values
   *   'voPersonAuthorName;lang-en' => ['J. Doe'],
   *
   *   // Application-scoped identifier values
   *   'voPersonApplicationUID;app-globus' => ['jdoe'],
   *   'voPersonApplicationUID;app-k8s'    => ['jdoe'],
   *
   *   // Application-scoped passwords
   *   'voPersonApplicationPassword;app-vpn'    => ['{SSHA}...'],
   *   'voPersonApplicationPassword;app-gitlab' => ['(CRYPT)...'],
   *
   *   // Status
   *   'voPersonStatus'          => 'active',
   *   'voPersonStatus;role-101' => 'active',
   *   'voPersonStatus;role-102' => 'active'
   * ]
   * </code>
   *
   * Example return (modify/rename clearing enabled-but-missing attributes in LDAP):
   * <code>
   * [
   *   'objectClass'                 => ['voPerson'],
   *   'voPersonCertificateDN'       => [],
   *   'voPersonCertificateIssuerDN' => [],
   *   'voPersonPolicyAgreement'     => []
   * ]
   * </code>
   *
   * @param EntityInterface $schema Active voPerson schema configuration entity.
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

    $isModifyLike = ($op === 'modify' || $op === 'rename');

    if (!empty($schema->vo_person_affiliation)) {
      $val = $this->assembleVoPersonAffiliation($data, $isModifyLike);
      if ($val !== null) {
        $ret['voPersonAffiliation'] = $val;
      }
    }

    if (empty($schema->vo_person_author_name)) {
      $typeId = !empty($schema->vo_person_author_name_type_id) ? (int)$schema->vo_person_author_name_type_id : null;
      $val = $this->assembleVoPersonAuthorName($data, $typeId, $isModifyLike);
      if ($val !== null) {
        $ret['voPersonAuthorName'] = $val;
      }
    }

    if (empty($schema->vo_person_application_uid)) {
      $typeId = !empty($schema->vo_person_application_uid_type_id) ? (int)$schema->vo_person_application_uid_type_id : null;
      $assembled = $this->assembleVoPersonApplicationUid(
        $ldapProvisioner,
        $data,
        $typeId,
        $isModifyLike
      );
      $ret = array_merge($ret, $assembled);
    }

    if (empty($schema->vo_person_external_id_export)) {
      $typeId = !empty($schema->vo_person_external_id_type_id) ? (int)$schema->vo_person_external_id_type_id : null;
      $val = $this->assembleVoPersonExternalId($data, $typeId, $isModifyLike);
      if ($val !== null) {
        $ret['voPersonExternalID'] = $val;
      }
    }

    if (empty($schema->vo_person_id_export)) {
      $typeId = !empty($schema->vo_person_id_type_id) ? (int)$schema->vo_person_id_type_id : null;
      $val = $this->assembleVoPersonId($data, $typeId, $isModifyLike);
      if ($val !== null) {
        $ret['voPersonID'] = $val;
      }
    }

    if (empty($schema->vo_person_sorid)) {
      $typeId = !empty($schema->vo_person_sorid_type_id) ? (int)$schema->vo_person_sorid_type_id : null;
      $val = $this->assembleVoPersonSorid($data, $typeId, $isModifyLike);
      if ($val !== null) {
        $ret['voPersonSoRID'] = $val;
      }
    }

    if (!empty($schema->vo_person_application_password)) {
      $assembled = $this->assembleVoPersonApplicationPassword(
        $ldapProvisioner,
        $data,
        $isModifyLike
      );
      $ret = array_merge($ret, $assembled);
    }

    if (!empty($schema->vo_person_certificate_dn)) {
      $val = $this->assembleVoPersonCertificateDn($data, $isModifyLike);
      if ($val !== null) {
        $ret['voPersonCertificateDN'] = $val;
      }
    }

    if (!empty($schema->vo_person_certificate_issuer_dn)) {
      $val = $this->assembleVoPersonCertificateIssuerDn($data, $isModifyLike);
      if ($val !== null) {
        $ret['voPersonCertificateIssuerDN'] = $val;
      }
    }

    if (!empty($schema->vo_person_policy_agreement)) {
      $val = $this->assembleVoPersonPolicyAgreement($data, $isModifyLike);
      if ($val !== null) {
        $ret['voPersonPolicyAgreement'] = $val;
      }
    }

    if (!empty($schema->vo_person_status)) {
      $val = $this->assembleVoPersonStatus($data, $isModifyLike);
      if ($val !== null) {
        $ret['voPersonStatus'] = $val;
      }
    }

    if (!empty($schema->vo_person_token)) {
      $val = $this->assembleVoPersonToken($data, $isModifyLike);
      if ($val !== null) {
        $ret['voPersonToken'] = $val;
      }
    }

    if (!empty($ret)) {
      $this->ensureSchemaObjectClass($ret);
    } else {
      $this->llog('debug', "Ret is empty after processing. Not calling ensureSchemaObjectClass(), so voPerson objectClass is NOT added.");
    }

    return $ret;
  }

  /**
   * Assemble voPersonAffiliation attribute from person roles.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleVoPersonAffiliation(Person $person, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->person_roles ?? []) as $pr) {
      if (!is_object($pr) || empty($pr->affiliation_type) || !is_object($pr->affiliation_type)) {
        continue;
      }

      $v = !empty($pr->affiliation_type->edupersonaffiliation)
        ? (string)$pr->affiliation_type->edupersonaffiliation
        : (!empty($pr->affiliation_type->value) ? (string)$pr->affiliation_type->value : null);

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
   * Assemble voPersonAuthorName attribute from names.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Name type ID.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleVoPersonAuthorName(Person $person, ?int $targetTypeId, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->names ?? []) as $n) {
      if (!is_object($n)) {
        continue;
      }

      if (!empty($targetTypeId)) {
        $nTypeId = !empty($n->type_id) ? (int)$n->type_id : null;
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
   * Assemble voPersonApplicationUID attribute (including option-tagged attributes).
   *
   * @param EntityInterface $ldapProvisioner
   * @param Person $person
   * @param int|null $targetTypeId
   * @param bool $isModifyLike
   * @return array<string,mixed>
   */
  protected function assembleVoPersonApplicationUid(
    EntityInterface $ldapProvisioner,
    Person $person,
    ?int $targetTypeId,
    bool $isModifyLike
  ): array {
    return $this->assembleVoPersonApplicationUidAttribute(
      ldapProvisioner: $ldapProvisioner,
      person: $person,
      typeId: $targetTypeId,
      isModifyLike: $isModifyLike
    );
  }

  /**
   * Assemble voPersonExternalID attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Identifier type ID.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleVoPersonExternalId(Person $person, ?int $targetTypeId, bool $isModifyLike): ?array
  {
    return $this->assembleIdentifierByTypeId($person, $targetTypeId, $isModifyLike);
  }

  /**
   * Assemble voPersonID attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Identifier type ID.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleVoPersonId(Person $person, ?int $targetTypeId, bool $isModifyLike): ?array
  {
    return $this->assembleIdentifierByTypeId($person, $targetTypeId, $isModifyLike);
  }

  /**
   * Assemble voPersonSoRID attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Identifier type ID.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleVoPersonSorid(Person $person, ?int $targetTypeId, bool $isModifyLike): ?array
  {
    return $this->assembleIdentifierByTypeId($person, $targetTypeId, $isModifyLike);
  }

  /**
   * Helper: extract identifier values for a specific type_id.
   *
   * @param Person $person Provisioned Person entity.
   * @param int|null $targetTypeId Target Identifier type ID.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleIdentifierByTypeId(Person $person, ?int $targetTypeId, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->identifiers ?? []) as $id) {
      if (!is_object($id)) {
        continue;
      }

      if (!empty($targetTypeId)) {
        $idTypeId = !empty($id->type_id) ? (int)$id->type_id : null;
        if (empty($idTypeId) || $idTypeId !== $targetTypeId) {
          continue;
        }
      }

      $v = (string)($id->identifier ?? '');
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
   * Assemble voPersonApplicationPassword attribute (including option-tagged attributes).
   *
   * @param EntityInterface $ldapProvisioner
   * @param Person $person
   * @param bool $isModifyLike
   * @return array<string,mixed>
   */
  protected function assembleVoPersonApplicationPassword(
    EntityInterface $ldapProvisioner,
    Person $person,
    bool $isModifyLike
  ): array {
    return $this->assembleVoPersonApplicationPasswordAttribute(
      ldapProvisioner: $ldapProvisioner,
      person: $person,
      isModifyLike: $isModifyLike
    );
  }

  /**
   * Assemble voPersonCertificateDN attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleVoPersonCertificateDn(Person $person, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->certificates ?? []) as $cr) {
      if (!is_object($cr)) {
        continue;
      }
      $v = (string)($cr->subject_dn ?? '');
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
   * Assemble voPersonCertificateIssuerDN attribute.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleVoPersonCertificateIssuerDn(Person $person, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->certificates ?? []) as $cr) {
      if (!is_object($cr)) {
        continue;
      }
      $v = (string)($cr->issuer_dn ?? '');
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
   * Assemble voPersonPolicyAgreement attribute from T&C agreements.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleVoPersonPolicyAgreement(Person $person, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->t_and_c_agreements ?? []) as $tc) {
      if (!is_object($tc)) {
        continue;
      }

      if (!empty($tc->url)) {
        $vals[] = $tc->url;
      }
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    if (!empty($vals)) {
      return $vals;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble voPersonStatus attribute from Person status.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return string|array<mixed>|null
   */
  protected function assembleVoPersonStatus(Person $person, bool $isModifyLike): string|array|null
  {
    $status = (string)($person->status ?? '');

    if ($status !== '') {
      return $status;
    }

    return $isModifyLike ? [] : null;
  }

  /**
   * Assemble voPersonToken attribute from TOTP tokens.
   *
   * @param Person $person Provisioned Person entity.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null
   */
  protected function assembleVoPersonToken(Person $person, bool $isModifyLike): ?array
  {
    $vals = [];

    foreach (($person->totp_tokens ?? []) as $tt) {
      if (!is_object($tt)) {
        continue;
      }
      $v = (string)($tt->serial ?? '');
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
   * Map an identifier type (Types.id) to one or more service short labels.
   *
   * CoService::mapIdentifierToLabels(co_id, identifier_type) which can return
   * multiple labels for a single identifier type.
   *
   * Current behavior: returns an empty array (mapping not wired yet).
   *
   * Example return:
   * <code>
   * [
   *   12 => 'globus',
   *   34 => 'k8s'
   * ]
   * </code>
   *
   * @param int|null $coId
   * @param int|null $typeId Types.id (Identifiers.type)
   * @return array<int,string> Map of service_id => short_label
   * @since COmanage Registry v5.3.0
   */
  private function mapIdentifierTypeIdToServiceLabels(?int $coId, ?int $typeId): array
  {
    if (empty($coId) || empty($typeId)) {
      return [];
    }

    // TODO: Implement when v5 has an equivalent mapping service available.
    return [];
  }

  /**
   * Map a password authenticator reference to one or more service short labels.
   *
   * CoService::mapAuthenticators(co_id) + group-based gating.
   *
   * Current behavior: returns an empty array (mapping not wired yet).
   *
   * Example return:
   * <code>
   * [
   *   7 => 'vpn',
   *   9 => 'gitlab'
   * ]
   * </code>
   *
   * @param int|null $coId
   * @param int|null $passwordAuthenticatorId
   * @param Person $person
   * @return array<int,string> Map of service_id => short_label
   * @since COmanage Registry v5.3.0
   */
  private function mapPasswordAuthenticatorToServiceLabels(
    ?int $coId,
    ?int $passwordAuthenticatorId,
    Person $person
  ): array {
    if (empty($coId) || empty($passwordAuthenticatorId)) {
      return [];
    }

    // TODO: Implement when v5 has an equivalent mapping service available.
    // This helper should also apply "service group" gating using the Person's group memberships.
    return [];
  }

  /**
   * Assemble voPersonApplicationUID.
   *
   * - If attribute options enabled, emit `voPersonApplicationUID;app-{short_label}` values for each
   *   mapped service short_label, and DO NOT emit a plain `voPersonApplicationUID` unless a label exists.
   * - If attribute options disabled, emit plain `voPersonApplicationUID` values.
   *
   * Example return (attr opts enabled):
   * <code>
   * [
   *   'voPersonApplicationUID;app-globus' => ['jdoe'],
   *   'voPersonApplicationUID;app-k8s'    => ['jdoe']
   * ]
   * </code>
   *
   * Example return (attr opts disabled):
   * <code>
   * [
   *   'voPersonApplicationUID' => ['jdoe']
   * ]
   * </code>
   *
   * @param EntityInterface $ldapProvisioner
   * @param Person $person
   * @param int|null $typeId
   * @param bool $isModifyLike
   * @return array<string,mixed>
   * @since COmanage Registry v5.3.0
   */
  protected function assembleVoPersonApplicationUidAttribute(
    EntityInterface $ldapProvisioner,
    Person $person,
    ?int $typeId,
    bool $isModifyLike
  ): array {
    $ret = [];

    $vals = [];
    foreach (($person->identifiers ?? []) as $id) {
      if (!is_object($id)) {
        continue;
      }

      if (!empty($typeId)) {
        $idTypeId = !empty($id->type_id) ? (int)$id->type_id : null;
        if (empty($idTypeId) || $idTypeId !== $typeId) {
          continue;
        }
      }

      $v = (string)($id->identifier ?? '');
      if ($v !== '') {
        $vals[] = $v;
      }
    }

    $vals = array_values(array_unique(array_filter($vals, static fn($v) => $v !== '')));

    $attropts = $this->attributeOptionsEnabled($ldapProvisioner);

    if (!$attropts) {
      if (!empty($vals)) {
        $ret['voPersonApplicationUID'] = $vals;
      } elseif ($isModifyLike) {
        $ret['voPersonApplicationUID'] = [];
      }

      return $ret;
    }

    // attr opts enabled => require mapped labels; emit scoped attributes only
    $coId = !empty($person->co_id) ? (int)$person->co_id : null;
    $labels = $this->mapIdentifierTypeIdToServiceLabels($coId, $typeId);

    if (!empty($labels) && !empty($vals)) {
      foreach ($labels as $serviceId => $shortLabel) {
        $shortLabel = trim((string)$shortLabel);
        if ($shortLabel === '') {
          continue;
        }

        $attr = 'voPersonApplicationUID;app-' . $shortLabel;
        $ret[$attr] = $vals;
      }
    } elseif ($isModifyLike) {
      // Best-effort: on modify-like operations, clear the base attribute key.
      $ret['voPersonApplicationUID'] = [];
    }

    return $ret;
  }

  /**
   * Assemble voPersonApplicationPassword.
   *
   * - Emits `voPersonApplicationPassword;app-{short_label}` values derived from application passwords.
   *
   * Current interim behavior:
   * - If attr opts are disabled (default), return either [] or clearing semantics on modify-like.
   * - If attr opts are enabled but mapping helpers return empty, return syntactically correct output.
   *
   * Example return:
   * <code>
   * [
   *   'voPersonApplicationPassword;app-vpn' => ['{SSHA}...'],
   *   'voPersonApplicationPassword;app-gitlab' => ['{SSHA}...']
   * ]
   * </code>
   *
   * @param EntityInterface $ldapProvisioner
   * @param Person $person
   * @param bool $isModifyLike
   * @return array<string,mixed>
   * @since COmanage Registry v5.3.0
   */
  protected function assembleVoPersonApplicationPasswordAttribute(
    EntityInterface $ldapProvisioner,
    Person $person,
    bool $isModifyLike
  ): array {
    $ret = [];

    $attropts = $this->attributeOptionsEnabled($ldapProvisioner);

    if (!$attropts) {
      if ($isModifyLike) {
        $ret['voPersonApplicationPassword'] = [];
      }
      return $ret;
    }

    $coId = !empty($person->co_id) ? (int)$person->co_id : null;

    foreach (($person->passwords ?? []) as $pw) {
      if (!is_object($pw)) {
        continue;
      }

      $authenticatorId = !empty($pw->password_authenticator_id) ? (int)$pw->password_authenticator_id : null;
      $labels = $this->mapPasswordAuthenticatorToServiceLabels($coId, $authenticatorId, $person);

      if (empty($labels)) {
        continue;
      }

      $passwordType = (string)($pw->type ?? '');
      $passwordVal  = (string)($pw->password ?? '');

      if ($passwordVal === '') {
        continue;
      }

      // CR => (CRYPT) prefix; SH => {SSHA}; otherwise raw
      $out = match ($passwordType) {
        'CR' => '(CRYPT)' . $passwordVal,
        'SH' => '{SSHA}' . $passwordVal,
        default => $passwordVal,
      };

      foreach ($labels as $serviceId => $shortLabel) {
        $shortLabel = trim((string)$shortLabel);
        if ($shortLabel === '') {
          continue;
        }

        $attr = 'voPersonApplicationPassword;app-' . $shortLabel;
        $ret[$attr][] = $out;
      }
    }

    // Normalize any collected lists
    foreach (array_keys($ret) as $k) {
      if (is_array($ret[$k])) {
        $ret[$k] = array_values(array_unique(array_filter($ret[$k], static fn($v) => $v !== '')));
      }
    }

    if (empty($ret) && $isModifyLike) {
      // Best-effort clearing key; cannot enumerate prior ;app-* variants without LDAP query.
      $ret['voPersonApplicationPassword'] = [];
    }

    return $ret;
  }

  /**
   * Extract configured Types.id (type_id) for an attribute from schema export configuration.
   *
   * @param array<string,array<string,mixed>> $cfgByAttr
   * @param string $attrName
   * @return int|null
   * @since COmanage Registry v5.3.0
   */
  private function extractConfiguredTypeId(array $cfgByAttr, string $attrName): ?int
  {
    $typeId = $cfgByAttr[$attrName]['type_id'] ?? null;
    $typeId = ($typeId !== null && $typeId !== '') ? (int)$typeId : null;

    return (!empty($typeId) ? $typeId : null);
  }

  /**
   * Default validation rules.
   *
   * @param Validator $validator Validator instance.
   * @return Validator
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function validationDefault(Validator $validator): Validator
  {
    $schema = $this->getSchema();

    $validator->add('ldap_schema_id', [
      'content' => ['rule' => 'isInteger'],
    ]);
    $validator->notEmptyString('ldap_schema_id');

    foreach ([
      'vo_person_affiliation',
      'vo_person_application_password',
      'vo_person_application_uid',
      'vo_person_author_name',
      'vo_person_certificate_dn',
      'vo_person_certificate_issuer_dn',
      'vo_person_external_id_export',
      'vo_person_id_export',
      'vo_person_policy_agreement',
      'vo_person_sorid',
      'vo_person_status',
      'vo_person_token'
    ] as $f) {
      $validator->add($f, [
        'content' => ['rule' => 'boolean']
      ]);
      $validator->allowEmptyString($f);
    }

    foreach ([
      'vo_person_application_uid_type_id',
      'vo_person_author_name_type_id',
      'vo_person_external_id_type_id',
      'vo_person_id_type_id',
      'vo_person_sorid_type_id'
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
   * @return string
   * @since  COmanage Registry v5.3.0
   */
  public function ldapObjectClass(): string
  {
    return 'voPerson';
  }
}
