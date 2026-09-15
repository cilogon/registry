<?php
/**
 * COmanage Registry Person Selectors Table Table
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

namespace CoreEnroller\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use CoreEnroller\Lib\Enum\PersonSelectorConfirmationModeEnum;
use CoreEnroller\Lib\Enum\PersonSelectorModeEnum;
use App\Lib\Enum\PetitionActionEnum;

class PersonSelectorsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
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
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('EnrollmentFlowSteps');
    $this->belongsTo('RoeEnrollmentFlows')
         ->setClassName('EnrollmentFlows')
         ->setForeignKey('roe_enrollment_flow_id')
         ->setProperty('roe_enrollment_flow');

    $this->hasManyPlugins([
      'EnrollmentFlows' => [
        [
          'alias' => 'PersonSelectorEnrollmentFlows',
          'targetModel' => 'CoreEnroller.PersonSelectors',
          'config' => [
            'foreignKey' => 'roe_enrollment_flow_id'
          ]
        ]
      ]
    ]);

    $this->setDisplayField('id');

    $this->setPrimaryLink('enrollment_flow_step_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['dispatch', 'display']);
    
    $this->setAutoViewVars([
      'confirmationPageModes' => [
        'type' => 'enum',
        'class' => 'CoreEnroller.PersonSelectorConfirmationModeEnum'
      ],
      'modes' => [
        'type' => 'enum',
        'class' => 'CoreEnroller.PersonSelectorModeEnum'
      ],
      'roeEnrollmentFlows' => [
        'type' => 'select',
        'model' => 'EnrollmentFlows'
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
   * Attach a Person as the Enrollee for a Petition. The action is completed immediately,
   * it is not deferred until finalization.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  int  $id           Person Selector ID
   * @param  int  $petitionId   Petition ID
   * @param  int  $personId     Enrollee Person ID
   * @return bool               true on success
   */

  public function attachPerson(int $id, int $petitionId, int $personId): bool {
    $cfg = $this->get($id);

    // Attach the selected Person to the Petition.

    $Petitions = TableRegistry::getTableLocator()->get('Petitions');

    $petition = $Petitions->get($petitionId);

    $petition->enrollee_person_id = $personId;
    
    $Petitions->saveOrFail($petition);

    // Record history

    $Petitions->PetitionHistoryRecords->record(
      petitionId:           $petition->id,
      enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
      action:               PetitionActionEnum::PersonAttached,
      comment:              __d('core_enroller', 'result.PersonSelectors.attached', [$personId, __d('core_enroller', 'enumeration.PersonSelectorModeEnum.'.$cfg->mode)])
    );

    return true;
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

    $validator->add('enrollment_flow_step_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('enrollment_flow_step_id');

    $validator->add('mode', [
      'content' => ['rule' => ['inList', PersonSelectorModeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('mode');

// XXX required when mode = Select
    $validator->add('confirmation_page_mode', [
      'content' => ['rule' => ['inList', PersonSelectorConfirmationModeEnum::getConstValues()]]
    ]);
    $validator->allowEmptyString('confirmation_page_mode');

    $validator->add('roe_enrollment_flow_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('roe_enrollment_flow_id');

    return $validator;
  }
}
