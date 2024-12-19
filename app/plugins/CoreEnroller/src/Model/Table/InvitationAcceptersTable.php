<?php
/**
 * COmanage Registry Invitation Accepters Table Table
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
use App\Lib\Enum\PetitionActionEnum;
use App\Lib\Enum\PetitionStatusEnum;
use App\Lib\Enum\StatusEnum;

class InvitationAcceptersTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
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

    // We intentionally don't hasMany PetitionAcceptances since there should only be one
    // acceptance per Petition, and the net result of not having the direct foreign key
    // is that if an admin instantiates the plugin multiple times the second instance
    // will refuse to do anything.

    $this->setDisplayField('id');

    $this->setPrimaryLink('enrollment_flow_step_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['dispatch', 'display']);

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
   * Perform steps necessary to finalize the Petition.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int      $id           Invitation Accepter ID
   * @param  Petition $petition     Petition
   * @return bool                   true on success
   */

  public function finalize(int $id, \App\Model\Entity\Petition $petition) {
    // $cfg = $this->get($id);

    // We don't have anything to do for finalization

    return true;
  }

  /**
   * Perform tasks prior to transitioning to this step.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  EnrollmentFlowStep $step     Enrollment Flow Step
   * @param  Petition           $petition Petition
   * @return bool                         true on success
   */

  public function prepare(
    \App\Model\Entity\EnrollmentFlowStep $step,
    \App\Model\Entity\Petition $petition
  ): bool {
    // Allocate an Invitation. We create an empty petition_acceptance to use to measure
    // invitation validity (based on the created timestamp).

    $PetitionAcceptances = TableRegistry::getTableLocator()->get('CoreEnroller.PetitionAcceptances');

    $acceptance = $PetitionAcceptances->find()
                                      ->where(['petition_id' => $petition->id])
                                      ->first();

    if(!empty($acceptance)) {
      // We'll allow a null (pending) invitation, but if there is already a response
      // we throw an error. This probably isn't the best behavior, once we have some
      // more requirements we should probably change this to do something else (eg:
      // fast forward to the next step).

      if(!is_null($acceptance->accepted)) {
        throw new \RuntimeException(__d('core_enroller', 'error.PetitionAcceptances.exists'));
      }

      // We don't reset the Petition status, at least pending further requirements.
    } else {
      // Register a new, pending invitation

      $acceptance = $PetitionAcceptances->newEntity([
        'petition_id' => $petition->id,
        'accepted'    => null
      ]);

      // If for some reason there is already an acceptance record
      // for this petition we'll basically reset it
      $PetitionAcceptances->saveOrFail($acceptance);

      // Set this petition to Pending Invitation

      $Petitions = TableRegistry::getTableLocator()->get('Petitions');

      $petition->status = PetitionStatusEnum::PendingAcceptance;

      $Petitions->saveOrFail($petition);
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

    $validator->add('invitation_validity', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('invitation_validity');

    $validator->add('welcome_message', [
      'filter'  => ['rule'     => ['validateInput'],
                    'provider' => 'table']
    ]);
    $validator->allowEmptyString('welcome_message');

    return $validator;
  }
}
