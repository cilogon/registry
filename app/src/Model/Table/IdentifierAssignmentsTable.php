<?php
/**
 * COmanage Registry IdentifierAssignments Table
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
use Cake\Validation\Validator;
use App\Lib\Enum\ActionEnum;
use App\Lib\Enum\IdentifierAssignmentContextEnum;
use App\Lib\Enum\ProvisioningContextEnum;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Util\StringUtilities;

class IdentifierAssignmentsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PluggableModelTrait;
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
    $this->belongsTo('Cos');
    $this->belongsTo('Groups');
    $this->belongsTo('EmailAddressTypes')
         ->setClassName('Types')
         ->setForeignKey('email_address_type_id')
         ->setProperty('email_address_type');
    $this->belongsTo('IdentifierTypes')
         ->setClassName('Types')
         ->setForeignKey('identifier_type_id')
         ->setProperty('identifier_type');

    $this->setPluginRelations();
    
    $this->setDisplayField('description');
    
    $this->setPrimaryLink(['co_id', 'group_id', 'person_id']);
    $this->setRequiresCO(true);
    $this->setAllowUnkeyedPrimaryLink(['assign']);
    $this->setRedirectGoal(action: 'assign', goal: 'primaryLink');

    $this->setAutoViewVars([
      'contexts' => [
        'type'  => 'enum',
        'class' => 'IdentifierAssignmentContextEnum'
      ],
      'emailAddressTypes' => [
        'type'      => 'type',
        'attribute' => ['EmailAddresses.type']
      ],
      'groups' => [
        'type'  => 'select',
        'model' => 'Groups'
      ],
      'identifierTypes' => [
        'type'      => 'type',
        'attribute' => ['Identifiers.type']
      ],
      'plugins' => [
        'type'        => 'plugin',
        'pluginType'  => 'assigner'
      ],
      'statuses' => [
        'type'  => 'enum',
        'class' => 'SuspendableStatusEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'configure' =>  ['platformAdmin', 'coAdmin'],
        'delete' =>     ['platformAdmin', 'coAdmin'],
        'edit' =>       ['platformAdmin', 'coAdmin'],
        'view' =>       ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'assign' =>   ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Assign Identifiers for an Entity.
   * 
   * @since  COmanage Registry v5.0.0
* XXX Document params
   */

  public function assign(
    string $entityType,
    int    $entityId,
    bool   $provision=true,
// XXX CFM-76 HistoryRecords don't seem to do anything with actorPersonId yet
//     Also need to update StandardController or something for regular requests
    int    $actorPersonId=null
  ): array {
    $ret = [
      'already'   => [],
      'assigned'  => [],
      'errors'    => []
    ];

    // Pull the entity, which we'll use to map to the CO and also to pass to
    // other functions.

    $contains = ['Identifiers' => 'Types'];

    if($entityType == 'People') { 
      $contains[] = 'PrimaryName'; 
    }

    $EntityTable = TableRegistry::getTableLocator()->get($entityType);

    // Map the entity to its CO, which we'll need to pull the
    // Identifier Assignment configuration.

    $coId = $EntityTable->findCoForRecord($entityId);

    $context = ($entityType == 'Groups' 
                ? IdentifierAssignmentContextEnum::Group
                : IdentifierAssignmentContextEnum::Person);

    $ias = $this->find()
                ->where([
                  'IdentifierAssignments.co_id' => $coId,
                  'IdentifierAssignments.status' => SuspendableStatusEnum::Active,
                  'IdentifierAssignments.context' => $context
                ])
                ->order(['IdentifierAssignments.ordr' => 'ASC'])
                ->contain($this->getPluginRelations())
                ->all();
    
    foreach($ias as $ia) {
// XXX CFM-57 If not group eligible skip this (but log that we skipped it)
      // We'll create a transaction for each Identifier Assignment

      $cxn = $this->getConnection();
      $cxn->begin();

      // We pull the entity at the start of each loop to reload any
      // identifiers that were generated on the previous loop and therefore
      // might be used in a subsequent assignment. (It might be slightly
      // more efficient to manually track the generated identifier, but
      // this should be less brittle if we add support for another model
      // alongside Identifiers and EmailAddresses.)

      $entity = $EntityTable->get($entityId, ['contain' => $contains]);

      // Check if there is already an identifier of this type

      if(!$this->assigned($ia, $entity)) {
        // Request a new Identifier

        try {
          $Plugin = TableRegistry::getTableLocator()->get($ia->plugin);

          // The plugin is expected to throw InvalidArgumentException on
          // unsupported context, or RuntimeException on some other error.
          $ret['assigned'][$ia->description] = $Plugin->assign($ia, $entity);
          
          $this->llog('trace', "New Identifier '".$ia->description."' assigned (".$ret['assigned'][$ia->description].") for $entityType $entityId");

          $this->attachIdentifier($ia, $entity, $ret['assigned'][$ia->description]);
        }
        catch(\Exception $e) {
          $this->llog('debug', "Identifier '".$ia->description."' assignment failed for $entityType $entityId: " . $e->getMessage());
          $ret['errors'][$ia->description] = $e->getMessage();
          $cxn->rollback();
        }
      } else {
        $this->llog('trace', "Identifier '".$ia->description."' already assigned for $entityType $entityId");
        $ret['already'][$ia->description] = true; // XXX maybe return the identifier?
        // We can't rollback here because it will cause parent transactions
        // (eg: Pipelines) to fail
//        $cxn->rollback();
      }

      $cxn->commit();
    }

    // Trigger provisioning, letting errors bubble up (AR-GMR-5)
    if(method_exists($EntityTable, "requestProvisioning")) {
      $this->llog('rule', "AR-GMR-5 Requesting provisioning for $entityType " . $entity->id);
      $EntityTable->requestProvisioning(id: $entity->id, context: ProvisioningContextEnum::Automatic);
    }

    return $ret;
  }

  /**
   * Determine if an identifier of a given type is already assigned to an entity.
   * Suspended identifiers are considered assigned.
   *
   * IMPORTANT: This function should be called within a transaction to ensure
   * actions taken based on availability are atomic.
   *
   * @since  COmanage Registry v5.0.0
   * @param  IdentifierAssignment $ia     Identifier Assignment
   * @param  EntityInterface      $entity Entity
   * @return bool                         True if an identifier of the specified type is already assigned, false otherwise
   */

  public function assigned($ia, $entity): bool {
    $fk = StringUtilities::entityToForeignKey($entity);

    $className = !empty($ia->email_address_type_id)
                 ? 'EmailAddresses'
                 : 'Identifiers';
    
    $typeId = !empty($ia->email_address_type_id)
              ? $ia->email_address_type_id
              : $ia->identifier_type_id;
    $EntityTable = TableRegistry::getTableLocator()->get($className);

    $count = $EntityTable->find()
                         ->where([
                            $className . '.' . $fk   => $entity->id,
                            $className . '.type_id'  => $typeId
                         ])
                         ->epilog('FOR UPDATE')
// We can't use aggregate functions with FOR UPDATE
//                          ->count()
                         ->all();

    return (bool)($count->count());
  }
  
  /**
   * Attach a newly generated Identifier (or Email Address) to the entity
   * for which it was generated.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  IdentifierAssignment $ia         Identifier Assignment
   * @param  EntityInterface      $entity     Subject Entity
   * @param  string               $identifier Identifier (or Email Address)
   * @return int                              Newly created entity ID
   */

  public function attachIdentifier($ia, $entity, string $identifier): int {
    // eg: person_id, group_id
    $fk = StringUtilities::entityToForeignKey($entity);
    $entityClassName = StringUtilities::entityToClassName($entity);

    // Are we attaching an Identifier or an Email Address?
    $targetClassName = !empty($ia->email_address_type_id)
                       ? 'EmailAddresses'
                       : 'Identifiers';

    $TargetEntityTable = TableRegistry::getTableLocator()->get($targetClassName);

    $newRecord = [];

    if($targetClassName == 'EmailAddresses') {
      $newRecord = [
        'mail' => $identifier,
        'type_id' => $ia->email_address_type_id,
        // AR-IdentifierAssignment-3 EmailAddresses generated via Identifier Assignment are considered verified
        'verified' => true
      ];
    } else {
      $newRecord = [
        'identifier' => $identifier,
        'type_id' => $ia->identifier_type_id,
        'status' => SuspendableStatusEnum::Active,
        'login' => $ia->login
      ];
    }

    // Add in the foreign key
    $newRecord[$fk] = $entity->id;

    $newEntity = $TargetEntityTable->newEntity($newRecord);

    $TargetEntityTable->saveOrFail($newEntity);

    $Types = TableRegistry::getTableLocator()->get('Types');

    $typeLabel = $Types->getTypeLabel((int)$newRecord['type_id']);

    // We can actually pass either $newEntity or $entity to recordHistory()
    // with basically the same effect, but passing $entity will result in
    // fewer lookups to get the foreign keys required to record history.
    $TargetEntityTable->$entityClassName->recordHistory(
      entity: $entity,
      action: ActionEnum::IdentifierAutoAssigned,
      comment: __d('result', "IdentifierAssignments.history", [$identifier, $typeLabel, $ia->description])
    );

    return $newEntity->id;
  }

  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-IdentifierAssignment-1 An IdentifierAssignment must apply to either an
    // Identifier or an EmailAddress, but not both.

    $rules->add([$this, 'ruleWhichType'],
                'targetType',
                ['errorField' => 'identifier_type_id']);

    return $rules;
  }

  /**
   * Check if an identifier or email address is available for use, ie
   * if it is not defined (regardless of status) within the same CO.
   *
   * IMPORTANT: This function should be called within a transaction to ensure
   * actions taken based on availability are atomic.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string           $className  Class name ("Identifiers" or "EmailAddresses")
   * @param  int              $typeId     Type ID
   * @param  string           $candidate  Candidate identifier or email address
   * @param  EntityInterface  $entity     Entity to check availability for
   * @return bool                         True if the candidate is already in use
   * @throws OverflowException If $candidate is already in use
   */

  public function checkAvailability(
    string $className,
    int $typeId,
    string $candidate,
    $entity
  ): bool {
    $fieldName = ($className == 'EmailAddresses' ? 'mail' : 'identifier');
    $foreignKey = StringUtilities::entityToForeignKey($entity);

    $Table = TableRegistry::getTableLocator()->get($className);

    // In order to allow ensure that another process doesn't perform the same
    // availability check while we're running, we need to lock the appropriate
    // tables/rows at read time. We do this with FOR UPDATE.

    $r = $Table->find()
               ->where([
                 // AR-Identifier-Assignment-2 Availability checks for newly
                 // generated Identifiers and EmailAddresses are case insensitive.
// XXX CFM-306 This really requires a case insensitive index, but DBAL doesn't support
//     those because it's a "database specific" thing. It might be possible to do this
//     by overriding the schema manager...
// https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/schema-manager.html#overriding-the-schema-manager
                 'LOWER('.$className.'.'.$fieldName.')' => strtolower($candidate),
                 // Because type_ids are specific to a CO, we effectively
                 // constrain the search within a CO by typeId
                 $className.'.type_id' => $typeId,
                 // Only consider records where the foreign key of the same type
                 // is not null. (eg: We don't consider an Identifier assigned to
                 // a Group to be taken if we're assigning for a Person.)
                 $className.'.'.$foreignKey.' IS NOT NULL',
               ])
               ->epilog('FOR UPDATE')
// We can't use aggregate functions with FOR UPDATE
//                  ->count()
               ->all();
    
    if($r->count() > 0) {
      throw new \OverflowException(__d('error', 'IdentifierAssignments.exists', $candidate));
    }

// XXX CFM-309: Once Identifier Validators are a thing call them here
//     (see v4 AppModel::checkAvailability)
    
    return true;
  }

  /**
   * Application Rule to determine if an appropriate target type is selected.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleWhichType($entity, $options) {
    if(!$entity->email_address_type_id
       && !$entity->identifier_type_id) {
      // No type was set
      return(__d('error', 'IdentifierAssignments.type'));
    } elseif($entity->email_address_type_id
       && $entity->identifier_type_id) {
      // Both types were set
      return(__d('error', 'IdentifierAssignments.type'));
    }

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
    
    $validator->add('co_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('co_id');
    
    $this->registerStringValidation($validator, $schema, 'description', false);
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'plugin', false);
    
    $validator->add('context', [
      'content' => ['rule' => ['inList', IdentifierAssignmentContextEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('context');

    $validator->add('group_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('group_id');

    $validator->add('identifier_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    // See AR-IdentifierAssignment-1
    $validator->allowEmptyString('identifier_type_id');

    $validator->add('login', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('login');

    $validator->add('email_address_type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    // See AR-IdentifierAssignment-1
    $validator->allowEmptyString('email_address_type_id');

    $validator->add('ordr', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('ordr');

    return $validator; 
  }
}