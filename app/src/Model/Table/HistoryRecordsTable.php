<?php
/**
 * COmanage Registry History Records Table
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

use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Event\EventInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

class HistoryRecordsTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  use \App\Lib\Traits\SearchFilterTrait;
  use \App\Lib\Traits\AutoViewVarsTrait;

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
    $this->belongsTo('ApiUser')
         ->setForeignKey('actor_api_user_id')
         ->setProperty('actor_api_user');
    $this->belongsTo('ActorPeople')
         ->setClassName('People')
         ->setForeignKey('actor_person_id')
         // Property is set so ruleValidateCO can find it. We don't use the
         // _id suffix to match Cake's default pattern.
         ->setProperty('actor_person');
    $this->belongsTo('People');
    $this->belongsTo('PersonRoles');
    $this->belongsTo('ExternalIdentities');
    $this->belongsTo('ExternalIdentityRoles');
    $this->belongsTo('Groups');
    
    $this->setDisplayField('comment');
    
// XXX note primary link is external_identity_id when set...
// or the other fields as we add them
    // When adding a new Primary Link here, add a check for it in beforeMarshal
    // in order to find the current CO for recording History Records
    $this->setPrimaryLink(['external_identity_id', 'group_id', 'person_id']);
    $this->setRequiresCO(true);
    
// XXX does some of this stuff really belong in the controller?
    // Cake appears to incorrectly use the ActorPeople foreign key definition
    // even though the relation to PrimaryName is for People. There's probably
    // a patch that needs to be made, but for now we'll just force the foreign
    // key back.
    $this->setEditContains(['ActorPeople' => ['PrimaryName' => ['foreignKey' => 'person_id']]]);
    $this->setIndexContains(['ActorPeople' => ['PrimaryName' => ['foreignKey' => 'person_id']]]);
    $this->setViewContains([
      'People' => ['PrimaryName'],
      // contain results in a join when the relation is belongsTo (or hasOne),
      // and joining the same table twice makes the database unhappy, so we
      // force ActorPeople to use multiple queries.
      'ActorPeople' => ['Names' => ['queryBuilder' => function ($q) {
        return $q->where(['primary_name' => true]);
      }]],
      'ExternalIdentities' => ['Names'],
      'Groups'
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false,
        'edit' =>     false,
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
   * Perform actions while marshaling data, before validation.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event    Event
   * @param  ArrayObject    $data     Object data, in array format
   * @param  ArrayObject    $options  Entity save options
   */

  public function beforeMarshal(EventInterface $event, \ArrayObject $data, \ArrayObject $options) {
    // $options['actor'] is set by ActorEventListener
// XXX support api_user_id?
    if(empty($data['actor_person_id']) && !empty($options['actor'])) {
      // Try to map the actor username to a Person ID. For that we need the current CO.
      $coId = null;

      if(!empty($data['person_id'])) {
        $coId = $this->People->findCoForRecord($data['person_id']);
      } elseif(!empty($data['group_id'])) {
        $coId = $this->Groups->findCoForRecord($data['group_id']);
      }

      if($coId) {
        $Identifiers = TableRegistry::getTableLocator()->get('Identifiers');

        try {
          $data['actor_person_id'] = $Identifiers->lookupPersonByLogin($coId, $options['actor']);
        }
        catch(RecordNotFoundException $e) {
          // This would typically indicate an unenrolled user or someone going through an
          // enrollment flow, but we shouldn't be recording History Records in either of
          // those scenarios...
        }
      }
    }

    if(!empty($data['comment'])) {
      // Truncate the comment to fit the column width
      $column = $this->getSchema()->getColumn('comment');

      $data['comment'] = substr($data['comment'], 0, $column['length']);
    }
  }

  /**
   * Table specific logic to generate a display field.
   *
   * @since  COmanage Registry v5.0.0
   * @param  HistoryRecord $entity Entity to generate display field for
   * @return string                Display field
   */

  public function generateDisplayField(\App\Model\Entity\HistoryRecord $entity): string {
    // Comments may be too long to render, so we just use the model name
    // (which will get appended with the record ID)

    return __d('controller', 'HistoryRecords', [1]);
  }
  
  /**
   * Record a History Record entry for a Group.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $groupId                Group ID
   * @param  string $action                 Action
   * @param  string $comment                Comment
   * @param  int    $personId               Person ID
   * @return int                            History Record ID
   */
  
  public function recordForGroup(int $groupId, 
                                 string $action,
                                 string $comment,
                                 ?int $personId=null): int {
    $record = [
      'group_id'  => $groupId,
      'action'    => $action,
      'comment'   => $comment
    ];
    
    if($personId) {
      $record['person_id'] = $personId;
    }
    
    $obj = $this->newEntity($record);
    
    $this->saveOrFail($obj);
    
    return $obj->id;
  }
  
  /**
   * Record a History Record entry for a Person.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $personId               Person ID
   * @param  string $action                 Action
   * @param  string $comment                Comment
   * @param  int    $personRoleId           Person Role ID
   * @param  int    $externalIdentityId     External Identity ID
   * @param  int    $externalIdentityRoleId External Identity Role ID
   * @param  int    $actorPersonId          Actor Person ID if known (will be autocalculated otherwise)
   * @return int                            History Record ID
   */
  
  public function recordForPerson(?int $personId, 
                                  string $action,
                                  string $comment,
                                  ?int $personRoleId=null,
                                  ?int $externalIdentityId=null,
                                  ?int $externalIdentityRoleId=null,
                                  ?int $actorPersonId=null): int {
    $record = [
      'person_id'                 => $personId,
      'action'                    => $action,
      'comment'                   => $comment,
      'person_role_id'            => $personRoleId,
      'external_identity_id'      => $externalIdentityId,
      'external_identity_role_id' => $externalIdentityRoleId,
      'actor_person_id'           => $actorPersonId
    ];
    
    $obj = $this->newEntity($record);
    
    $this->saveOrFail($obj);
    
    return $obj->id;
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
    
    $this->registerStringValidation($validator, $schema, 'action', true);

    // We disable validateInput for the comment field since changesToString likes to
    // include > characters.
// XXX should we maybe filter on input for manual history records? or maybe it's just
//     ok since we filter on output anyway...
    $this->registerStringValidation($validator, $schema, 'comment', required: true, validateInput: false);
    
    return $validator; 
  }
}