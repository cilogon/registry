<?php
/**
 * COmanage Registry Cos Table
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

use Cake\Datasource\EntityInterface;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Validation\Validator;

use \App\Lib\Enum\StatusEnum;
use \App\Lib\Enum\SuspendableStatusEnum;
use \App\Lib\Enum\TemplateableStatusEnum;
use \App\Lib\Util\PaginatedSqlIterator;

class CosTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Timestamp');
 
    // COs are configuration
    $this->setIsConfigurationTable(true);
    
    // Define associations
    
    $this->hasMany('ApiUsers')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Cous')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Dashboards')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Groups')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('People')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Reports')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    $this->hasMany('Types')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->hasOne('CoSettings')
         ->setDependent(true)
         ->setCascadeCallbacks(true);
    
    $this->setDisplayField('name');
    
    $this->setAutoViewVars([
      'statuses' => [
        'type' => 'enum',
        'class' => 'TemplateableStatusEnum'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>    ['platformAdmin'],
        'duplicate' => ['platformAdmin'],
        'edit' =>      ['platformAdmin'],
        'switch' =>    ['platformAdmin'],
        'view' =>      ['platformAdmin']
      ],
      // Actions that are permitted on readonly entities (besides view)
      'readOnly' =>    ['duplicate'],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>       ['platformAdmin'],
        'index' =>     ['platformAdmin'],
        'select' =>    ['authenticatedUser']
      ],
      // Related models whose permissions we'll need, typically for table views
      'related' => [
        'Dashboards'
      ]
    ]);
  }
  
  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-CO-2 The COmanage CO cannot be renamed or deleted
    // Note add() sets the rule for create+update, where addDelete() sets the rule additionally for delete
    $rules->add([$this, 'ruleIsCOmanageCO'],
                'isCOmanageCO',
                ['errorField' => 'name']);
    
    $rules->addDelete([$this, 'ruleIsCOmanageCO'],
                      'isCOmanageCO',
                      ['errorField' => 'name']);
                
    // AR-CO-3 Two COs cannot share the same name
// XXX CO-1736 In general, these checks should be case insensitive
// (ie: I shouldn't be able to create a CO called "comanage", similarly COUs etc)
// Also, with CO-1845 maybe unique ignores non-alphanumeric
    $rules->add($rules->isUnique(['name'], __d('error', 'exists', [__d('controller', 'Cos', [1])])));
    
    // AR-CO-5 A CO cannot be deleted if it is in Active status
    // This basically requires two steps to delete a CO (set to Suspended),
    // reducing the likelihood of accidentally deleting a CO.
    $rules->addDelete([$this, 'ruleIsActive'],
                      'isActive',
                      ['errorField' => 'status']);
    
    return $rules;
  }
  
  /**
   * Delete a CO.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  EntityInterface  $entity   CO to be deleted
   * @param  array            $options  Delete options (as per Cake)
   * @return boolean                    true on success
   * @throws Cake\ORM\Exception\PersistenceFailedException
   */

  public function deleteOrFail(EntityInterface $entity, $options = []): bool {
    // Completely wiping a CO requires special handling. Because of the complex
    // dependency paths, we can't simply rely on Cake's dependency propagation
    // on delete.

    // We ignore $options['useHardDelete'] because COs can _only_ be hard deleted.

    // We'll start by obtaining the set of models directly associated with the CO model.
    $associations = $this->associations();

    // We need to sort the associations into several buckets:
    //  (1) Pluggable Models,
    //  (2) Configuration Models that belong to a model other than Cos,
    //  (3) Primary Models,
    //  (4) Configuration Models that do not belong to another model
    // We don't need to identify Secondary Models because Primary Model deletes 
    // will cascade to them, and generally Cake's cascade delete will be sufficient.
    // See also: https://spaces.at.internet2.edu/display/COmanage/Registry+PE+Data+Model#RegistryPEDataModel-Tables

    // These will be keyed on the association target class name, with values being the Table objects
    $pluggable = [];
    $configFirst = [];
    $primary = [];
    $configLast = [];

    foreach($associations->getByType(['HasOne', 'HasMany']) as $a) {
      $targetTable = $a->getTarget();

      if(method_exists($targetTable, "getPluggableModelType")) {
        $pluggable[ $a->getClassName() ] = $targetTable;
      } elseif($targetTable->getIsConfigurationTable()) {
        // eg: CoSettings
        $targetAssociations = $a->associations();

        // Did we find an association to something other than Cos?
        $found = false;

        foreach($targetAssociations->getByType(['belongsTo', 'belongsToMany']) as $ta) {
          // eg: Types (CoSettings belongsTo Types)
          // We also skip associations into the same model (eg: Cous, for TreeBehavior)

          if($ta->getClassName() != 'Cos' 
             && $ta->getClassName() != $a->getClassName()) {
            $found = true;
            break;
          }
        }

        if($found) {
          $configFirst[ $a->getClassName() ] = $targetTable;
        } else {
          $configLast[ $a->getClassName() ] = $targetTable;
        }
      } else {
        // This is by definition a Primary Object since it belongsTo CO, eg: People
        $primary[ $a->getClassName() ] = $targetTable;
      }
    }

    // First, delete plugin related models
    // XXX unclear that we need to do anything here... PluggableModelTrait will
    // automatically bind instantiated Entry Point Models when a Pluggable Table object
    // is initialized, so plugin related models should be automatically deleted when
    // the Pluggable Model is deleted.

    // Delete any Configuration Object that references a Primary Object or other
    // Configuration Objects (such as Types)

    $this->paginatedDelete($entity->id, $configFirst);

    // Delete Primary Objects, which should cascade and take Secondary Objects with them.

    $this->paginatedDelete($entity->id, $primary);

    // Delete any remaining Configuration Objects, (including Types) after all
    // models that might reference them

    $this->paginatedDelete($entity->id, $configLast);

    // Delete any Changelog records for this CO. We can use deleteAll because we
    // don't need any callbacks to fire.
    $this->deleteAll(['Cos.co_id' => $entity->id]);

    // Finally, delete the CO itself
    parent::deleteOrFail($entity, ['useHardDelete' => true, 'checkRules' => false]);

    return true;
  }

  /*
  public function duplicate($id) {
    // XXX document AR-CO-4, use TableMetaTrait to determine which tables are configuration
  }*/
  
  /**
   * Find the COmanage CO.
   *
   * @since  COmanage Registry v5.0.0
   * @param  \Cake\ORM\Query $query Query
   * @return \Cake\ORM\Query        Query
   */
  
  public function findCOmanageCO(Query $query): Query {
    return $query->where(['lower(name)' => 'comanage']);
  }

  /**
   * Obtain the set of COs for the specified Identifier. The Identifier must
   * be a login identifier, Active, and attached to an Active or Grace Period
   * Person in an Active CO. If the Identifier belongs to a Platform Admin, all
   * Active COs will be returned.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $loginIdentifier Login Identifier
   * @return array                   Array of COs
   */
  
  public function getCosForIdentifier(string $loginIdentifier): array {
    // Start by pulling the active Identifier records where $loginIdentifier is
    // flagged for login and attached to a Person (not an External Identity).
    
    $identifiers = $this->People
                        ->Identifiers
                        ->find('all')
                        ->where([
                          'Identifiers.identifier' => $loginIdentifier,
                          'Identifiers.status'     => SuspendableStatusEnum::Active,
                          'Identifiers.login'      => true,
                          'Identifiers.person_id IS NOT NULL'
                        ])
                        ->contain(['People' => 'Cos'])
                        ->all();
    
    $cos = [];
    
    // Did we find an Identifier attached to a Person in the COmanage CO?
    
    foreach($identifiers as $i) {
      // Both the Person and the CO must be active. Note that there may be an
      // Active Identifier pointing to an Archived Person (for certain edge cases),
      // in which case $i->person is null even though person_id is not.
      
      if($i->person && $i->person->isActive() 
         && $i->person->co->status == TemplateableStatusEnum::Active) {
        // Keying on co_id should eliminate duplicates
        $cos[ $i->person->co_id ] = $i->person->co;
      }
    }
    
    return $cos;
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

  public function localAfterSave(\Cake\Event\EventInterface $event, \Cake\Datasource\EntityInterface $entity, \ArrayObject $options) {
    if(!empty($entity->id)) {
      if($entity->isNew()) {
        // Run setup for new CO
        
        $this->setup($entity->id);
      } elseif($entity->getOriginal('name') != $entity->get('name')) {
        // AR-CO-7 The name was changed, so we may need to update the system groups
        
        $this->Groups->addDefaults(coId: $entity->id, rename: true);
      }
    }

    return true;
  }

  /**
   * Perform a paginated delete over a large set of objects within a CO.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $coId     CO ID
   * @param  array  $tableSet Set of tables to operate over.
   * @todo   This could probably be generalized, if it were useful somewhere else
   */

  protected function paginatedDelete(int $coId, array $tableSet) {
    foreach($tableSet as $tableName => $table) {
      $iterator = new PaginatedSqlIterator(table: $table,
                                           conditions: ['co_id' => $coId],
                                           options: ['archived' => true]);

      foreach($iterator as $k => $tentity) {
        // We call delete on each entity individually so that callbacks fire,
        // in particular the unsetting of foreign keys that might be set.

        // We disable checkRules since we're hard deleting all objects in the CO.
        $table->deleteOrFail($tentity, ['useHardDelete' => true, 'checkRules' => false]);
      }
    }
  }

  /**
   * Application Rule to determine if the current entity is the COmanage CO.
   *
   * @param   Entity  $entity   Entity to be validated
   * @param   array   $options  Application rule options
   *
   * @return string|bool true if the Rule check passes, false otherwise
   * @since  COmanage Registry v5.0.0
   */

  public function ruleIsCOmanageCO($entity, array $options): string|bool {
    // We want negative logic since we want to fail if we're editing the COmanage CO
    if($entity->isCOmanageCO()) {
        return __d('error', 'edit.comanage');
      }

    return true;
  }

  /**
   * Application Rule to determine if the current entity is not Active.
   *
   * @param   Entity  $entity   Entity to be validated
   * @param   array   $options  Application rule options
   *
   * @return bool|string true if the Rule check passes, false otherwise
   * @since  COmanage Registry v5.0.0
   */

  public function ruleIsActive($entity, array $options): bool|string {
    // We want negative logic since we want to fail if the record is Active
    if($entity->status === TemplateableStatusEnum::Active) {
      return __d('error', 'delete.active');
    }
    
    return true;
  }
  
  /**
   * Perform initial setup for a CO.
   *
   * @since  COmanage Registry v5.0.0
   * @param  int  $id CO ID
   * @return bool     True on success
   */
  
  public function setup(int $id): bool {
    // AR-Type-1 Set up the default values for extended types
    $this->Types->addDefaults($id);

    // AR-CO-6 Create the default groups
    $this->Groups->addDefaults($id);

    // Set up the default settings
    $this->CoSettings->addDefaults($id);
    
    return true;
  }

  /**
   * Perform initial setup for COmanage CO
   *
   * @since  COmanage   Registry v5.0.0
   * @return null|int   null or the id of the COmanage CO
   */

  public function setupCOmanageCO(): int|null {
    $comanage_co = $this->newEmptyEntity();
    $comanage_co->name = __d('command', 'product.comanage');
    $comanage_co->description = __d('command', 'registry.co.desc');
    $comanage_co->status = StatusEnum::Active;

    $co_id = null;
    if ($this->save($comanage_co, ['checkRules' => false])) {
      $co_id = $comanage_co->id;
    }

    return $co_id;
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return $validator           Validator
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $this->registerStringValidation($validator, $schema, 'name', true);
    
    $this->registerStringValidation($validator, $schema, 'description', false);
    
    $validator->add('status', [
      'content' => ['rule' => ['inList', TemplateableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');
    
    return $validator; 
  }
}