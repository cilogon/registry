<?php
/**
 * COmanage Registry Petition Agreements Table
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace TermsAgreer\Model\Table;

use Cake\I18n\FrozenTime;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\PetitionActionEnum;
use App\Lib\Enum\PetitionStatusEnum;

class PetitionAgreementsTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\UpsertTrait;
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

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);

    // Define associations
    $this->belongsTo('TermsAgreer.AgreementCollectors');
    $this->belongsTo('Petitions');
    $this->belongsTo('TermsAndConditions')
         // It's unclear why, but Cake isn't inflecting the property key correctly here
         // even though it does elsewhere (maybe something related to this being a plugin?)
         ->setProperty('terms_and_conditions')
         ->setForeignKey('terms_and_conditions_id');

    $this->setDisplayField('agreement_time');

    $this->setPrimaryLink('petition_id');
    $this->setRequiresCO(true);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false,
        'edit' =>     false,
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false,
        'index' =>    false
      ]
    ]);
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

    $validator->add('petition_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('petition_id');

    // Strictly speaking we don't require agreement_collector_id because we always
    // collect all T&C, at least in the current implementation. We use it partly
    // for consistency with the the CoreEnroller plugins, and partly for future
    // proofing (in case we eg support collecting different T&C at different times).
    $validator->add('agreement_collector_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('agreement_collector_id');

    $validator->add('terms_and_conditions_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('terms_and_conditions_id');

    $this->registerStringValidation($validator, $schema, 'identifier', true);

    $validator->add('agreement_time', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->notEmptyString('agreement_time');
    
    return $validator;
  }
}
