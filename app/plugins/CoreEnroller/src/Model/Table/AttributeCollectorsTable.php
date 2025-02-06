<?php
/**
 * COmanage Registry Attribute Collectors Table
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
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreEnroller\Model\Table;

use App\Model\Entity\Petition;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use App\Lib\Enum\PetitionActionEnum;

class AttributeCollectorsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TabTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
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

    $this->hasMany('CoreEnroller.EnrollmentAttributes')
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
        'tabs' => ['EnrollmentFlowSteps', 'CoreEnroller.AttributeCollectors', 'CoreEnroller.EnrollmentAttributes'],
        // What actions will include the subnavigation header
        'action' => [
          // If a model renders in a subnavigation mode in edit/view mode, it cannot
          // render in index mode for the same use case/context
          // XXX edit should go first.
          'EnrollmentFlowSteps' => ['edit', 'view'],
          'CoreEnroller.AttributeCollectors' => ['edit'],
          'CoreEnroller.EnrollmentAttributes' => ['index'],
        ]
      ]
    );

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
   * Perform steps necessary to finalize the Petition.
   *
   * @param int $id Attribute Collector ID
   * @param Petition $petition
   * @return bool                 true on success
   * @since  COmanage Registry v5.1.0
   */

  public function finalize(int $id, \App\Model\Entity\Petition $petition) {
    $cfg = $this->get($id);

    if(empty($petition->enrollee_person_id)) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.enrollee.notfound', [$petition->id]));
    }

    $People = TableRegistry::getTableLocator()->get('People');

    $person = $People->get($petition->enrollee_person_id);

    $attributes = $this->EnrollmentAttributes
      ->PetitionAttributes
      ->find()
      ->where(['petition_id' => $petition->id])
      ->firstOrFail();

    // XXX Should i save the primary name?
    // XXX Should i save the email?

    return true;
  }

  /**
   * Insert or update a set of Petition Attributes.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id           Attribute Collector ID
   * @param  int    $petitionId   Petition ID
   * @param  array  $attributes   Petition Attributes
   * @return bool                 true on success
   * @throws PersistenceFailedException
   */

  public function upsert(int $id, int $petitionId, array $attributes) {
    $attributeCollector = $this->get($id);

    // Do we have existing attributes for this petition? Note this will pull
    // _all_ attributes for the Petition, not just those associated with this
    // particular Attribute Collector; however we'll only look at the attributes
    // we need below.
    $currentAttributes = $this->EnrollmentAttributes
                              ->PetitionAttributes->find('list', [
                                                          'keyField' => 'enrollment_attribute_id',
                                                          'valueField' => 'id'
                                                        ])
                                                  ->where(['petition_id' => $petitionId])
                                                  ->toArray();

    $petitionAttributes = [];

    foreach($attributes as $enrollmentAttributeLabel => $value) {
      // Remove field- prefix from the form field name
      $fieldToParts = explode('-', $enrollmentAttributeLabel);
      // MVAs have multiple columns. For example a name has:
      // given, surname, prefix, ...
      $columnName = null;
      if (count($fieldToParts) > 2) {
        // There is a type ID in the middle
        $columnName = $fieldToParts[1];
      }
      // The enrollment Attribute ID is the last part
      $enrollmentAttributeId = (int)array_pop($fieldToParts);

      // The people picker will send a complex string and not just the id. We need to extract it ourselves.
      $re = '/^.*\(ID: (\d+)\)$/m';
      preg_match_all($re, $value, $matches, PREG_SET_ORDER, 0);
      if(!empty($matches)) {
        $value = $matches[0][1];
      }

      $newAttribute = [
        'petition_id'             => $petitionId,
        'enrollment_attribute_id' => $enrollmentAttributeId,
        'value'                   => $value,
        // This is the column name for the attributes that consist of multiple fields, like the name or the address
        'column_name'             => $columnName,
      ];

      if(\array_key_exists($enrollmentAttributeId, $currentAttributes)) {
        // This is an update of an existing attribute

        $newAttribute['id'] = $currentAttributes[$enrollmentAttributeId];

        $entity = $this->EnrollmentAttributes
                       ->PetitionAttributes->get($newAttribute['id']);

        // We don't bother with patch entity since the only thing we support
        // changing is value

        $entity->value = $value;

        $this->EnrollmentAttributes->PetitionAttributes->saveOrFail($entity);
// XXX we could record petition history that this specific attribute was updated
      } else {
        // This is a new attribute
        $petitionAttributes[] = $newAttribute;
      }
    }

    if(!empty($petitionAttributes)) {
      $entities = $this->EnrollmentAttributes->PetitionAttributes->newEntities($petitionAttributes);

      $this->EnrollmentAttributes->PetitionAttributes->saveManyOrFail($entities);
    }

    // Record Petition History

    $PetitionHistoryRecords = TableRegistry::getTableLocator()->get('PetitionHistoryRecords');

    $PetitionHistoryRecords->record(
      petitionId:           $petitionId, 
      enrollmentFlowStepId: $attributeCollector->enrollment_flow_step_id,
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
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */

  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('enrollment_flow_step_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('enrollment_flow_step_id');

    $this->registerStringValidation($validator, $schema, 'description', true);
    
    return $validator;
  }
}
