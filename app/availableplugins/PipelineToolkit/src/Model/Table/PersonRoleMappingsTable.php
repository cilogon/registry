<?php
/**
 * COmanage Registry Person Role Mappings Table
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

namespace PipelineToolkit\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use \App\Lib\Enum\ComparisonEnum;

class PersonRoleMappingsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
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
    $this->addBehavior('Orderable');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('PipelineToolkit.PersonRoleMappers');

    $this->belongsTo('AffiliationTypes')
         ->setClassName('Types')
         ->setForeignKey('affiliation_type_id')
         ->setProperty('affiliation_type');
    $this->belongsTo('TargetCous')
         ->setClassName('Cous')
         ->setForeignKey('target_cou_id')
         ->setProperty('target_cou');
    $this->belongsTo('TargetAffiliationTypes')
         ->setClassName('Types')
         ->setForeignKey('target_affiliation_type_id')
         ->setProperty('target_affiliation_type');
    
    $this->setDisplayField('description');
    
    $this->setPrimaryLink(['PipelineToolkit.person_role_mapper_id']);
    $this->setRequiresCO(true);
    $this->setRedirectGoal('index');
    
    $this->setAutoViewVars([
      'affiliationTypes' => [
        'type' => 'type',
        'attribute' => 'PersonRoles.affiliation_type'
      ],
      'attributes' => [
        'type' => 'array',
        'array' => $this->getMappableAttributes()
      ],
      'comparisons' => [
        'type' => 'enum',
        'class' => 'ComparisonEnum'
      ],
      'targetAffiliationTypes' => [
        'type' => 'type',
        'attribute' => 'PersonRoles.affiliation_type'
      ],
      'targetCous' => [
        'type' => 'select',
        'model' => 'Cous'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Obtain the set of mappable attributes, in Model.attribute format.
   * 
   * @since  COmanage Registry v5.0.0
   * @return array    Mappable attributes
   */

  public function getMappableAttributes(): array {
    return [
      'AdHocAttribute.value',
      'ExternalIdentityRole.affiliation',
      'ExternalIdentityRole.department',
      'ExternalIdentityRole.organization',
      'ExternalIdentityRole.title',
    ];
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
    
    $validator->add('person_role_mapper_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('person_role_mapper_id');

    $validator->add('attribute', [
      'content' => ['rule' => ['inList', $this->getMappableAttributes()]]
    ]);
    $validator->notEmptyString('attribute');

    $validator->add('affiliation_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('affiliation_type_id');

    // The required fields here aren't ideal, since they vary by attribute
    $this->registerStringValidation($validator, $schema, 'ad_hoc_tag', false);

    $validator->add('comparison', [
      'content' => ['rule' => ['inList', ComparisonEnum::getConstValues()]]
    ]);
    $validator->allowEmptyString('comparison');

    $this->registerStringValidation($validator, $schema, 'pattern', false);

    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');

    $validator->add('target_cou_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('target_cou_id');

    $validator->add('target_affiliation_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('target_affiliation_type_id');
    
    return $validator; 
  }
}