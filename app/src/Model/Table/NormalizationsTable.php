<?php
/**
 * COmanage Registry Normalizations Table
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use \Cake\ORM\Table;
use \Cake\ORM\TableRegistry;
use \Cake\Validation\Validator;
use \App\Lib\Enum\SuspendableStatusEnum;

class NormalizationsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PluggableModelTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.3.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    $this->addBehavior('Changelog');
    $this->addBehavior('Orderable');
    $this->addBehavior('Timestamp');
    $this->addBehavior('Timezone');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Cos');
    
    $this->setPluginRelations();

    $this->setDisplayField('plugin');
    
    $this->setPrimaryLink('co_id');
    $this->setRequiresCO(true);
    
    $this->setAutoViewVars([
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SuspendableStatusEnum'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'configure' =>  ['platformAdmin', 'coAdmin'],
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        // Normalizations are added automatically
        'add' =>    false,
        'index' =>  ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Sync Normalizations for the requested CO.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  int  $coId   CO ID to sync normalizations for
   * @param  bool $active If true, any new Plugins will be set to Active status
   */

  public function syncNormalizationIndex(int $coId, bool $active=false) {
    $Plugins = TableRegistry::getTableLocator()->get('Plugins');

    foreach(array_keys($Plugins->getActivePluginModels('normalization')) as $p) {
      $Normalizer = TableRegistry::getTableLocator()->get($p);

      // If there's already an entry for this plugin and this CO ID, don't do anything
      $count = $this->find()->where(['co_id' => $coId, 'plugin' => $p])->count();

      if($count == 0) {
        // Insert a new row, and then tell the Plugin to create an new configuration.
        // (We don't just insert a blank record for the Plugin here since a configuration
        // could be arbitrarily complex, better to let the Plugin handle it.)

        $normalization = $this->newEntity([
          'co_id'   => $coId,
          'plugin'  => $p,
          'status'  => $active ? SuspendableStatusEnum::Active : SuspendableStatusEnum::Suspended
        ]);

        $this->save($normalization);

        try {
          $Normalizer->createDefaultConfig($normalization);
        }
        catch(\Exception $e) {
          // On error we'll delete $normalization so the admin can retry later

          $this->delete($normalization);

          throw $e;
        }
      }
    }
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');

    $this->registerStringValidation($validator, $schema, 'plugin', true);

    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    
    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');

    return $validator; 
  }
}