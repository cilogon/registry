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

use App\Lib\Enum\PetitionActionEnum;
use App\Lib\Enum\StatusEnum;
use App\Model\Entity\Person;
use App\Model\Entity\PersonRole;
use Cake\Collection\Collection;
use Cake\Database\Connection;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Validation\Validator;

class AttributeCollectorsTable extends Table {
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
   * Perform steps necessary to hydrate the Person record as part of Petition finalization.
   *
   * @param int $id Attribute Collector ID
   * @param \App\Model\Entity\Petition $petition
   * @return bool                 true on success
   * @since  COmanage Registry v5.1.0
   */

  public function hydrate(int $id, \App\Model\Entity\Petition $petition): bool
  {
    $cfg = $this->get($id);

    if(empty($petition->enrollee_person_id)) {
      throw new \InvalidArgumentException(__d('error', 'Petitions.enrollee.notfound', [$petition->id]));
    }

    // I will get the model from the supported attributes.
    $supportedAttributes = $this->EnrollmentAttributes->supportedAttributes();

    $People = TableRegistry::getTableLocator()->get('People');

    $person = $People->get($petition->enrollee_person_id);
    $role = null;

    $attributes = $this->EnrollmentAttributes
      ->PetitionAttributes
      ->find()
      ->where(['petition_id' => $petition->id])
      ->contain(
        ['EnrollmentAttributes' => ['AttributeTypes']]
      )->toArray();

    // Get the collection object
    $attributesCollection = new Collection($attributes);


    /*********** PERSON ROLE  ***************/
    // Filter the Person Role Attributes and keep the field name
    $personRoleAttributes = (new Collection($supportedAttributes))->filter(function($attr, $key) {
      return isset($attr['model']) && $attr['model'] == 'PersonRole';
    })->toArray();
    $personRoleAttributes = array_keys($personRoleAttributes);

    // Get all the fields/values required to build the PersonRole
    $fieldsForPersonRole = $attributesCollection->filter(function($attr, $key) use ($personRoleAttributes) {
      return in_array($attr['enrollment_attribute']['attribute'], $personRoleAttributes);
    })->toArray();

    // Start a Transaction
    $cxn = $this->getConnection();
    $cxn->begin();

    // Save the Person Role
    $personRoleObj = TableRegistry::getTableLocator()->get('PersonRoles');
    $role = $personRoleObj->saveAttributes((int)$person->id, $fieldsForPersonRole);

    /*********  MVEAS **************/
    // Filter the MVEAS Attributes and keep the field name
    $mveaAttributes = (new Collection($supportedAttributes))->filter(function($attr, $key) {
      return isset($attr['mveaModel']);
    })->toArray();
    $mveaAttributes = array_keys($mveaAttributes);

    // MVEAs for Person
    $this->handleMveaAttributes(
      $person,
      $role,
      'Person',
      $mveaAttributes,
      $attributes,
      $cxn
    );

    // MVEAs for Role
    $this->handleMveaAttributes(
      $person,
      $role,
      'PersonRole',
      $mveaAttributes,
      $attributes,
      $cxn
    );

    /****** PERSON ******/
    // Keep the person attributes
    $personAttributes = (new Collection($supportedAttributes))->filter(function($attr, $key) {
      return isset($attr['model']) && $attr['model'] == 'Person';
    })->toArray();
    $personAttributes = array_keys($personAttributes);

    // Get all the fields/values required to build the PersonRole
    $fieldsForPerson = $attributesCollection->filter(function($attr, $key) use ($personAttributes) {
      return in_array($attr['enrollment_attribute']['attribute'], $personAttributes);
    })->toArray();

    $People->saveAttributes($person->id, $fieldsForPerson);

    /****** GROUP ******/
    // Filter the MVEAS Attributes and keep the field name
    $groupAttributes = (new Collection($supportedAttributes))->filter(function($attr, $key) {
      return isset($attr['model']) && $attr['model'] == 'Group';
    })->toArray();
    $groupAttributes = array_keys($groupAttributes);

    // Get all the fields/values required to build the PrersonRole
    $fieldsForGroup = $attributesCollection->filter(function($attr, $key) use ($groupAttributes) {
      return in_array($attr['enrollment_attribute']['attribute'], $groupAttributes);
    })->toArray();

    $groupMemberObj = TableRegistry::getTableLocator()->get('GroupMembers');
    $groupMemberObj->saveAttributes($person->id, $fieldsForGroup);

    // Save the Date Of Birth. This is the only one that is single valued
    // and goes under the Person

    $cxn->commit();

    return true;
  }

  /**
   * Handle MVEA (Multi-Valued Enrollment Attributes) for a model.
   *
   * This method processes and saves Multi-Valued Enrollment Attributes associated
   * with a specific model (e.g., Person, PersonRole) for the given person and role.
   *
   * @param Person|null $person The person entity involved.
   * @param PersonRole|null $role The person role entity involved.
   * @param string $mveaParent
   * @param array $mveaAttributes
   * @param array $attributes Collection of petition attributes to filter and process.
   * @param Connection $cxn Database connection used for transactions.
   * @return void
   * @since  COmanage Registry v5.1.0
   */
  protected function handleMveaAttributes(
    Person|null $person,
    PersonRole|null $role,
    string $mveaParent,
    array $mveaAttributes,
    array $attributes,
    Connection $cxn
  ): void {
    $attributesCollection = new Collection($attributes);
    // MVEAs for the requested model
    $fieldsForMveaModel = $attributesCollection->filter(function($attr, $key) use ($mveaAttributes, $mveaParent) {
      return in_array($attr['enrollment_attribute']['attribute'], $mveaAttributes)
        && $attr['enrollment_attribute']['attribute_mvea_parent'] == $mveaParent;
    })->toArray();

    if(empty($fieldsForMveaModel)) {
      return;
    }

    $enrollmentAttributes = Hash::combine(
      $fieldsForMveaModel,
      '{n}.enrollment_attribute_id', '{n}.enrollment_attribute.attribute',
    );


    foreach ($enrollmentAttributes as $attribute_id => $attribute) {
      // Get all the fields for this enrollment_attribute_id
      $fieldsForAttribute = $attributesCollection->filter(function($attr, $key) use ($attribute_id) {
        return $attr['enrollment_attribute_id'] == $attribute_id;
      })->toArray();

      $supportedAttributes = $this->EnrollmentAttributes->supportedAttributes();
      $mveaModel = $supportedAttributes[$attribute]['mveaModel'];

      $modelObj = TableRegistry::getTableLocator()->get($mveaModel);
      $modelObj->saveAttributes((int)$person->id, $role?->id, $mveaParent, $fieldsForAttribute);
    }
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

  /**
   * Obtain the set of Email Addresses known to this plugin that are eligible for
   * verification.
   *
   * @since  COmanage Registry v5.1.0
   * @param  EntityInterface  $config       Configuration entity for this plugin
   * @param  int              $petitionId   Petition ID
   * @return array                          Array of Email Addrsses that are eligible for verification
   */

  public function verifiableEmailAddresses(
    EntityInterface $config,
    int $petitionId
  ): array {
    // First get the Enrollment Attributes for this petition
    $vv_enrollment_attributes = $this->EnrollmentAttributes->find('list',
    [
      'keyField' => 'id',
      'valueField' => 'attribute_type'
    ])->where([
        'attribute_collector_id' => $config->id,
        'attribute' => 'emailAddress',
        'status' => StatusEnum::Active,
      ])
      ->order(['ordr' => 'ASC'])
      ->toArray();

    if (empty($vv_enrollment_attributes)) {
      return [];
    }

    $set = $this->EnrollmentAttributes
      ->PetitionAttributes
      ->find()
      ->where(['petition_id' => $petitionId])
      ->where(fn(QueryExpression $exp, Query $q) => $exp->in('enrollment_attribute_id', array_keys($vv_enrollment_attributes)))
      ->toArray();

    $verifiableEmailAddressesArray = [];
    if (!empty($set)) {
      $emalAddresses = Hash::extract($set, '{n}.value');
      $verifiableEmailAddressesArray = array_fill_keys($emalAddresses, false);
    }

    return $verifiableEmailAddressesArray;
  }
}
