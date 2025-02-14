<?php
/**
 * COmanage Registry Identifier Collectors Table Table
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreEnroller\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\SuspendableStatusEnum;

class IdentifierCollectorsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TabTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.1.0
   * @param  array  $config Configuration options passed to constructor
   */

  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('EnrollmentFlowSteps');
    $this->belongsTo('Types');

    // We intentionally don't hasMany PetitionIdentifiers since there should only be one
    // collector per Petition, and the net result of not having the direct foreign key
    // is that if an admin instantiates the plugin multiple times the second instance
    // will refuse to do anything.

    $this->setDisplayField('id');

    $this->setPrimaryLink('enrollment_flow_step_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['dispatch', 'display']);
    
    // All the tabs share the same configuration in the ModelTable file
    $this->setTabsConfig(
      [
        // Ordered list of Tabs
        'tabs' => ['EnrollmentFlowSteps', 'CoreEnroller.IdentifierCollectors'],
        // What actions will include the subnavigation header
        'action' => [
          // If a model renders in a subnavigation mode in edit/view mode, it cannot
          // render in index mode for the same use case/context
          // XXX edit should go first.
          'EnrollmentFlowSteps' => ['edit', 'view'],
          'CoreEnroller.IdentifierCollectors' => ['edit']
        ]
      ]
    );
    
    $this->setAutoViewVars([
      'types' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'dispatch' => true,
        'display' =>  true,
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false, // This is added by the parent model
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Perform steps necessary to hydrate the Person record as part of Petition finalization.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int      $id           Invitation Accepter ID
   * @param  Petition $petition     Petition
   * @return bool                   true on success
   */

  public function hydrate(int $id, \App\Model\Entity\Petition $petition) {
    $cfg = $this->get($id);

    // Pull the Identifier that was recorded

    $PetitionIdentifiers = TableRegistry::getTableLocator()->get('CoreEnroller.PetitionIdentifiers');

    $pidentifier = $PetitionIdentifiers->find()->where(['petition_id' => $petition->id])->first();

    if(!empty($pidentifier->identifier)) {
      // Copy the Identifier to the Person record in accordance with the configuration

      $Identifiers = TableRegistry::getTableLocator()->get('Identifiers');

      $id = $Identifiers->newEntity([
        'person_id'   => $petition->enrollee_person_id,
        'identifier'  => $pidentifier->identifier,
        'type_id'     => $cfg->type_id,
        'status'      => SuspendableStatusEnum::Active,
        'login'       => true
      ]);

      $Identifiers->saveOrFail($id);
    }

    return true;
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

    $validator->add('enrollment_flow_step_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('enrollment_flow_step_id');

    $validator->add('type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('type_id');

    return $validator;
  }
}
