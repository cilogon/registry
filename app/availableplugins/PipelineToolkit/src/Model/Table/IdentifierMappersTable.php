<?php
/**
 * COmanage Registry Identifier Mappers Table
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

use Cake\ORM\Table;
use Cake\Validation\Validator;

class IdentifierMappersTable extends Table {
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
    $this->belongsTo('Flanges');

    $this->hasMany('PipelineToolkit.LoginIdentifierTypes')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setDisplayField('id');
    
    $this->setPrimaryLink(['flange_id']);
    $this->setRequiresCO(true);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false,
        'index' =>    ['platformAdmin', 'coAdmin']
      ],
      // Related models whose permissions we'll need, typically for table views
      'related' => [
        'table' => [
          'PipelineToolkit.LoginIdentifierTypes'
        ]
      ]
    ]);
  }

  /**
   * Apply mappings for vuildRelatedAttributes.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  Flange   $flange   Flange, including top level plugin configuration
   * @param  array    $newdata  Array of new Entity data
   * @param  string   $model    Model name (eg: "EmailAddresses")
   * @param  Entity   $entity   Original Entity data
   * @return array              Updated array of new Entity data
   */

  public function buildRelatedAttributes(
    \App\Model\Entity\Flange $flange,
    array $newdata,
    string $model,
    ?\Cake\ORM\Entity $entity=null
  ): array {
    // We only operate over Identifiers
    if($model != 'Identifiers') {
      return $newdata;
    }

    $retdata = $newdata;

    // Pull our type configuration
    $typeSettings = $this->LoginIdentifierTypes->find()
                         ->where(['identifier_mapper_id' => $flange->identifier_mapper->id])
                         ->all();

    // Note we don't (and can't) preserve login flags if the configuration is changed and
    // the Pipeline is rerun, but that's probably the correct behavior. Specifically, if
    // an Identifier is flagged as login, then the configuration is changed so that type
    // is no longer flagged as login, then the next time the Pipeline runs the Identifier
    // will be updated to be not login.

    foreach($typeSettings as $ts) {
      if($ts->type_id == $retdata['type_id'] && $ts->login) {
        $this->llog('trace', $flange->description . " setting Identifier " . $ts->identifier
                             . " to be flagged as login=true");
        $retdata['login'] = true;
      }
    }

    return $retdata;
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
    
    $validator->add('flange_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('flange_id');
    
    return $validator; 
  }
}