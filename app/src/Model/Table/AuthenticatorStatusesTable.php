<?php
/**
 * COmanage Registry Authenticator Statuses Table
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\AuthenticatorStatusEnum;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Util\StringUtilities;
use App\Model\Entity\Authenticator;
use App\Model\Entity\AuthenticatorStatus;

class AuthenticatorStatusesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.2.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);
    
    // Define associations
    $this->belongsTo('Authenticators');
    $this->belongsTo('People');

    $this->setDisplayField('id');
    
    $this->setPrimaryLink('person_id');
    $this->setRequiresCO(true);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>     false,
        'edit' =>       false,
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false,
        'index' =>    ['platformAdmin', 'coAdmin']
      ],
      'related' => [
        'table' => [
          'Authenticators'
        ]
      ]
    ]);
  }
  
  /**
   * Obtain the set of Authenticator Statuses for a Person.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int  $personId   Person ID
   * @return array            Array of Authenticator Status, one for each Authenticator configured in the CO
   * @throws RecordNotFoundException
   */

  public function getAllForPerson(int $personId): array {
    $ret = [];

    // We start by mapping the Person to their CO, then retrieving all of the CO's
    // active Authenticators. We use calculateCoId specifically since it will throw
    // an Exception if not found.

    $coId = $this->People->calculateCoId($personId);

    $authenticators = $this->Authenticators->find()
                                           ->where([
                                            'status' => SuspendableStatusEnum::Active,
                                            'co_id'  => $coId
                                           ])
                                           ->contain($this->Authenticators->getPluginRelations())
                                           ->all();

    foreach($authenticators as $authenticator) {
      $ret[] = $this->getForPerson($authenticator, $personId);
    }

    return $ret;
  }

  /**
   * Obtain Authenticator Status for a Person.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int  $authenticator  Authenticator, including plugin model
   * @param  int  $personId       Person ID
   * @return array                AuthenticatorStatus
   * @throws RecordNotFoundException
   */

  public function getForPerson(Authenticator $authenticator, int $personId): AuthenticatorStatus {
    // See if we have an Authenticator Status for this Person.
    // If not, create a placeholder.

    $status = $this->find()
                    ->where([
                      'authenticator_id' => $authenticator->id,
                      'person_id'        => $personId
                    ])
                    ->first();
    
    // This only indicates if the authenticator is locked, and it may not even be present.
    // We need to query the backend for actual status.

    if(!$status) {
      $status = $this->newEntity([
        'authenticator_id'  => $authenticator->id,
        'person_id'         => $personId,
        'locked'            => false
      ]);
    }

    // Query the Plugin for status for this Person, unless the Authenticator is locked
    if($status->locked) {
      $status->status = AuthenticatorStatusEnum::Locked;
      $status->comment = __d('result', 'Authenticators.locked');
    } else {
      $Plugin = $this->Authenticators->authenticatorTable($authenticator->plugin);
      
      $backendStatus = $Plugin->status($authenticator, $personId);

      $status->status = $backendStatus['status'];
      $status->comment = $backendStatus['comment'];
    }

    // Inject additional metadata for the view
    $status->description = $authenticator->description;
    $status->plugin = $authenticator->plugin;

    return $status;
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('authenticator_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('authenticator_id');
    
    $validator->add('person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('person_id');
    
    $validator->add('locked', [
      'content' => ['rule' => 'boolean']
    ]);
    $validator->allowEmptyString('locked');

    return $validator; 
  }
}