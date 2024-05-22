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
   * Retrieve a record from an External Identity Source.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id         External Identity Source ID
   * @param  string $source_key EIS Backend Source Key
   * @return array              Array of source_key, source_record, and entity_data
   */

  public function retrieve(int $id, string $source_key): array {
    // We want to pull the plugin configuration along with the EIS, to make
    // the query simpler we contain all possible relations, which will
    // usually only be a small number.
    $source = $this->get($id, ['contain' => $this->getPluginRelations()]);

    $pModel = StringUtilities::pluginModel($source->plugin);

    $record = $this->$pModel->retrieve($source, $source_key);

    // Inject the source key so every backend doesn't have to do this
    $record['entity_data']['source_key'] = $source_key;

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
    // We want to pull the plugin configuration along with the EIS, to make
    // the query simpler we contain all possible relations, which will
    // usually only be a small number.
    $source = $this->get($id, ['contain' => $this->getPluginRelations()]);

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
   * @param  string $source_key EIS Backend Source Key
   */
  
  public function sync(int $id, string $source_key) {
    // All work is actually handled by the Pipeline, but we need our configuration
    // to know which Pipeline.
    $eis = $this->get($id);

    // Also get the current record from the Backend, which might have been deleted
    $eisBackendRecord = $this->retrieve($id, $source_key);

    $this->Pipelines->execute(
      id:               $eis->pipeline_id,
      eisId:            $id,
      eisBackendRecord: $eisBackendRecord,
      // Force the full Pipeline run even if the backend record didn't change
      force:            true
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
    
    $this->registerStringValidation($validator, $schema, 'description', false);
    
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
    
    return $validator; 
  }
}