<?php
/**
 * COmanage Registry Traffic Detours Table
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Model\Entity\TrafficDetour;

class TrafficDetoursTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PluggableModelTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.1.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Orderable');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    $this->setPluginRelations();

    $this->setDisplayField('description');
    
    $this->setRequiresCO(false);

    $this->setAutoViewVars([
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'traffic_detour'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SuspendableStatusEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'configure' =>  ['platformAdmin'],
        'delete' =>     ['platformAdmin'],
        'edit' =>       ['platformAdmin'],
        'view' =>       ['platformAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin'],
        'index' =>    ['platformAdmin']
      ]
    ]);
  }
  
  /**
   * Determine the next Traffic Detour to process, given the last Traffic Detour to run.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $lastDetourId   ID of last Traffic Detour to run, or 0 if none
   * @return TrafficDetour        The next Traffic Detour to process, or null if none left
   */

  public function calculateNextDetour(string $context, int $lastDetourId=0): ?TrafficDetour {
    // Start by pulling the configured set of Traffic Detours

    $detours = $this->find()
                          ->where(['status' => SuspendableStatusEnum::Active])
                          ->order(['TrafficDetours.ordr' => 'ASC'])
                          ->all();

    // Should we return the next Traffic Detour? If we haven't seen any yet then we
    // want to return the first one.
    $returnNext = ($lastDetourId == 0);

    if(!empty($detours)) {
      foreach($detours as $detour) {
        if($returnNext) {
          // Only return this detour if it supports the requested context
          $PluginTable = TableRegistry::getTableLocator()->get($detour->plugin);

          if($PluginTable->supportsDetourContext($context)) {
            return $detour;
          }
          // else keep trying
        } elseif($detour->id == $lastDetourId) {
          $returnNext = true;
        }
      }
    }

    // We're done, or nothing to return
    return null;
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $this->registerStringValidation($validator, $schema, 'description', true);

    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'plugin', true);

    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');
    
    return $validator; 
  }
}