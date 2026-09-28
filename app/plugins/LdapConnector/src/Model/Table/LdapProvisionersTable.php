<?php
/**
 * COmanage Registry LDAP Provisioners Table
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

use App\Lib\Enum\ProvisioningEligibilityEnum;
use App\Lib\Enum\ProvisioningStatusEnum;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Util\StringUtilities;
use App\Model\Entity\ProvisioningTarget;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use CoreServer\Lib\Enum\LdapCommonCodesEnum;

class LdapProvisionersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionerTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;


  /**
   * Initialization method for the table.
   *
   * Sets up behaviors, table type, associations, supported models for provisioning,
   * and permissions for this table.
   *
   * @param array $config The configuration options passed to the method.
   * @return void
   * @since COmanage Registry v5.3.0
   */
  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    $this->setPrimaryLink(['provisioning_target_id']);
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['resync']);
    $this->setRedirectGoal('self');

    // Associations
    // Define associations
    $this->belongsTo('ProvisioningTargets');
    $this->belongsTo('Servers');
    $this->belongsTo('DnIdentifierTypes')
      ->setClassName('Types')
      ->setForeignKey('dn_identifier_type_id')
      ->setProperty('dn_identifier_type');

    $this->hasManyPlugins([
      'Types' => [
        [
          'alias' => 'LdapProvisionerDnIdentifierTypes',
          'targetModel' => 'LdapConnector.LdapProvisioners',
          'config' => [
            'foreignKey' => 'dn_identifier_type_id'
          ]
        ]
      ],
      'People' => [
        [
          'targetModel' => 'LdapConnector.LdapProvisionerDns',
          'config' => [
            'dependent' => true,
            'cascadeCallbacks' => true
          ]
        ]
      ],
      'Groups' => [
        [
          'targetModel' => 'LdapConnector.LdapProvisionerDns',
          'config' => [
            'dependent' => true,
            'cascadeCallbacks' => true
          ]
        ]
      ]
    ]);

    $this->setDisplayField('server_id');

    // We now link to the new Pluggable Model manager for schemas
    $this->hasMany('LdapConnector.LdapSchemas')
      ->setDependent(true)
      ->setForeignKey('ldap_provisioner_id')
      ->setCascadeCallbacks(true);
    $this->hasMany('LdapConnector.LdapProvisionerDns')
      ->setDependent(true)
      ->setForeignKey('ldap_provisioner_id')
      ->setCascadeCallbacks(true);

    $this->setAutoViewVars([
      'servers' => [
        'type' => 'plugin',
        'model' => 'CoreServer.LdapServers'
      ],
      'dnIdentifierTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ]
    ]);

    $this->setViewContains(['ProvisioningTargets', 'Servers']);
    $this->setEditContains(['ProvisioningTargets', 'Servers']);
    $this->setIndexContains(['ProvisioningTargets', 'Servers']);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'resync' =>   ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);

    // Declare supported models for provisioning
    $this->setProvisionableModels(['People', 'Groups']);
  }


  /**
   * Generates a display field for the given entity.
   *
   * This method retrieves a description from the provisioning target
   * if available and uses it as the display field.
   *
   * @param EntityInterface $entity The entity being processed.
   * @return string|null The generated display field, or null if unavailable.
   * @since v5.3.0
   */
  public function generateDisplayField(EntityInterface $entity): ?string {
    if (empty($entity->provisioning_target) || empty($entity->provisioning_target->description)) {
      return null;
    }

    return (string)$entity->provisioning_target->description;
  }


  /**
   * After saving a new LdapProvisioner, auto-create one LdapSchema row
   * per available ldap_schema plugin so they are ready to be enabled.
   *
   * @param \Cake\Event\EventInterface $event Event
   * @param \Cake\Datasource\EntityInterface $entity Entity (ie: Co)
   * @param \ArrayObject $options Save options
   * @return bool                     True on success
   * @since COmanage Registry v5.3.0
   */
  public function localAfterSave(\Cake\Event\EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $options): bool {
    // Only run on create, not on update
    if (!$entity->isNew()) {
      return true;
    }

    $LdapSchemas = TableRegistry::getTableLocator()->get('LdapConnector.LdapSchemas');
    $LdapSchemas->syncLdapSchemasIndex((int)$entity->id);

    return true;
  }

  /**
   * Sync LDAP Schemas for the requested LDAP Provisioner.
   *
   * @since  COmanage Registry v5.3.0
   * @param  int   $ldapProvisionerId LDAP Provisioner ID to sync schemas for
   * @param  bool  $active            If true, optional new Plugins will be set to Active status
   * @param  array $alwaysActive      Schema model names that are always active
   * @throws \Throwable
   */
  public function syncLdapSchemasIndex(
    int $ldapProvisionerId,
    bool $active = false,
    array $alwaysActive = ['PersonSchemas', 'OrganizationalPersonSchemas', 'InetOrgPersonSchemas']
  ): void {
    $LdapSchemas = TableRegistry::getTableLocator()->get('LdapConnector.LdapSchemas');
    $LdapSchemas->syncLdapSchemasIndex(
      ldapProvisionerId: $ldapProvisionerId,
      active: $active,
      alwaysActive: $alwaysActive
    );
  }


  /**
   * Default validation rules for the table.
   *
   * Defines required fields and their validation types for the table records.
   *
   * @param Validator $validator The validator object.
   * @return Validator
   * @since COmanage Registry v5.3.0
   */
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('provisioning_target_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('provisioning_target_id');

    $validator->add('server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('server_id');

    $this->registerStringValidation($validator, $schema, 'dn_attribute_name', true);
    $validator->add('dn_identifier_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('dn_identifier_type_id');

    $this->registerStringValidation($validator, $schema, 'scope_suffix', false);
    $validator->boolean('attr_opts')->allowEmptyString('attr_opts');

    return $validator;
  }

  /**
   * Apply a standard LDAP provisioning plan (add/modify/rename/delete).
   *
   * Optimistically performs the smallest LDAP operation needed and falls back
   * based on actual server error codes (no upfront existence reads).
   *
   * Decision tree:
   *  - delete:           deleteEntry(oldDn|newDn); ignore noSuchObject.
   *  - rename requested: renameEntry(oldDn -> newRdn); on noSuchObject -> add at newDn,
   *                      on entryAlreadyExists -> modReplace at newDn,
   *                      on success -> modReplace at newDn.
   *  - modify (default): modReplace(newDn); on noSuchObject -> addEntry(newDn).
   *  - add:              addEntry(newDn); on entryAlreadyExists -> modReplace(newDn).
   *
   * @param Table            $CoreLdapServers CoreServer.LdapServers table instance.
   * @param \LDAP\Connection $cxn         Bound LDAP connection.
   * @param string|null      $oldDn       Previously provisioned DN (may be null).
   * @param string|null      $newDn       Target DN (required for non-delete operations).
   * @param string           $baseDn      Base DN used to derive RDN on rename (may be empty).
   * @param array            $attributes  LDAP attributes for add/replace.
   * @param array            $options {
   *   @var bool $delete                       If true, delete oldDn (or newDn) and return.
   *   @var bool $allowRename                  If true, allow rename when oldDn != newDn.
   *   @var bool $deleteOldRdn                 Passed to ldap_rename (default true).
   *   @var bool $ignoreNoSuchObjectOnDelete   Default true.
   *   @var bool $applyAttributesAfterRename   Default true.
   * }
   * @return array{action:string,dn:?string}
   *   action ∈ {'delete','rename','modify','add','noop'}
   * @throws \InvalidArgumentException If non-delete and $newDn is missing.
   * @throws \RuntimeException         On LDAP failures not handled by fallback.
   */
  protected function applyProvisioningPlan(
    Table $CoreLdapServers,
    \LDAP\Connection $cxn,
    ?string $oldDn,
    ?string $newDn,
    string $baseDn,
    array $attributes,
    array $options = []
  ): array {
    $delete = (bool)($options['delete'] ?? false);
    $allowRename = (bool)($options['allowRename'] ?? true);
    $deleteOldRdn = (bool)($options['deleteOldRdn'] ?? true);
    $ignoreNoSuchOnDelete = (bool)($options['ignoreNoSuchObjectOnDelete'] ?? true);
    $applyAfterRename = (bool)($options['applyAttributesAfterRename'] ?? true);

    $oldDn = ($oldDn !== null && trim($oldDn) !== '') ? trim($oldDn) : null;
    $newDn = ($newDn !== null && trim($newDn) !== '') ? trim($newDn) : null;

    // ---- DELETE ----
    if ($delete) {
      $dnToDelete = $oldDn ?? $newDn;

      if ($dnToDelete === null) {
        return ['action' => 'noop', 'dn' => null];
      }

      try {
        $CoreLdapServers->deleteEntry($cxn, $dnToDelete);
      } catch (\RuntimeException $e) {
        if (
          !$ignoreNoSuchOnDelete
          || (int)$e->getCode() !== LdapCommonCodesEnum::LDAP_NO_SUCH_OBJECT
        ) {
          throw $e;
        }
      }

      return ['action' => 'delete', 'dn' => $dnToDelete];
    }

    if ($newDn === null) {
      throw new \InvalidArgumentException('applyProvisioningPlan requires $newDn for non-delete operations');
    }

    // Treat DNs as case-insensitive for the rename test
    $renameRequested = $allowRename
      && $oldDn !== null
      && strcasecmp($oldDn, $newDn) !== 0;

    // ---- RENAME ----
    if ($renameRequested) {
      $newRdn = $CoreLdapServers->removeBaseDnSuffix($newDn, $baseDn);

      try {
        $CoreLdapServers->renameEntry(
          $cxn,
          oldDn: $oldDn,
          newRdn: $newRdn,
          newParentDn: '',
          deleteOldRdn: $deleteOldRdn
        );

        if ($applyAfterRename && !empty($attributes)) {
          $CoreLdapServers->modReplace($cxn, $newDn, $attributes);
        }

        return ['action' => 'rename', 'dn' => $newDn];
      } catch (\RuntimeException $e) {
        $code = (int)$e->getCode();

        // Source DN doesn't exist anymore (or never did) -> just create it at newDn.
        if ($code === LdapCommonCodesEnum::LDAP_NO_SUCH_OBJECT) {
          return $CoreLdapServers->addOrModifyAt($cxn, $newDn, $attributes);
        }

        // Target already exists (someone else got there first or stale state) -> modify in place.
        if ($code === LdapCommonCodesEnum::LDAP_ENTRY_ALREADY_EXISTS) {
          $CoreLdapServers->modReplace($cxn, $newDn, $attributes);
          return ['action' => 'modify', 'dn' => $newDn];
        }

        throw $e;
      }
    }

    // ---- MODIFY (default), with add fallback ----
    return $CoreLdapServers->addOrModifyAt($cxn, $newDn, $attributes);
  }

  /**
   * Handles provisioning logic for the target
   *
   * @param ProvisioningTarget $provisioningTarget The provisioning target being processed.
   * @param string $className Name of the model being provisioned (eg: People, Groups).
   * @param object $data Object data related to the provisioning request.
   * @param string $eligibility Eligibility value for provisioning.
   * @return array Array containing provisioning result keys: status, comment, and identifier.
   * @throws \Throwable
   * @since COmanage Registry v5.3.0
   */
  public function provision(
    ProvisioningTarget $provisioningTarget,
    string             $className,
    object             $data,
    string             $eligibility
  ): array {
    // Result factory: keep this local and consistent.
    $result = static function (
      string  $status,
      string  $comment,
      ?string $identifier
    ): array {
      return [
        'status' => $status,
        'comment' => $comment,
        'identifier' => $identifier,
      ];
    };

    // Guard-only checks (no I/O, no LDAP) to short-circuit obvious cases.
    $guard = $this->guardProvisioningPrerequisites(
      provisioningTarget: $provisioningTarget,
      eligibility: $eligibility,
      resultFactory: $result
    );

    if (!empty($guard['result'])) {
      return $guard['result'];
    }

    $eligibilityValue = (string)$guard['eligibilityValue'];
    $serverId = (int)$guard['serverId'];

    // Guard: only these model names are supported.
    if ($className !== 'People' && $className !== 'Groups') {
      return $result(
        ProvisioningStatusEnum::Unknown,
        __d('ldap_connector', 'Unsupported provisioning class: {0}', [$className]),
        null
      );
    }

    // Load LDAP server configuration (I/O).
    $CoreLdapServers = TableRegistry::getTableLocator()->get('CoreServer.LdapServers');

    $ldapServer = $CoreLdapServers->find()
      ->where(['server_id' => $serverId])
      ->first();

    if (!$ldapServer) {
      return $result(
        ProvisioningStatusEnum::Unknown,
        __d('ldap_connector', 'LDAP server configuration not found for server_id {0}', [$serverId]),
        null
      );
    }

    $peopleBaseDn = (string)($ldapServer->basedn ?? '');
    $groupBaseDn  = (string)($ldapServer->group_basedn ?? '');

    $LdapProvisionerDns = TableRegistry::getTableLocator()->get('LdapConnector.LdapProvisionerDns');

    // Dispatch (keep the branching shallow and explicit).
    if ($className === 'People') {
      return $this->provisionPeople(
        provisioningTarget: $provisioningTarget,
        data: $data,
        eligibilityValue: $eligibilityValue,
        serverId: $serverId,
        CoreLdapServers: $CoreLdapServers,
        ldapServer: $ldapServer,
        peopleBaseDn: $peopleBaseDn,
        LdapProvisionerDns: $LdapProvisionerDns
      );
    }

    return $this->provisionGroups(
      provisioningTarget: $provisioningTarget,
      data: $data,
      eligibilityValue: $eligibilityValue,
      serverId: $serverId,
      CoreLdapServers: $CoreLdapServers,
      ldapServer: $ldapServer,
      groupBaseDn: $groupBaseDn,
      LdapProvisionerDns: $LdapProvisionerDns
    );
  }


  /**
   * Provision a Person into LDAP (create/modify/rename/delete).
   *
   * Assumes caller already performed high-level guards (eg ineligible),
   * and already loaded LDAP server config (including $peopleBaseDn) and table instances.
   *
   * @param ProvisioningTarget $provisioningTarget
   * @param object $data Person entity
   * @param string $eligibilityValue Normalized eligibility string
   * @param int $serverId LDAP server_id
   * @param Table $CoreLdapServers CoreServer.LdapServers table
   * @param object $ldapServer LDAP server entity (not currently used, but kept for symmetry/future needs)
   * @param string $peopleBaseDn Base DN for People
   * @param Table $LdapProvisionerDns LdapConnector.LdapProvisionerDns table
   * @return array{status:string,comment:string,identifier:?string}
   * @throws \Throwable
   */
  protected function provisionPeople(
    ProvisioningTarget $provisioningTarget,
    object             $data,
    string             $eligibilityValue,
    int                $serverId,
    Table              $CoreLdapServers,
    object             $ldapServer,
    string             $peopleBaseDn,
    Table              $LdapProvisionerDns
  ): array {
    $result = static function (
      string  $status,
      string  $comment,
      ?string $identifier
    ): array {
      return [
        'status' => $status,
        'comment' => $comment,
        'identifier' => $identifier,
      ];
    };

    $personId = isset($data->id) ? (int)$data->id : null;

    // Guard: we need some identifier to look up the DN mapping.
    if (empty($personId)) {
      return $result(
        ProvisioningStatusEnum::Unknown,
        __d('ldap_connector', 'Missing person identifier for provisioning'),
        null
      );
    }

    // Fetch old DN (may be null if never provisioned).
    $oldDn = $this->fetchProvisionedDn(
      provisioningTarget: $provisioningTarget,
      groupId: null,
      personId: $personId
    );

    // Guard: Deleted -> best-effort delete in LDAP, do not attempt DN assignment.
    if ($eligibilityValue === ProvisioningEligibilityEnum::Deleted) {
      if (!empty($oldDn)) {
        $cxn = $CoreLdapServers->getLdapConnection($serverId);

        $this->applyProvisioningPlan(
          $CoreLdapServers,
          $cxn,
          oldDn: (string)$oldDn,
          newDn: null,
          baseDn: $peopleBaseDn,
          attributes: [],
          options: [
            'delete' => true,
            'ignoreNoSuchObjectOnDelete' => true,
          ]
        );
      }

      return $result(
        ProvisioningStatusEnum::NotProvisioned,
        __d('ldap_connector', 'Record removed from LDAP'),
        null
      );
    }

    // Guard: base DN required for all non-delete operations.
    if ($peopleBaseDn === '') {
      return $result(
        ProvisioningStatusEnum::Unknown,
        __d('ldap_connector', 'Missing LDAP server basedn for server_id {0}', [$serverId]),
        null
      );
    }

    /** @var \App\Model\Entity\Person $person */
    $person = $data;

    // Obtain/assign new DN (also updates the DN cache table).
    $dnInfo = $LdapProvisionerDns->obtainPersonDn(
      provisioningTarget: $provisioningTarget,
      person: $person,
      baseDn: $peopleBaseDn,
      assign: true
    );

    $newDn = !empty($dnInfo['newdn']) ? (string)$dnInfo['newdn'] : null;

    // Guard: no DN means we cannot provision.
    if (empty($newDn)) {
      return $result(
        ProvisioningStatusEnum::NotProvisioned,
        !empty($dnInfo['newdnerr']) ? (string)$dnInfo['newdnerr'] : '',
        null
      );
    }

    // Operation hint for schema assembly (do not probe LDAP for existence).
    if (empty($oldDn)) {
      $opHint = 'add';
    } else {
      $opHint = (strcasecmp((string)$oldDn, $newDn) !== 0) ? 'rename' : 'modify';
    }

    // Assemble attributes once, based on the operation hint.
    $attributes = $this->assembleAttributesFromEnabledSchemas(
      ldapProvisioner: $provisioningTarget->ldap_provisioner,
      className:       'People',
      data:            $data,
      op:              $opHint
    );

    // Guard: if no schemas contribute anything, do not touch LDAP.
    if (empty($attributes)) {
      return $result(
        ProvisioningStatusEnum::NotProvisioned,
        __d('ldap_connector', 'No enabled LDAP schema attributes for provisioning'),
        null
      );
    }

    // Execute provisioning via centralized plan (handles add/modify/rename fallbacks).
    $cxn = $CoreLdapServers->getLdapConnection($serverId);

    $plan = $this->applyProvisioningPlan(
      $CoreLdapServers,
      $cxn,
      oldDn: (!empty($oldDn) ? (string)$oldDn : null),
      newDn: $newDn,
      baseDn: $peopleBaseDn,
      attributes: $attributes,
      options: [
        // We already handled Deleted above; here we just allow rename when DN changed.
        'allowRename' => true,
        'applyAttributesAfterRename' => true,
      ]
    );

    $action = (string)($plan['action'] ?? 'unknown');
    $dn     = (string)($plan['dn'] ?? $newDn);

    return $result(
      ProvisioningStatusEnum::Provisioned,
      '[' . $action . '] ' . $dn,
      $dn
    );
  }


  /**
   * Validate preconditions for provisioning and compute basic IDs.
   *
   * This method is intentionally "guard-only": it does not query tables or fetch
   * LDAP server configuration. It returns either an early provisioning result, or
   * the normalized inputs required for config retrieval inside provision().
   *
   * @param \App\Model\Entity\ProvisioningTarget $provisioningTarget
   * @param mixed $eligibility string|ProvisioningEligibilityEnum (or other scalar-ish)
   * @param callable $resultFactory function(string $status, string $comment, ?string $identifier): array
   * @return array{result:array|null,eligibilityValue:string,ldapProvisionerId:int,serverId:int}
   */
  protected function guardProvisioningPrerequisites(
    \App\Model\Entity\ProvisioningTarget $provisioningTarget,
    mixed                                $eligibility,
    callable                             $resultFactory
  ): array
  {
    $eligibilityValue = (string)$eligibility;

    // Short-circuit: ineligible records are not provisioned
    if ($eligibilityValue === \App\Lib\Enum\ProvisioningEligibilityEnum::Ineligible) {
      return [
        'result' => $resultFactory(
          \App\Lib\Enum\ProvisioningStatusEnum::NotProvisioned,
          __d('ldap_connector', 'Record is not eligible for provisioning'),
          null
        ),
        'eligibilityValue' => $eligibilityValue,
        'ldapProvisionerId' => 0,
        'serverId' => 0,
      ];
    }

    $ldapProvisionerId = (int)($provisioningTarget->ldap_provisioner->id ?? 0);
    if ($ldapProvisionerId < 1) {
      throw new \InvalidArgumentException(__d('error', 'notfound', [__d('controller', 'LdapProvisioners', [1])]));
    }

    $serverId = (int)($provisioningTarget->ldap_provisioner->server_id ?? 0);
    if ($serverId < 1) {
      throw new \InvalidArgumentException(__d('error', 'notfound', [__d('controller', 'Servers', [1])]));
    }

    return [
      'result' => null,
      'eligibilityValue' => $eligibilityValue,
      'ldapProvisionerId' => $ldapProvisionerId,
      'serverId' => $serverId,
    ];
  }


  /**
   * Provision a Group into LDAP (create/modify/rename/delete).
   *
   * Assumes caller already performed high-level guards (eg ineligible),
   * and already loaded LDAP server config (including $groupBaseDn) and table instances.
   *
   * @param ProvisioningTarget $provisioningTarget
   * @param object $data Group entity
   * @param string $eligibilityValue Normalized eligibility string
   * @param int $serverId LDAP server_id
   * @param Table $CoreLdapServers CoreServer.LdapServers table
   * @param object $ldapServer LDAP server entity (not currently used, but kept for symmetry/future needs)
   * @param string $groupBaseDn Base DN for Groups
   * @param Table $LdapProvisionerDns LdapConnector.LdapProvisionerDns table
   * @return array{status:string,comment:string,identifier:?string}
   * @throws \Throwable
   */
  protected function provisionGroups(
    ProvisioningTarget $provisioningTarget,
    object             $data,
    string             $eligibilityValue,
    int                $serverId,
    Table              $CoreLdapServers,
    object             $ldapServer,
    string             $groupBaseDn,
    Table              $LdapProvisionerDns
  ): array {
    $result = static function (
      string  $status,
      string  $comment,
      ?string $identifier
    ): array {
      return [
        'status' => $status,
        'comment' => $comment,
        'identifier' => $identifier,
      ];
    };

    $groupId = isset($data->id) ? (int)$data->id : null;

    // Guard: we need some identifier to look up the DN mapping.
    if (empty($groupId)) {
      return $result(
        ProvisioningStatusEnum::Unknown,
        __d('ldap_connector', 'Missing group identifier for provisioning'),
        null
      );
    }

    // Fetch old DN (may be null if never provisioned).
    $oldDn = $this->fetchProvisionedDn(
      provisioningTarget: $provisioningTarget,
      groupId: $groupId,
      personId: null
    );

    // Guard: Deleted -> best-effort delete in LDAP, do not attempt DN assignment.
    if ($eligibilityValue === ProvisioningEligibilityEnum::Deleted || $eligibilityValue === 'Deleted') {
      if (!empty($oldDn)) {
        $cxn = $CoreLdapServers->getLdapConnection($serverId);

        $this->applyProvisioningPlan(
          $CoreLdapServers,
          $cxn,
          oldDn: (string)$oldDn,
          newDn: null,
          baseDn: $groupBaseDn,
          attributes: [],
          options: [
            'delete' => true,
            'ignoreNoSuchObjectOnDelete' => true,
          ]
        );
      }

      return $result(
        ProvisioningStatusEnum::NotProvisioned,
        __d('ldap_connector', 'Record removed from LDAP'),
        null
      );
    }

    // Guard: base DN required for all non-delete operations.
    if ($groupBaseDn === '') {
      return $result(
        ProvisioningStatusEnum::Unknown,
        __d('ldap_connector', 'Missing LDAP server group_basedn for server_id {0}', [$serverId]),
        null
      );
    }

    /** @var \App\Model\Entity\Group $group */
    $group = $data;

    // Obtain/assign new DN (also updates the DN cache table).
    $dnInfo = $LdapProvisionerDns->obtainGroupDn(
      provisioningTarget: $provisioningTarget,
      group: $group,
      groupBaseDn: $groupBaseDn,
      assign: true
    );

    $newDn = !empty($dnInfo['newdn']) ? (string)$dnInfo['newdn'] : null;

    // Guard: no DN means we cannot provision.
    if (empty($newDn)) {
      return $result(
        ProvisioningStatusEnum::NotProvisioned,
        !empty($dnInfo['newdnerr']) ? (string)$dnInfo['newdnerr'] : '',
        null
      );
    }

    // Operation hint for schema assembly (do not probe LDAP for existence).
    if (empty($oldDn)) {
      $opHint = 'add';
    } else {
      $opHint = (strcasecmp((string)$oldDn, $newDn) !== 0) ? 'rename' : 'modify';
    }

    // Assemble attributes once, based on the operation hint.
    // Keep legacy parity: GroupOfNamesSchemasTable may throw UnderflowException('member').
    try {
      $attributes = $this->assembleAttributesFromEnabledSchemas(
        ldapProvisioner: $provisioningTarget->ldap_provisioner,
        className:       'Groups',
        data:            $data,
        op:              $opHint
      );
    } catch (\UnderflowException $e) {
      // Legacy parity: groupOfNames requires at least one member -> deprovision the group in LDAP.
      if ($e->getMessage() === 'member') {
        if (!empty($oldDn)) {
          $cxn = $CoreLdapServers->getLdapConnection($serverId);

          $this->applyProvisioningPlan(
            $CoreLdapServers,
            $cxn,
            oldDn: (string)$oldDn,
            newDn: null,
            baseDn: $groupBaseDn,
            attributes: [],
            options: [
              'delete' => true,
              'ignoreNoSuchObjectOnDelete' => true,
            ]
          );
        }

        return $result(
          ProvisioningStatusEnum::NotProvisioned,
          __d('ldap_connector', 'groupOfNames requires at least one member; record removed from LDAP'),
          null
        );
      }

      throw $e;
    }

    // Guard: if no schemas contribute anything, do not touch LDAP.
    if (empty($attributes)) {
      return $result(
        ProvisioningStatusEnum::NotProvisioned,
        __d('ldap_connector', 'No enabled LDAP schema attributes for provisioning'),
        null
      );
    }

    // Execute provisioning via centralized plan (handles add/modify/rename fallbacks).
    $cxn = $CoreLdapServers->getLdapConnection($serverId);

    $plan = $this->applyProvisioningPlan(
      $CoreLdapServers,
      $cxn,
      oldDn: (!empty($oldDn) ? (string)$oldDn : null),
      newDn: $newDn,
      baseDn: $groupBaseDn,
      attributes: $attributes,
      options: [
        'allowRename' => true,
        'applyAttributesAfterRename' => true,
      ]
    );

    $action = (string)($plan['action'] ?? 'unknown');
    $dn     = (string)($plan['dn'] ?? $newDn);

    return $result(
      ProvisioningStatusEnum::Provisioned,
      '[' . $action . '] ' . $dn,
      $dn
    );
  }

  /**
   * Queries the backend system to determine live provisioning status.
   *
   * Verifies if the record exists in the backend and retrieves its current provisioning status.
   *
   * @param ProvisioningTarget $cfg The provisioning target entity to check.
   * @param int|null $groupId Optional group ID for the query.
   * @param int|null $personId Optional person ID for the query.
   * @return array Array with provisioning status keys: status, comment, and optional timestamp.
   * @since COmanage Registry v5.3.0
   */
  public function status(
    ProvisioningTarget $cfg,
    ?int $groupId,
    ?int $personId
  ): array {
    if (empty($cfg->ldap_provisioner->server_id)) {
      return [
        'status'    => ProvisioningStatusEnum::NotProvisioned,
        'timestamp' => null,
        'comment'   => __d('error', 'notfound', [__d('controller', 'Servers', [1])])
      ];
    }

    $serverId = (int)$cfg->ldap_provisioner->server_id;

    $dn = $this->fetchProvisionedDn($cfg, $groupId, $personId);

    // No DN on file means not provisioned
    if (empty($dn)) {
      return [
        'status'    => ProvisioningStatusEnum::NotProvisioned,
        'timestamp' => null,
        'comment'   => ""
      ];
    }

    $CoreLdapServers = TableRegistry::getTableLocator()->get('CoreServer.LdapServers');

    try {
      $cxn = $CoreLdapServers->getLdapConnection($serverId);
    } catch (\Throwable $e) {
      return [
        'status'    => ProvisioningStatusEnum::Unknown,
        'timestamp' => null,
        'comment'   => $e->getMessage()
      ];
    }

    if (!$cxn) {
      return [
        'status'    => ProvisioningStatusEnum::Unknown,
        'timestamp' => null,
        'comment'   => __d('ldap_connector', 'Failed to obtain LDAP connection for server {0}', [$serverId])
      ];
    }

    try {
      $ldapRecord = $CoreLdapServers->queryLdap(
        $cxn,
        $dn,
        "(objectclass=*)",
        ['modifytimestamp']
      );
    } catch (\RuntimeException $e) {
      // LDAP_NO_SUCH_OBJECT (32) means not provisioned
      if ($e->getCode() === LdapCommonCodesEnum::LDAP_NO_SUCH_OBJECT) {
        return [
          'status'    => ProvisioningStatusEnum::NotProvisioned,
          'timestamp' => null,
          'comment'   => $dn
        ];
      }

      // Any other LDAP/runtime error -> unknown
      return [
        'status'    => ProvisioningStatusEnum::Unknown,
        'timestamp' => null,
        'comment'   => $e->getMessage()
      ];
    } catch (\Throwable $e) {
      // Unexpected error -> unknown
      return [
        'status'    => ProvisioningStatusEnum::Unknown,
        'timestamp' => null,
        'comment'   => $e->getMessage()
      ];
    }

    // Entry not found (empty result set) -> not provisioned
    if (empty($ldapRecord['count']) || (int)$ldapRecord['count'] < 1) {
      return [
        'status'    => ProvisioningStatusEnum::NotProvisioned,
        'timestamp' => null,
        'comment'   => $dn
      ];
    }

    // Provisioned: entry exists in LDAP
    $ret = [
      'status'    => ProvisioningStatusEnum::Provisioned,
      'timestamp' => null,
      'comment'   => $dn
    ];

    if (!empty($ldapRecord[0]['modifytimestamp'][0])) {
      $ret['timestamp'] = strtotime($ldapRecord[0]['modifytimestamp'][0]);
    }

    return $ret;
  }

  /**
   * Fetch the currently provisioned DN for a person or group for this LDAP provisioner.
   *
   * @param ProvisioningTarget $provisioningTarget Provisioning target (contains ldap_provisioner).
   * @param int|null $groupId Group ID (if checking group provisioning status).
   * @param int|null $personId Person ID (if checking person provisioning status).
   * @return string|null DN if on file, else null.
   * @throws \Throwable
   * @since COmanage Registry v5.3.0
   */
  protected function fetchProvisionedDn(
    ProvisioningTarget $provisioningTarget,
    ?int $groupId,
    ?int $personId
  ): ?string {
    if (empty($personId) && empty($groupId)) {
      return null;
    }

    if (empty($provisioningTarget->ldap_provisioner) || empty($provisioningTarget->ldap_provisioner->id)) {
      return null;
    }

    $ldapProvisionerId = (int)$provisioningTarget->ldap_provisioner->id;

    $LdapProvisionerDns = TableRegistry::getTableLocator()->get('LdapConnector.LdapProvisionerDns');

    $where = [
      'LdapProvisionerDns.ldap_provisioner_id' => $ldapProvisionerId,
    ];

    if (!empty($personId)) {
      $where['LdapProvisionerDns.person_id'] = $personId;
    } else {
      $where['LdapProvisionerDns.group_id'] = (int)$groupId;
    }

    $dnRecord = $LdapProvisionerDns->find()
      ->select(['dn'])
      ->where($where)
      ->enableHydration(false)
      ->first();

    return !empty($dnRecord['dn']) ? (string)$dnRecord['dn'] : null;
  }


  /**
   * Assemble LDAP attributes by iterating enabled LdapSchemas for this provisioner.
   *
   * Only schema instances that are Active are considered. Each schema plugin table
   * is expected to implement:
   *   assemblePluginAttributes(
   *     EntityInterface $schema,
   *     EntityInterface $ldapProvisioner,
   *     string $className,
   *     object $data,
   *     string $op
   *   ): array
   *
   * Returned attributes from all enabled schemas are merged.
   *
   * @param EntityInterface $ldapProvisioner LDAP provisioner configuration entity.
   * @param string $className 'People'|'Groups'
   * @param object $data Person|Group entity.
   * @param string $op Provisioning operation: 'add', 'modify', or 'rename'.
   * @return array<string,mixed> Merged LDAP attributes keyed by LDAP attribute name.
   * @throws \Throwable
   * @since  COmanage Registry v5.3.0
   */
  protected function assembleAttributesFromEnabledSchemas(
    EntityInterface    $ldapProvisioner,
    string             $className,
    object             $data,
    string             $op
  ): array {
    $ldapProvisionerId = (int)($ldapProvisioner->id ?? 0);
    if ($ldapProvisionerId < 1) {
      return [];
    }

    $LdapSchemas = TableRegistry::getTableLocator()->get('LdapConnector.LdapSchemas');

    $schemas = $LdapSchemas->find()
      ->where([
        'LdapSchemas.ldap_provisioner_id' => $ldapProvisionerId,
        'LdapSchemas.status' => SuspendableStatusEnum::Active,
      ])
      ->orderBy(['LdapSchemas.id' => 'ASC'])
      ->contain($LdapSchemas->getPluginRelations())
      ->all();
      
    $this->llog('debug', "Found " . $schemas->count() . " active schemas for ldapProvisionerId=" . $ldapProvisionerId);

    $attributes = [];

    foreach ($schemas as $schema) {
      $pluginModel = (string)($schema->plugin ?? '');
      if ($pluginModel === '') {
        continue;
      }
      
      $this->llog('debug', "Evaluating schema pluginModel=" . $pluginModel);

      // Each schema plugin is a Table class (eg "LdapConnector.EduMemberSchemas")
      $SchemaTable = TableRegistry::getTableLocator()->get($pluginModel);

      if (!method_exists($SchemaTable, 'assemblePluginAttributes')) {
        $this->llog('debug', "method assemblePluginAttributes not found on " . $pluginModel);
        // Skip misconfigured/legacy schemas gracefully
        continue;
      }

      // Skip schemas that don't apply to the current provisioning class (People vs Groups)
      if (method_exists($SchemaTable, 'supportsProvisioningClass')
        && !$SchemaTable->supportsProvisioningClass($className)) {
        $this->llog('debug', "pluginModel=" . $pluginModel . " does not support provisioning class " . $className);
        continue;
      }

      $modelAlias = StringUtilities::pluginModel($pluginModel);
      $prop = Inflector::underscore(Inflector::singularize($modelAlias));

      if (empty($schema->$prop)) {
        $this->llog('debug', "pluginModel=" . $pluginModel . " has no configuration row for schema id " . $schema->id);
        continue;
      }

      $schemaAttrs = (array)$SchemaTable->assemblePluginAttributes(
        schema:          $schema->$prop,
        ldapProvisioner: $ldapProvisioner,
        className:       $className,
        data:            $data,
        op:              $op
      );
      
      $this->llog('debug', "pluginModel=" . $pluginModel . " returned attrs=" . json_encode($schemaAttrs));

      if (empty($schemaAttrs)) {
        continue;
      }

      // Merge attributes; if the same attribute appears in multiple schemas,
      // combine values (dedupe) when both are arrays, else last-one-wins.
      foreach ($schemaAttrs as $attr => $value) {
        if ($attr === '' || $value === null) {
          continue;
        }

        if (!array_key_exists($attr, $attributes)) {
          $attributes[$attr] = $value;
          continue;
        }

        $existing = $attributes[$attr];

        if (is_array($existing) && is_array($value)) {
          $attributes[$attr] = array_values(array_unique(array_merge($existing, $value)));
        } else {
          $attributes[$attr] = $value;
        }
      }
    }
    
    $this->llog('debug', "Final merged attributes=" . json_encode($attributes));

    return $attributes;
  }
}
