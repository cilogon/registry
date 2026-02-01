<?php
/**
 * COmanage Registry T&C Agreement Collectors Table Table
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

use App\Lib\Enum\PetitionActionEnum;
use App\Lib\Enum\TableTypeEnum;
use Cake\ORM\ResultSet;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use TermsAgreer\Lib\Enum\TAndCEnrollmentModeEnum;

class AgreementCollectorsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;  
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TabTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.2.0
   * @param  array  $config Configuration options passed to constructor
   */

  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');

    $this->setTableType(TableTypeEnum::Configuration);

    // Define associations
    $this->belongsTo('EnrollmentFlowSteps');

    $this->hasMany('TermsAgreer.PetitionAgreements')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setDisplayField('id');

    $this->setPrimaryLink('enrollment_flow_step_id');
    $this->setRequiresCO(true);
    $this->setAllowLookupPrimaryLink(['dispatch', 'display']);
    
    // All the tabs share the same configuration in the ModelTable file
    $this->setTabsConfig(
      [
        // Ordered list of Tabs
        'tabs' => ['EnrollmentFlowSteps', 'TermsAgreer.AgreementCollectors'],
        // What actions will include the subnavigation header
        'action' => [
          // If a model renders in a subnavigation mode in edit/view mode, it cannot
          // render in index mode for the same use case/context
          // XXX edit should go first.
          'EnrollmentFlowSteps' => ['edit', 'view'],
          'TermsAgreer.AgreementCollectors' => ['edit']
        ]
      ]
    );

    $this->setAutoViewVars([
      'tAndCModes' => [
        'type' => 'enum',
        'class' => 'TermsAgreer.TAndCEnrollmentModeEnum'
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
   * @since  COmanage Registry v5.2.0
   * @param  int      $id           Approval Collector ID
   * @param  Petition $petition     Petition
   * @return bool                   true on success
   */

  public function hydrate(int $id, \App\Model\Entity\Petition $petition) {
    // We don't currently need the configuration for anything
    // $cfg = $this->get($id);

    if(empty($petition->enrollee_person_id)) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.enrollee.notfound', [$petition->id]));
    }

    $TAndCAgreements = TableRegistry::getTableLocator()->get('TAndCAgreements');

    // Retrieve the Petition Agreements and convert each one to a T&C Agreement.
    // Fon certain types of Enrollments, it's possible that the Enrollee has already
    // agreed to one or more T&C, but that's OK, we can simply add a new Agreement
    // that will supercede the previous one.

    $agreements = $this->PetitionAgreements
                       ->find()
                       ->where([
                         'petition_id' => $petition->id,
                         // Strictly speaking since T&C are an all-or-nothing thing in the
                         // current implementation, we don't really care about the
                         // agreement_collector_id, but we'll filter for it anyway to
                         // maintain consistency
                         'agreement_collector_id' => $id
                       ])
                       ->all();

    foreach($agreements as $pa) {
      $TAndCAgreements->record(
        termsAndConditionsId: $pa->terms_and_conditions_id,
        personId:             $petition->enrollee_person_id,
        actorPersonId:        $petition->enrollee_person_id,
        identifier:           $pa->identifier,
        agreementTime:        $pa->agreement_time->getTimestamp(),
        petitionId:           $petition->id
      );
    }

    // We recorded Petition History when the T&C were processed, and TAndCAgreements
    // will record History above, so we don't really need to record any more here.

    return true;
  }
  
  /**
   * Record T&C Agreements. 
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int        $petitionId           Petition ID
   * @param  int        $agreementCollectorId Agreement Collector ID
   * @param  string     $identifier           Authenticated Identifier of Agreer
   * @param  ResultSet  $tAndC                Set of TermsAndConditions that were agreed to
   * @throws \InvalidArgumentException
   */

  public function record(
    int       $petitionId,
    int       $agreementCollectorId,
    string    $identifier,
    ResultSet $tAndC
  ) {
    $cfg = $this->get($agreementCollectorId);

    $Petitions = TableRegistry::getTableLocator()->get('Petitions');

    $petition = $Petitions->get($petitionId);

    // Walk the set of $tAndC, upserting PetitionAgreements for each

    foreach($tAndC as $tc) {
      // Store the PetitionAgreement

      $this->PetitionAgreements->upsertOrFail(
        data: [
          'petition_id'             => $petitionId,
          'agreement_collector_id'  => $agreementCollectorId,
          'terms_and_conditions_id' => $tc->id,
          'identifier'              => $identifier,
          // We could probably use changelog timestamps, but it's clearer to record
          // an explicit agreement_time. (We'd have to use modified instead of created
          // in case we upsert, but then other changes could theoretically update the
          // modified timestamp).
          'agreement_time'          => date('Y-m-d H:i:s', time())
        ],
        whereClause: [
          'petition_id'             => $petitionId,
          'agreement_collector_id'  => $agreementCollectorId,
          'terms_and_conditions_id' => $tc->id
        ]
      );
      
      // Record PetitionHistory

      $Petitions->PetitionHistoryRecords->record(
        petitionId:           $petitionId,
        enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
        action:               $cfg->t_and_c_mode == TAndCEnrollmentModeEnum::ExplicitConsent ? PetitionActionEnum::TCExplicitAgreement : PetitionActionEnum::TCImpliedAgreement,
        comment:              __d('terms_agreer', 'result.AgreementCollectors.recorded', [$tc->description, $tc->id, $cfg->t_and_c_mode])
      );
    }
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

    $validator->add('enrollment_flow_step_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('enrollment_flow_step_id');

    $validator->add('t_and_c_mode', [
      'content' => ['rule' => ['inList', TAndCEnrollmentModeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('t_and_c_mode');

    return $validator;
  }
}
