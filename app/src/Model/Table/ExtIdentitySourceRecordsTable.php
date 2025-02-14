<?php
/**
 * COmanage Registry External Identity Source Records Table
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

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;

// This should be ExternalIdentitySourceRecordsTable but then alias.field assembly
// exceeds Cake's 61 character limit
class ExtIdentitySourceRecordsTable extends Table {
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
  use \App\Lib\Traits\TabTrait;

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
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);
    
    // Define associations
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('ExternalIdentitySources');
    
    $this->setDisplayField('source_key');
    
    $this->setPrimaryLink(['external_identity_source_id', 'external_identity_id']);
    $this->setRequiresCO(true);

    // These are required for the link to work from the Artifacts page
    $this->setAllowUnkeyedPrimaryCO(['index']);
    $this->setAllowEmptyPrimaryLink(['index']);

    $this->setIndexContains([
      'ExternalIdentitySources'
    ]);
    
    $this->setViewContains([
      'ExternalIdentitySources',
      'ExternalIdentities'  => ['Names', 'People' => ['PrimaryName']],
    ]);
/*
// XXX This doesn't seem to correlate to what actually renders?
    $this->setFilterConfig([
      'external_identity_source_id' => [
        'type' => 'field',
        'active' => true,
        'order' => 1
      ],
      'source_key' => [
        'type' => 'field',
        'active' => true,
        'order' => 2
      ]
    ]);*/

    $this->setTabsConfig(
      [
        // Ordered list of Tabs
        'tabs' => ['People', 'PersonRoles', 'ExternalIdentities'],
        // What actions will include the subnavigation header
        'action' => [
          // If a model renders in a subnavigation mode in edit/view mode, it cannot
          // render in index mode for the same use case/context
          // XXX edit should go first.
          'People' => ['edit', 'view'],
          'PersonRoles' => ['index'],
          'ExternalIdentities' => ['index'],
        ],
        // What model will have a counter-badge after the tab title
        'counter' => ['PersonRoles', 'ExternalIdentities'],
        'nested' => [
          // Ordered list of Tabs
          'tabs' => ['ExternalIdentities', 'ExternalIdentityRoles'],
          // What actions will include the subnavigation header
          'action' => [
            // If a model renders in a subnavigation mode in edit/view mode, it cannot
            // render in index mode for the same use case/context
            // XXX edit should go first.
            'ExternalIdentities' => ['edit', 'view'],
            'ExternalIdentityRoles' => ['index'],
          ],
          // What model will have a counter-badge after the tab title
          'counter' => ['ExternalIdentityRoles'],
        ]
      ]
    );

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
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('external_identity_source_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('external_identity_source_id');
    
    $this->registerStringValidation($validator, $schema, 'source_key', true);
    
// Since source_record comes from upstream, it's not clear that we should
// enforce any validation on it
//    $this->registerStringValidation($validator, $schema, 'source_record', false);
    
    $validator->add('last_updane', [
      'content' => ['rule' => 'dateTime']
    ]);
    $validator->allowEmptyString('last_update');

    $validator->add('external_identity_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('external_identity_id');

    $this->registerStringValidation($validator, $schema, 'reference_identifier', false);
    
    return $validator; 
  }
}