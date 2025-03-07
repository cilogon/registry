<?php
/**
 * COmanage Registry Login Identifier Types Table
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace PipelineToolkit\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class LoginIdentifierTypesTable extends Table {
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
   * @since  COmanage Registry v5.1.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('PipelineToolkit.IdentifierMappers');
    $this->belongsTo('Types');
    
    $this->setDisplayField('id');
    
    $this->setPrimaryLink(['PipelineToolkit.identifier_mapper_id']);
    $this->setRequiresCO(true);
    $this->setRedirectGoal('index');
    
    $this->setAutoViewVars([
      'types' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
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
   * Define business rules.
   *
   * @since  COmanage Registry v5.1.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */

  public function buildRules(RulesChecker $rules): RulesChecker {
    // We only allow an Identifier Type to be configured once per Mapper.
    // This isn't strictly speaking an Application Rule, it is mostly intended
    // to avoid inconsistent configurations.
    $rules->add([$this, 'ruleUniqueType'],
                'uniqueType',
                ['errorField' => 'type_id']);

    return $rules;
  }

  /**
   * Application Rule to determine if a specific Type is already configured.
   *
   * @since  COmanage Registry v5.1.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */

  public function ruleUniqueType($entity, $options) {
    // debug($entity);

    if($entity->isNew() || $entity->isDirty('type_id')) {
      $whereClause = [
        'LoginIdentifierTypes.type_id' => $entity->type_id,
        'identifier_mapper_id' => $entity->identifier_mapper_id
      ];

      if(!$entity->isNew()) {
        $whereClause['id IS NOT'] = $entity->id;
      }

      $existing = $this->find()->where($whereClause)->contain(['Types'])->first();
      
      if(!empty($existing)) {
        throw new \InvalidArgumentException(__d('pipeline_toolkit', "error.LoginIdentifierType.already", [$existing->type->display_name, $existing->id]));
      }
    }
    
    return true;
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('identifier_mapper_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('identifier_mapper_id');

    $validator->add('type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('type_id');

    $validator->add('login', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('login');
    
    return $validator; 
  }
}