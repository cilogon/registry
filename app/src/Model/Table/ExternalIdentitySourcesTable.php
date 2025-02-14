<?php
/**
 * COmanage Registry External Identity Sources Table
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
 * @package       registry
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use App\Lib\Enum\SyncModeEnum;
use App\Lib\Util\StringUtilities;

class ExternalIdentitySourcesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PluggableModelTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\TabTrait;

  // Cache of the EIS configuration, keyed on id
  protected $eisCache = null;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Cos');
    $this->belongsTo('Pipelines');
    
    $this->hasMany('ExtIdentitySourceRecords')
         ->setDependent(true)
         ->setCascadeCallbacks(true);

    $this->setPluginRelations();
    
    $this->setDisplayField('description');
    
    $this->setPrimaryLink(['co_id']);
    $this->setRequiresCO(true);
    // We need to calculate the redirect URL for sync ourselves (in the controller)
    $this->setRedirectGoal('special', 'sync');
    $this->setAllowLookupPrimaryLink(['retrieve', 'search', 'sync']);

    $this->setAutoViewVars([
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'source'
      ],
      'pipelines' => [
        'type'  => 'select',
        'model' => 'Pipelines'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SyncModeEnum'
      ]
    ]);

    // All the tabs share the same configuration in the ModelTable file
    $this->setTabsConfig(
      [
        // Ordered-list of Tabs
        'tabs' => ['ExternalIdentitySources', 'ExternalIdentitySources.Plugin', 'ExternalIdentitySources@action.search'],
        // What actions will include the subnavigation header
        'action' => [
          // If a model renders in a subnavigation mode in edit/view mode, it cannot
          // render in index mode for the same use case/context
          // XXX edit should go first.
          'ExternalIdentitySources' => ['edit', 'view', 'search'],
          'ExternalIdentitySources.Plugin' => ['edit'],
          'ExternalIdentitySources@action.search' => [],
        ],
      ]
    );

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'configure' =>  ['platformAdmin', 'coAdmin'],
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'retrieve' =>   ['platformAdmin', 'coAdmin'],
        'search' =>     ['platformAdmin', 'coAdmin'],
        'sync' =>       ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
        'status' =>   ['platformAdmin', 'coAdmin']
      ],
      'related' => [
        'table' => [
          'ExtIdentitySourceRecords'
        ]
      ]
    ]);
  }

  /**
   * Obtain the changelist from the backend, if supported.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id         External Identity Source ID
   * @param  int    $lastStart  Timestamp of last run
   * @param  int    $curStart   Timestamp of current run
   * @return array|bool     Array of updated source keys, or false if not supported
   */

  public function getChangeList(
    int $id,
    int $lastStart,
    int $curStart
  ): array|bool {
    $source = $this->getEIS($id);

    // We directly retrieve the table object here rather than use $this->$model
    // because the latter is actually an instance of \Cake\ORM\Association\HasOne,
    // so we can't tell if the plugin has implemented getChangeList that way.
    $Plugin = TableRegistry::getTableLocator()->get($source->plugin);

    if(method_exists($Plugin, 'getChangeList')) {
      return $Plugin->getChangeList($source, $lastStart, $curStart);
    }

    return false;
  }

  /**
   * Get an EIS configuration, possibly via the cache.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id         External Identity Source ID
   * @return ExternalIdentitySource
   */

  protected function getEIS(int $id) {
    // We want to pull the plugin configuration along with the EIS, to make
    // the query simpler we contain all possible relations, which will
    // usually only be a small number.
    if(empty($this->eisCache[$id])) {
      $this->eisCache[$id] = $this->get($id, ['contain' => $this->getPluginRelations()]);
    }
    
    return $this->eisCache[$id];
  }

  /**
   * Obtain all known source keys for an EIS.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int $id  External Identity Source ID
   * @return array    Source keys
   */

  public function getKnownSourceKeys(int $id): array {
    // For now we don't use an iterator (like PaginatedSqlIterator) because
    // even for the larger deployments we expect to work with, the array of
    // source keys _should_ fit in memory (for a reasonably sized VM/etc).

    $records = $this->ExtIdentitySourceRecords
                    ->find('list', [
                            'keyField' => 'source_key',
                            'valueField' => 'external_identity_id'
                          ])
                    ->where(['external_identity_source_id' => $id])
                    ->toArray();
    
    return array_keys($records); 
  }

  /**
   * Obtain the inventory from the backend.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id         External Identity Source ID
   * @return array              Array of all source keys
   */

  public function inventory(
    int $id,
  ): array|bool {
    $source = $this->getEIS($id);

    $pModel = StringUtilities::pluginModel($source->plugin);

    return $this->$pModel->inventory($source);
  }

  /**
   * Retrieve a record from an External Identity Source.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id         External Identity Source ID
   * @param  string $sourceKey  EIS Backend Source Key
   * @return array              Array of source_key, source_record, and entity_data
   */

  public function retrieve(int $id, string $sourceKey): array {
    $source = $this->getEIS($id);

    $pModel = StringUtilities::pluginModel($source->plugin);

    $record = $this->$pModel->retrieve($source, $sourceKey);

    if($record['entity_data']) {
      // Inject the source key so every backend doesn't have to do this,
      // but only if the backend returned a record.
      $record['entity_data']['source_key'] = $sourceKey;

      $record['entity_data']['identifiers'][] = [
        'identifier' => $sourceKey,
        'type' => 'sorid'
      ];
    }
    
    return $record;
  }

  /**
   * Search the External Identity Source.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $id           External Identity Source ID
   * @param  array  $searchAttrs  Array of search attributes and values, as configured by searchAttributes()
   * @return array                Array of matching records
   */

  public function search(int $id, array $attrs): array {
    $source = $this->getEIS($id);

    $pModel = StringUtilities::pluginModel($source->plugin);

    return $this->$pModel->search($source, $attrs);
  }

  /**
   * Obtain the set of searchable attributes for this backend.
   *
   * @since  COmanage Registry v5.0.0
   * @return array    Array of searchable attributes and localized descriptions
   */

  public function searchableAttributes(int $id) {
    $pModel = $this->pluginModelForEntityId($id);

    return $pModel->searchableAttributes();
  }

  /**
   * Sync an External Identity from a Source to a Person via a Pipeline.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id         External Identity Source ID
   * @param  string $sourceKey  EIS Backend Source Key
   * @param  bool   $force      Whether to force the full Pipeline to run even if the backend record didn't change
   * @param  bool   $syncOnly   Whether to skip identifier assignment and provisioning
   * @param  int    $personId   If set, request the Pipeline to link to this Person (for create operations only)
   * @return string             Record status (new, unchanged, unknown, updated)
   */
  
  public function sync(
    int $id, 
    string $sourceKey, 
    bool $force=true,
    bool $syncOnly=false,
    ?int $personId=null
  ): string {
    // All work is actually handled by the Pipeline, but we need our configuration
    // to know which Pipeline.
    $source = $this->getEIS($id);

    // Also get the current record from the Backend, which might have been deleted
    $eisBackendRecord = $this->retrieve($id, $sourceKey);

    return $this->Pipelines->execute(
      id:               $source->pipeline_id,
      eisId:            $id,
      eisBackendRecord: $eisBackendRecord,
      // Force the full Pipeline run even if the backend record didn't change
      force:            $force,
      personId:         $personId,
      // Do we want the Pipeline to assign identifiers and provision?
      syncOnly:         $syncOnly
    );
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');
    
    $this->registerStringValidation($validator, $schema, 'description', true);
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', SyncModeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'plugin', true);

    $this->registerStringValidation($validator, $schema, 'sor_label', false);

    $validator->add('pipeline_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('pipeline_id');
    
    $validator->add('hash_source_record', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('hash_source_record');

    $validator->add('suppress_noop_logs', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('suppress_noop_logs');

    return $validator; 
  }
}