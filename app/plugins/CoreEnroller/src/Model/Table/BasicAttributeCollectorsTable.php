<?php
/**
 * COmanage Registry Basic Attribute Collectors Table
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
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\PetitionActionEnum;
use App\Lib\Enum\StatusEnum;

class BasicAttributeCollectorsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TabTrait;
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
    $this->belongsTo('EmailAddressTypes')
         ->setClassName('Types')
         ->setForeignKey('email_address_type_id')
         ->setProperty('email_address_type');
    $this->belongsTo('NameTypes')
         ->setClassName('Types')
         ->setForeignKey('name_type_id')
         ->setProperty('name_type');
    $this->belongsTo('AffiliationTypes')
         ->setClassName('Types')
         ->setForeignKey('affiliation_type_id')
         ->setProperty('affiliation_type');
    $this->belongsTo('Cous');

    $this->hasMany('CoreEnroller.PetitionBasicAttributeSets')
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
        'tabs' => ['EnrollmentFlowSteps', 'CoreEnroller.BasicAttributeCollectors'],
        // What actions will include the subnavigation header
        'action' => [
          // If a model renders in a subnavigation mode in edit/view mode, it cannot
          // render in index mode for the same use case/context
          // XXX edit should go first.
          'EnrollmentFlowSteps' => ['edit', 'view'],
          'CoreEnroller.BasicAttributeCollectors' => ['edit'],
        ]
      ]
    );

    $this->setAutoViewVars([
      'affiliationTypes' => [
        'type' => 'type',
        'attribute' => 'PersonRoles.affiliation_type'
      ],
      'cous' => [
        'type' => 'select',
        'model' => 'Cous'
      ],
      'emailAddressTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ],
      'nameTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type'
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
      ],
      'related' => [
        'table' => [
          'CoreEnroller.EnrollmentAttributes'
        ]
      ]
    ]);
  }

  /**
   * Perform steps necessary to hydrate the Person record as part of Petition finalization.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int      $id           Basic Attribute Collector ID
   * @param  Petition $petition     Petition
   * @return bool                   true on success
   */

  public function hydrate(int $id, \App\Model\Entity\Petition $petition) {
    $cfg = $this->get($id);

    // At this point there is a Person record allocated and stored in the Petition,
    // but it doesn't have any attributes on it, including a Primary Name.
    // We assume we're the only attribute collector, so we'll force a Primary Name
    // based on the Basic Attributes, and create a skeletal role.

    if(empty($petition->enrollee_person_id)) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.enrollee.notfound', [$petition->id]));
    }

    $People = TableRegistry::getTableLocator()->get('People');
    
    $person = $People->get($petition->enrollee_person_id);

    $attributes = $this->PetitionBasicAttributeSets
                       ->find()
                       ->where([
                         'petition_id' => $petition->id,
                         // Strictly speaking we only support one instance per Flow,
                         // but we'll filter on the $id anyway since we have it
                         'basic_attribute_collector_id' => $id
                       ])
                       ->firstOrFail();

    // Since we're not modifying $person, it's a bit clearer if we save each entity
    // individually than try to save related

    $Names = TableRegistry::getTableLocator()->get('Names');

// XXX enforce CoSettings required/permitted fields here?
    $name = [
      'person_id'     => $person->id,
      'primary_name'  => true,
      'type_id'       => $cfg->name_type_id
    ];
    
    foreach(['honorific', 'given', 'middle', 'family', 'suffix'] as $n) {
      if(!empty($attributes->$n)) {
        $name[$n] = $attributes->$n;
      }
    }

    $Names->saveOrFail($Names->newEntity($name));

    $EmailAddresses = TableRegistry::getTableLocator()->get('EmailAddresses');

    $email = [
      'person_id' => $person->id,
      'mail'      => $attributes->mail,
      'type_id'   => $cfg->email_address_type_id
    ];

    $EmailAddresses->saveOrFail($EmailAddresses->newEntity($email));

    $PersonRoles = TableRegistry::getTableLocator()->get('PersonRoles');

    $personRole = [
      'person_id'           => $person->id,
      'affiliation_type_id' => $cfg->affiliation_type_id,
      'cou_id'              => $cfg->cou_id,
      'status'              => StatusEnum::Active
    ];

    $PersonRoles->saveOrFail($PersonRoles->newEntity($personRole));

    $PetitionHistoryRecords = TableRegistry::getTableLocator()->get('PetitionHistoryRecords');

    $PetitionHistoryRecords->record(
      petitionId:           $petition->id, 
      enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
      action:               PetitionActionEnum::Finalized,
      comment:              __d('core_enroller', 'result.basicattr.finalized')
// We don't have $actorPersonId yet...
//    ?int $actorPersonId=null
    );

    return true;
  }

  /**
   * Insert or update a Basic Petition Attribute Set.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int    $id           Basic Attribute Collector ID
   * @param  int    $petitionId   Petition ID
   * @param  array  $attributes   Petition Attributes
   * @return bool                 true on success
   * @throws PersistenceFailedException
   */

  public function upsert(int $id, int $petitionId, array $attributes) {
    $basicAttributeCollector = $this->get($id);

    // Do we have existing attributes for this petition? Note this will pull
    // _all_ attributes for the Petition, not just those associated with this
    // particular Attribute Collector; however we'll only look at the attributes
    // we need below.
    $entity = $this->PetitionBasicAttributeSets
                   ->find()
                   ->where([
                     'petition_id' => $petitionId,
                     // Strictly speaking we only support one instance per Flow,
                     // but we'll filter on the $id anyway since we have it
                     'basic_attribute_collector_id' => $id
                   ])
                   ->first();

    if(!$entity) {
      // insert, not update

      $entity = $this->PetitionBasicAttributeSets->newEntity([
        'basic_attribute_collector_id'  => $id,
        'petition_id'                   => $petitionId
      ]);
    }

    foreach(['honorific', 'given', 'middle', 'family', 'suffix', 'mail'] as $f) {
// XXX we should probably check CoSettings for name settings
      $entity->$f = $attributes[$f] ?? null;
    }

    $this->PetitionBasicAttributeSets->saveOrFail($entity);

    // Record Petition History

    $PetitionHistoryRecords = TableRegistry::getTableLocator()->get('PetitionHistoryRecords');

    $PetitionHistoryRecords->record(
      petitionId:           $petitionId, 
      enrollmentFlowStepId: $basicAttributeCollector->enrollment_flow_step_id,
      action:               PetitionActionEnum::AttributesUpdated,
      comment:              __d('core_enroller', 'result.attr.saved')
// We don't have $actorPersonId yet...
//    ?int $actorPersonId=null
    );

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

    $validator->add('name_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('name_type_id');
    
    $validator->add('email_address_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('email_address_type_id');

    $validator->add('affiliation_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('affiliation_type_id');

    $validator->add('cou_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('cou_id');

    return $validator;
  }

  /**
   * Obtain the set of Email Addresses known to this plugin that are eligible for
   * verification or that have already been verified.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  EntityInterface  $config       Configuration entity for this plugin
   * @param  int              $petitionId   Petition ID
   * @return array                          Array of Email Addresses and verification status
   */

  public function verifiableEmailAddresses(
    EntityInterface $config, 
    int $petitionId
  ): array {
    $set = $this->PetitionBasicAttributeSets->find()
                                            ->where([
                                              'basic_attribute_collector_id' => $config->id,
                                              'petition_id' => $petitionId
                                            ])
                                            ->first();
    
    return !empty($set->mail) ? [$set->mail => false] : [];
  }
}
