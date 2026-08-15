<?php
/**
 * COmanage Registry Clusters Table
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

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Util\StringUtilities;

class ClustersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
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
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Cos');

    $this->bindPluginRelations();

    $this->setDisplayField('description');
    
    $this->setPrimaryLink(['co_id', 'person_id']);
    $this->setRequiresCO(true);
    $this->setAllowUnkeyedPrimaryLink(['assign', 'status']);

    $this->setAutoViewVars([
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'cluster'
      ],
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
        'add' =>      ['platformAdmin', 'coAdmin'],
        'assign' =>   ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
        'status' =>   ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Assign Cluster Accounts for a Person.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  int  $personId   Person to assign accounts for
   * @return array            Array of results
   */

  public function assign(int $personId): array {
    $ret = [
      'assigned' => [],   // Array of Clusters
      'existing' => [],   // Array of Clusters
      'error'    => []    // Hash keyed on Cluster ID, with 'cluster' and 'message' keys
    ];

    // First we need the CO for the Person
    $person = $this->Cos->People->get($personId);

    // Now we can pull the list of configured (active) Clusters

    $clusters = $this->find()
                     ->where([
                      'co_id' => $person->co_id,
                      'status' => SuspendableStatusEnum::Active
                     ])
                     ->contain($this->getPluginRelations())
                     ->all();
    
    foreach($clusters as $cluster) {
      $Plugin = TableRegistry::getTableLocator()->get($cluster->plugin);

      try {
        if($Plugin->assign($cluster, $personId)) {
          $ret['assigned'][] = $cluster;
        } else {
          $ret['existing'][] = $cluster;
        }
      }
      catch(\Exception $e) {
        $ret['error'][$cluster->id] = [
          'cluster' => $cluster,
          'message' => $e->getMessage()
        ];
      }
    }

    return $ret;
  }

  /**
   * Obtain Cluster status for a Person.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  int  $personId   Person to obtain status for
   * @return array            Array of status information for each defined Cluster
   */

  public function status(int $personId): array {
    $ret = [];

    // First we need the CO for the Person
    $person = $this->Cos->People->get($personId);

    // Now we can pull the list of configured (active) Clusters

    $clusters = $this->find()
                     ->where([
                      'co_id' => $person->co_id,
                      'status' => SuspendableStatusEnum::Active
                     ])
                     ->contain($this->getPluginRelations())
                     ->all();
    
    foreach($clusters as $cluster) {
      $Plugin = TableRegistry::getTableLocator()->get($cluster->plugin);
      // $property = StringUtilities::pluginToEntityField($cluster->plugin);

      $status = $Plugin->status($cluster, $personId);

      $r = [
        'cluster' => $cluster,
        'status' => $status['status'],
        'comment' => $status['comment']
      ];

      $ret[] = $r;
    }

    return $ret;
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
    
    $this->registerStringValidation($validator, $schema, 'description', true);
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'plugin', true);

    return $validator; 
  }
}