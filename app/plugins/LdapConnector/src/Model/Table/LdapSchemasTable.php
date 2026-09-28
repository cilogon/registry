<?php
declare(strict_types=1);

namespace LdapConnector\Model\Table;

/**
 * LdapSchemasTable
 *
 * This class represents the table definition for LDAP Schemas within the LdapConnector plugin.
 *
 * @link   https://www.internet2.edu/comanage COmanage Project
 * @since  COmanage Registry v5.3.0
 */

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\TableRegistry;
use App\Lib\Util\StringUtilities;

class LdapSchemasTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\PluggableModelTrait;


  /**
   * Initialize method
   *
   * Configures the LdapSchemasTable, including behaviors, associations, and other table settings.
   *
   * @param array $config Table configuration array.
   * @return void
   * @since  COmanage Registry v5.3.0
   */
  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    $this->belongsTo('LdapConnector.LdapProvisioners');

    $this->setPrimaryLink(['LdapConnector.ldap_provisioner_id']);
    $this->setRequiresCO(true);
    $this->setDisplayField('description');
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');

    $this->bindPluggableRelations();

    $this->setAutoViewVars([
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'ldap_schema'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SuspendableStatusEnum'
      ],
      'telephoneNumberTypes' => [
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
        'configure' => ['platformAdmin', 'coAdmin'],
        'delete' => false,
        'edit' => ['platformAdmin', 'coAdmin'],
        'view' => ['platformAdmin', 'coAdmin']
      ],
      'table' => [
        'add' => false,
        'index'   => ['platformAdmin', 'coAdmin'],
        'save'    => ['platformAdmin', 'coAdmin'],
        'deleted' => ['platformAdmin', 'coAdmin'],
      ]
    ]);
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
    $Plugins = TableRegistry::getTableLocator()->get('Plugins');
    $availableSchemas = $Plugins->getActivePluginModels('ldap_schema');

    foreach (array_keys($availableSchemas) as $plugin) {
      $count = $this->find()
        ->where([
          'ldap_provisioner_id' => $ldapProvisionerId,
          'plugin'              => $plugin,
        ])
        ->count();

      if ($count == 0) {
        $modelName = StringUtilities::pluginModel($plugin);
        $humanReadable = \Cake\Utility\Inflector::humanize(\Cake\Utility\Inflector::underscore($modelName));

        $status = in_array($modelName, $alwaysActive, true)
          ? \App\Lib\Enum\SuspendableStatusEnum::Active
          : ($active ? \App\Lib\Enum\SuspendableStatusEnum::Active : \App\Lib\Enum\SuspendableStatusEnum::Suspended);

        $schemaEntity = $this->newEntity([
          'ldap_provisioner_id' => $ldapProvisionerId,
          'plugin'              => $plugin,
          'status'              => $status,
          'description'         => $humanReadable . ' for Provisioner #' . $ldapProvisionerId,
        ]);

        try {
          $this->saveOrFail($schemaEntity);
        } catch (\Throwable $e) {
          if (!empty($schemaEntity->id)) {
            $this->delete($schemaEntity);
          }
          throw $e;
        }
      }
    }
  }

  /**
   * After saving a new LdapSchema, auto-create the corresponding schema plugin row.
   * On update, synchronize status changes to the corresponding schema plugin row.
   *
   * @param \Cake\Event\EventInterface $event Event
   * @param \Cake\Datasource\EntityInterface $entity Entity
   * @param \ArrayObject $options Save options
   * @return bool
   * @since COmanage Registry v5.3.0
   */
  public function localAfterSave(
    \Cake\Event\EventInterface $event,
    \Cake\Datasource\EntityInterface $entity,
    \ArrayObject $options
  ): bool {
    if (!$entity->isNew() || empty($entity->plugin)) {
      return true;
    }

    $schemaTable = $this->getSchemaPluginTable((string)$entity->plugin);
    $this->createSchemaPluginRow($schemaTable, (int)$entity->id);

    return true;
  }

  /**
   * Get the schema plugin table instance from the configured plugin model string.
   *
   * @param string $pluginModel Eg "LdapConnector.PersonSchemas"
   * @return \Cake\ORM\Table
   * @since COmanage Registry v5.3.0
   */
  protected function getSchemaPluginTable(string $pluginModel): \Cake\ORM\Table
  {
    return TableRegistry::getTableLocator()->get($pluginModel);
  }

  /**
   * Create the schema plugin row (eg PersonSchemas row) pointing at this ldap_schema_id.
   *
   * @param \Cake\ORM\Table $schemaTable Schema plugin table
   * @param int $ldapSchemaId LdapSchemas.id
   * @return void
   * @throws \Throwable
   * @since COmanage Registry v5.3.0
   */
  protected function createSchemaPluginRow(
    \Cake\ORM\Table $schemaTable,
    int $ldapSchemaId
  ): void {
    $data = ['ldap_schema_id' => $ldapSchemaId];

    $tableSchema = $schemaTable->getSchema();

    $defaults = [];
    if (method_exists($schemaTable, 'getDefaults')) {
      $defaults = (array)$schemaTable->getDefaults();
    }

    foreach ($defaults as $col => $val) {
      if ($tableSchema->hasColumn($col) && $val !== null) {
        $data[$col] = $val;
      }
    }

    $schemaRow = $schemaTable->newEntity($data);

    $schemaTable->saveOrFail($schemaRow, ['validate' => false, 'checkRules' => false]);
  }

  /**
   * Default validation rules
   *
   * Validates the default schema for the LDAP Schema table.
   *
   * @param \Cake\Validation\Validator $validator Validator instance.
   * @return \Cake\Validation\Validator
   * @since  COmanage Registry v5.3.0
   */
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('ldap_provisioner_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('ldap_provisioner_id');

    $validator->integer('ldap_provisioner_id')->requirePresence('ldap_provisioner_id', 'create');
    $this->registerStringValidation($validator, $schema, 'plugin', true);
    $validator->add('status', [
      'content' => ['rule' => ['inList', \App\Lib\Enum\SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    $this->registerStringValidation($validator, $schema, 'description', false);

    return $validator;
  }

  /**
   * Build application integrity rules.
   *
   * @param RulesChecker $rules Rules checker to be modified.
   * @return RulesChecker Modified rules checker with additional integrity rules.
   * @since  COmanage Registry v5.3.0
   */
  public function buildRules(RulesChecker $rules): RulesChecker
  {
    // A provisioner target can only have one instance of each schema plugin
    $rules->add(
      $rules->isUnique(
        ['ldap_provisioner_id', 'plugin'],
        __d('ldap_connector', 'error.LdapSchemas.plugin.exists')
      ),
      'uniquePluginPerProvisioner',
      ['errorField' => 'plugin']
    );

    return $rules;
  }
}
