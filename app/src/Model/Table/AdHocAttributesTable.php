<?php
/**
 * COmanage Registry Ad Hoc Attributes Table
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
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class AdHocAttributesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionableTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);
    
    // Define associations
    $this->belongsTo('People');
    $this->belongsTo('PersonRoles');
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('ExternalIdentityRoles');
    $this->belongsTo('SourceAdHocAttributes')
         ->setClassName('AdHocAttributes')
         ->setForeignKey('source_ad_hoc_attribute_id')
         ->setProperty('source_ad_hoc_attribute');
    $this->hasMany('PipelinedAdHocAttributes')
         ->setClassName('AdHocAttributes')
         ->setForeignKey('source_ad_hoc_attribute_id')
         ->setProperty('pipelined_ad_hoc_attribute');
    
    $this->setDisplayField('tag');
    
    $this->setPrimaryLink(['external_identity_id', 'external_identity_role_id', 'person_id', 'person_role_id']);
    $this->setRequiresCO(true);
    $this->setRedirectGoal('self');
    $this->setRedirectGoal(action: 'delete', goal: 'deleted');
    $this->setAllowLookupPrimaryLink(['unfreeze']);
    $this->setEditContains(['ExternalIdentities', 'ExternalIdentityRoles', 'SourceAdHocAttributes']);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'unfreeze' => ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin', 'selfMember']
      ],
      // Actions that are permitted on readonly entities (besides view)
      'readOnly' =>   ['unfreeze'],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin', 'selfMember'],
        'deleted' =>  ['platformAdmin', 'coAdmin']
      ]
    ]);
  }
  
  /**
   * Callback after model save.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface  $event   Event
   * @param  EntityInterface $entity  Entity (ie: Co)
   * @param  ArrayObject     $options Save options
   * @return bool                     True on success
   */
    
  public function localAfterSave(\Cake\Event\EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $options): bool {
    $this->recordHistory($entity);
    
    return true;
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $this->registerPrimaryKeyValidation($validator, $this->getPrimaryLinks());
    
    $this->registerStringValidation($validator, $schema, 'tag', true);
    
    $this->registerStringValidation($validator, $schema, 'value', false);
    
    $validator->add('frozen', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('frozen');

    $validator->add('source_ad_hoc_attribute_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('source_ad_hoc_attribute_id');
    
    return $validator; 
  }


  /**
   * Save ad hoc attributes for a person or person role.
   *
   * @since  COmanage Registry v5.1.0
   * @param int $personId ID of the person the attributes belong to
   * @param int|null $roleId ID of the person role the attributes belong to (or null if not applicable)
   * @param string $parentModel Name of the parent model ('Person' or 'PersonRole')
   * @param array $fields Array of fields containing the attributes to be saved
   * @return bool                  True on success
   * @throws \InvalidArgumentException Thrown if required parameters are missing or invalid
   * @throws \Cake\ORM\Exception\PersistenceFailedException If saving the entity fails
   */
  public function saveAttributeCollectorPetitionAttributes(int $personId, ?int $roleId, string $parentModel, array $fields): bool
  {
    foreach ($fields as $idx => $field) {
      // Check if this has already been saved
      $adhoc = [
        'tag'          => $field->enrollment_attribute->attribute_tag,
        'value'       => $field->value
      ];

      if($parentModel === 'Person') {
        if(empty($personId)) {
          throw new \InvalidArgumentException(__d('error', 'personId'));
        }
        $adhoc['person_id'] = $personId;
      } elseif ($parentModel === 'PersonRole') {
        if(empty($roleId)) {
          throw new \InvalidArgumentException(__d('error', 'person_role_id'));
        }
        $adhoc['person_role_id'] = $roleId;
      }

      $this->saveOrFail($this->newEntity($adhoc));
    }
    return true;
  }
}