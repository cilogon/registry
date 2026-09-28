<?php
/**
 * COmanage Registry LDAP Connector Schema eduMember Table
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

use App\Lib\Enum\SuspendableStatusEnum;
use App\Model\Entity\Person;
use App\Model\Entity\Group;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

class EduMemberSchemasTable extends Table {
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
   * and permissions for eduMember schema.
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
    $this->belongsTo('HasMemberTypes')
      ->setClassName('Types')
      ->setForeignKey('has_member_type_id')
      ->setProperty('has_member_type');

    $this->hasManyPlugins([
      'Types' => [
        [
          'alias' => 'EduMemberHasMemberTypes',
          'targetModel' => 'LdapConnector.EduMemberSchemas',
          'config' => [
            'foreignKey' => 'has_member_type_id'
          ]
        ]
      ]
    ]);

    $this->setPrimaryLink(['LdapConnector.ldap_schema_id']);
    $this->setRequiresCO(true);

    $this->setViewContains(['LdapSchemas', 'HasMemberTypes']);
    $this->setEditContains(['LdapSchemas', 'HasMemberTypes']);
    $this->setIndexContains(['LdapSchemas']);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');

    // For attribute-level type selection (eg hasMember identifier type)
    $this->setAutoViewVars([
      'hasMemberTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'identifierTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ]
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
   * @since COmanage Registry v5.3.0
   */
  public function supportsGroups(): bool
  {
    return true;
  }


  /**
   * Hook to assemble LDAP attributes for the eduMember schema.
   *
   * The eduMember objectclass publishes group membership semantics bidirectionally:
   * 1. On Person entries (className = "People"): exports group names via the multi-valued
   *    `isMemberOf` attribute when the boolean configuration `is_member_of` is enabled.
   *    This allows LDAP clients to inspect group memberships directly on the user's entry.
   * 2. On Group entries (className = "Groups"): exports member identifiers via the multi-valued
   *    `hasMember` attribute when an identifier type is selected in `has_member_type_id`
   *    (e.g., UID, ePPN). This allows LDAP clients to inspect member identity handles on the
   *    group's entry without requiring full DN references.
   *
   * Operation semantics:
   * - On add ($op = 'add'): emits only attributes that have populated values.
   * - On modify or rename ($op = 'modify' | 'rename'): if `has_member_type_id` is configured but
   *   no matching identifiers are found, emits an empty array (`hasMember => []`) to signal
   *   removal/clearing of the attribute in LDAP.
   * - Whenever attributes are contributed, ensures the `eduMember` objectclass is present in `objectClass`.
   *
   * Example return values:
   *
   * - Person entry add/modify (className = "People", with is_member_of enabled):
   * <code>
   * [
   *   'objectClass' => ['eduMember'],
   *   'isMemberOf'  => [
   *     'CO:members:active',
   *     'Faculty Council',
   *     'Research Group A'
   *   ]
   * ]
   * </code>
   *
   * - Group entry add (className = "Groups", with has_member_type_id configured for UID):
   * <code>
   * [
   *   'objectClass' => ['eduMember'],
   *   'hasMember'   => [
   *     'jdoe',
   *     'asmith',
   *     'bjones'
   *   ]
   * ]
   * </code>
   *
   * - Group entry modify/rename when member identifiers are removed (clearing in LDAP):
   * <code>
   * [
   *   'objectClass' => ['eduMember'],
   *   'hasMember'   => []
   * ]
   * </code>
   *
   * - When no attributes are enabled in the schema configuration or entity type does not match:
   * <code>
   * []
   * </code>
   *
   * @param EntityInterface $schema Active eduMember schema configuration entity.
   * @param EntityInterface $ldapProvisioner The LDAP provisioner configuration entity.
   * @param string $className Name of the model being provisioned ('People' or 'Groups').
   * @param object $data Provisioned entity (Person or Group).
   * @param string $op Provisioning operation: 'add', 'modify', or 'rename'.
   * @return array<string,mixed> Assembled LDAP attributes keyed by LDAP attribute name.
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  public function assemblePluginAttributes(
    EntityInterface $schema,
    EntityInterface $ldapProvisioner,
    string $className,
    object $data,
    string $op
  ): array {
    $ret = [];

    $isModifyLike = ($op === 'modify' || $op === 'rename');

    // eduMember:isMemberOf (People) — export group names
    if (!empty($schema->is_member_of) && $className === 'People' && $data instanceof Person) {
      $groups = $this->assembleIsMemberOf($data);
      if (!empty($groups)) {
        $ret['isMemberOf'] = $groups;
      }
    }

    // eduMember:hasMember (Groups) — member identifiers of configured type_id
    if (!empty($schema->has_member_type_id) && $className === 'Groups' && $data instanceof Group) {
      $members = $this->assembleHasMember($data, (int)$schema->has_member_type_id, $isModifyLike);
      if ($members !== null) {
        $ret['hasMember'] = $members;
      }
    }

    $this->ensureSchemaObjectClass($ret);

    return $ret;
  }

  /**
   * Assemble isMemberOf attribute (group names) for a Person.
   *
   * @param Person $person Provisioned Person entity.
   * @return array<string> List of group names.
   */
  protected function assembleIsMemberOf(Person $person): array
  {
    $names = [];

    foreach (($person->group_members ?? []) as $gm) {
      $groupName = null;

      if (is_object($gm)) {
        // Prefer contained Group
        if (!empty($gm->group) && is_object($gm->group) && !empty($gm->group->name)) {
          $groupName = (string)$gm->group->name;
        } elseif (!empty($gm->group_id)) {
          // Fallback: lookup Group name
          try {
            $Groups = TableRegistry::getTableLocator()->get('Groups');
            $g = $Groups->get((int)$gm->group_id);
            if (!empty($g->name)) {
              $groupName = (string)$g->name;
            }
          } catch (\Throwable $e) {
            // best-effort
          }
        }
      }

      if ($groupName !== null && $groupName !== '') {
        $names[] = $groupName;
      }
    }

    return array_values(array_unique($names));
  }

  /**
   * Assemble hasMember attribute (member identifiers of configured type) for a Group.
   *
   * @param Group $group Provisioned Group entity.
   * @param int $typeId Identifier type ID.
   * @param bool $isModifyLike Whether the provisioning operation is modify/rename.
   * @return array<string>|null List of member identifiers, empty array to clear on modify, or null if omitted on add.
   */
  protected function assembleHasMember(Group $group, int $typeId, bool $isModifyLike): ?array
  {
    $values = [];

    if (!empty($typeId)) {
      $Identifiers = TableRegistry::getTableLocator()->get('Identifiers');

      foreach (($group->group_members ?? []) as $gm) {
        $personId = null;

        if (is_object($gm)) {
          $personId = $gm->person_id ?? null;
        }

        if (empty($personId)) {
          continue;
        }

        $idRows = $Identifiers->find()
          ->select(['identifier'])
          ->where([
            'Identifiers.person_id' => (int)$personId,
            'Identifiers.type_id'   => $typeId,
            'Identifiers.status'    => SuspendableStatusEnum::Active,
          ])
          ->enableHydration(false)
          ->all()
          ->toArray();

        foreach ($idRows as $r) {
          if (!empty($r['identifier'])) {
            $values[] = (string)$r['identifier'];
          }
        }
      }
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

    $validator->add('is_member_of', [
      'content' => ['rule' => 'boolean']
    ]);
    $validator->allowEmptyString('is_member_of');

    $validator->add('has_member_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('has_member_type_id');

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
    return 'eduMember';
  }
}
