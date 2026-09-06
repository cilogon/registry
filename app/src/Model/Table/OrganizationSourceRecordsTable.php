<?php
/**
 * COmanage Registry Organization Source Records Table
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;

class OrganizationSourceRecordsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\LayoutTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.3.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);
    
    // Define associations
    $this->belongsTo('Organizations');
    $this->belongsTo('OrganizationSources');
    $this->setDisplayField('source_key');
    
    $this->setPrimaryLink(['organization_source_id', 'organization_id']);
    $this->setRequiresCO(true);

    // These are required for the link to work from the Artifacts page
    $this->setAllowUnkeyedPrimaryCO(['index']);
    $this->setAllowEmptyPrimaryLink(['index']);

    $this->setIndexContains([
      'OrganizationSources'
    ]);
    
    $this->setViewContains([
      'Organizations',
      'OrganizationSources'
    ]);

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
      ]
    ]);
  }

  /**
   * Modify an index Query to specify how to filter on the requested CO.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  Query  $query  Query object
   * @param  int    $coId   CO ID to filter on
   * @return Query          Modified query
   */

  public function filterIndexByCO(Query $query, int $coId): Query {
    return $query->where(['OrganizationSources.co_id' => $coId]);
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('organization_source_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('organization_source_id');

    $this->registerStringValidation($validator, $schema, 'source_key', true);
    
// Since source_record comes from upstream, it's not clear that we should
// enforce any validation on it.
//    $this->registerStringValidation($validator, $schema, 'source_record', false);
    
    $validator->add('last_update', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('last_update');

    $validator->add('organization_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('organization_id');

    return $validator; 
  }
}