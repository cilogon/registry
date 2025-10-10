<?php
/**
 * COmanage Registry Rule Builder Event Listener
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

namespace App\Lib\Events;

use ArrayObject;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

class RuleBuilderEventListener Implements EventListenerInterface {
  use \App\Lib\Traits\LabeledLogTrait;
  
  /**
   * Build rules event listener.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Event           $event   Cake Event
   * @param  RulesChecker    $rules   Rules Checker subject of the event
   * @return RulesChecker             Rules Checker
   */
  
  public function buildRules(Event $event, RulesChecker $rules) {
    // We automatically insert ruleValidateCO for GMR-1 and GMR-2 by examining
    // the table schema and injecting a rule for any field ending with _id.
    // We use the table schema in case a programmer forgets to define a
    // validation rule for a foreign key field.
    
    // Strictly speaking we only need to do this over the API, since the UI
    // doesn't permit these operations in the first place, but it doesn't hurt
    // to have an extra safety check, and we'd need AppController to pass in
    // the RESTful status during __construct() (like for ChangelogEventListener).
    
    $subjectTable = $event->getSubject();
    
    if(strncmp($subjectTable->getRegistryAlias(), "DebugKit.", 9)==0) {
      // Skip DebugKit calls
      $event->setResult($rules);
      return;

    }

    $schema = $subjectTable->getSchema();
    
    // We need to skip some metadata fields, including changelog and EIS fks
    // changelog
    $cl = Inflector::singularize($subjectTable->getTable()) . "_id";
    // external identity source
    $eis = "source_" . $cl;
    
    // Figure out the primary link(s) for this table.
    $primaryLinks = [];
    
    if(method_exists($subjectTable, "getPrimaryLinks")) {
      $primaryLinks = $subjectTable->getPrimaryLinks();
    }
    
    foreach($schema->columns() as $col) {
      if(in_array($col, [$cl, $eis])) {
        // Skip the changelog key since it will only every have pointed to a
        // record within the CO, and the self-dependency confuses the association
        // calculation, below
        continue;
      }
      
      if(in_array($col, $primaryLinks)) {
        $rules->addUpdate(
          [$this, 'ruleFreezePrimaryLink'],
          'freezePrimaryLink_' . $col,
          ['errorField' => $col]
        );
      } elseif(preg_match('/^.*_id$/', $col)) {
      
// XXX still need to handle whatever "unfreeze" is going to become
        $rules->add(
          [$this, 'ruleValidateCO'],
          'validateCO_' .  $col,
          ['errorField' => $col]
        );
      }
    }

    $event->setResult($rules);
  }
  
  /**
   * Define the list of implemented events.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of implemented events and associated configuration.
   */
  
  public function implementedEvents(): array {
		return [
			'Model.buildRules' => [
				'callable' => 'buildRules',
        // We don't currently need to set the priority
//				'priority' => -100
			]
		];
	}
  
  /**
   * Application Rule to prevent the CO ID of an object from being changed once
   * attached. This is more of a Security Rule than an Application Rule, but for
   * now we don't distinguish between the two types.
   *
   * This function arguably belongs in a trait or something, but then any table
   * we apply it to needs to add that trait.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleFreezePrimaryLink(EntityInterface $entity, array $options) {
    // Tables can have multiple primary link fields, but only one can be
    // populated at a time, and the primary link cannot change.
    
    // The table we are validating, eg Name
    $table = $options['repository'];
    
    // The field to check is (confusingly) $options['errorField'].
    
    if(empty($options['errorField'])) {
      return __d('error', 'rule.ValidateCo.errorField');
    }
    
    // The foreign key we are validating
    $targetField = $options['errorField'];
    
    $want = $entity->get($targetField);
    $have = $entity->getOriginal($targetField);
    
    // GMR-3 The Primary Link key cannot be changed once set. If the primary
    // link field goes to or from NULL throw an error. If it is NULL in both
    // places, then this Primary Link is not in use for the object.
    
    // Changing the primary link key (eg: person_id to external_identity_id)
    // is not permitted. To be clear, the _value_ CAN be changed (within the
    // same CO), just not which key is being used.
      
    if($want === NULL && $have === NULL) {
      // This primary link is not in use
      return true;
    }
    
    if($want != $have && ($want === NULL || $have === NULL)) {
      // GMR-3
      $this->llog('error', "GMR-3 The Primary Link key cannot be changed once set, changing " . $table->getAlias() . " record " . $entity->id . " " . $options['errorField'] . " from " . ($have ?? "null") . " to " . ($want ?? "null") . " is not allowed");
      return __d('error', 'primary_link.frozen');
    }
    
    // GMR-1 Once an entity is created within a CO, it cannot be moved to
    // another CO.

    $wantCO = $table->calculateCoForRecord($entity);
    $haveCO = $table->calculateCoForRecord($entity, true);
    
    if($wantCO != $haveCO) {
      $this->llog('error', "GMR-1 Attempt to move " . $table->getAlias() . " record " . $entity->id . " from CO " . $haveCO . " to CO " . $wantCO . " is not allowed");
      return __d('error', 'coid.frozen');
    }
    
    return true;
  }
  
  /**
   * Application Rule to require foreign keys to be within the same CO as the
   * entity being saved. This is more of a Security Rule than an Application
   * Rule, but for now we don't distinguish between the two types.
   *
   * This function arguably belongs in a trait or something, but then any table
   * we apply it to needs to add that trait.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleValidateCO(EntityInterface $entity, array $options) {
    // GMR-2 Foreign keys from one entity to another cannot cross COs.
    // The logic here requires an "anchor" that cannot change, which is the
    // primary link, which is enforced by ruleFreezePrimaryLink (which verifies
    // that the primary object cannot be altered).
    
    // The field to check is (confusingly) $options['errorField'].
    // We don't need to check "unfreeze" here since it should be checked in
    // buildRules().
    
    if(empty($options['errorField'])) {
      return __d('error', 'rule.ValidateCo.errorField');
    }
    
    // The foreign key we are validating
    $targetField = $options['errorField'];
    // The property name for the fk, which following cake's internal convention
    // simply drops the _id. Note this property must also be set in the Table's
    // belongsTo association definition.
    $targetProperty = substr($targetField, 0, strlen($targetField)-3);
    // The table we are validating, eg Name
    $table = $options['repository'];
    
    // Use the table associations to find the correct target table name
    $assn = $table->associations()->getByProperty($targetProperty);
    
    if(empty($assn)) {
      // If you're debugging this, you most likely didn't set up your
      // associations correctly.
      throw new \LogicException("Missing association from " . $table->getAlias(). " to $targetProperty in ruleValidateCO");
    }

    // The table holding the foreign key we are validating, eg Type
    $targetTable = $assn->getTarget();
    
    if(empty($entity->$targetField)) {
      // If the foreign key field is blank there's nothing to check
      return true;
    }
    
    // First we need to determine the CO of this record.
    
    $have = $table->calculateCoForRecord($entity);
    $want = $targetTable->findCoForRecord($entity->$targetField);
    
    if($want != $have) {
      $this->llog('error', "GMR-2 Field $targetField for " . $table->getAlias() . " record " . $entity->id . " cannot cross from CO " . $have . " to CO " . $want);
      return __d('error', 'rule.ValidateCo.mismatch', $targetField, $want, $have);
    }
    
    return true;
  }
}
