<?php
/**
 * COmanage Registry History Trait
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

namespace App\Lib\Traits;

use \Cake\Utility\Inflector;
use \App\Lib\Enum\ActionEnum;

trait HistoryTrait {
  use \Cake\ORM\Locator\LocatorAwareTrait;
  
  /**
   * Generate a text string describing the changes in an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity Cake Entity
   * @return string         Change description
   */
  
  public function changesToString($entity): string {
    $Types = $this->getTableLocator()->get('Types');
    
    // We want to exclude the metadata from the change string, except revision,
    // which makes it easier to correlate to changelog records
    $skipFields = [
      'id',
      'created',
      'modified',
      'deleted',
      'actor_identifier'
    ];
    
    // Use the entity's visible field list to start from
    $diffFields = array_diff($entity->getVisible(), $skipFields);
    
    // Remove all _id fields, except type_id
    foreach($diffFields as $i => $f) {
      if($f != 'type_id' && preg_match('/_id$/', $f)) {
        unset($diffFields[$i]);
      }
    }
    
    // Create one string per field
    $changeSet = [];
    
    if($entity->isNew() || $entity->deleted) {
      // Generate a changeset of non-empty fields
      foreach($diffFields as $field) {
        $newValue = $entity->get($field);
        
        if(!empty($newValue)) {
          if($field == 'type_id') {
            $newValue = $Types->getTypeLabel((int)$newValue);
          }
          
          $changeSet[] = $field . ": " . $newValue;
        }
      }
    } else {
      // Ask the entity what changed. This will be a list of field/value pairs,
      // but only where field (1) is in $diffFields and (2) changed.
      $diff = $entity->extractOriginalChanged($diffFields);
      
      foreach(array_keys($diff) as $field) {
        $oldValue = $diff[$field];
        $newValue = $entity->get($field);
        
        if($field == 'type_id') {
          $oldValue = $Types->getTypeLabel((int)$diff[$field]);
          $newValue = $Types->getTypeLabel((int)$newValue);
        }
        
        if(!empty($oldValue) || !empty($newValue)) {
          $changeSet[] = $field . ": " . $oldValue . ">" . $newValue;
        }
      }
    }
    
    // And finally concatenate the field strings together
    return implode(';', $changeSet);
  }
  
  /**
   * Record history for an entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity  Entity to record history for
   * @param  string $action  Action string (if null, auto-calculate from $entity)
   * @param  string $comment History comment (if null, auto-calculate from $entity)
   * @return int             HistoryRecord ID
   */
  
  public function recordHistory($entity, ?string $action=null, ?string $comment=null): int {
    $laction = $action;
    $lcomment = $comment;
    
    if(!$laction) {
      $laction = ActionEnum::MVEAEdited;
      
      if($entity->isNew()) {
        $laction = ActionEnum::MVEAAdded;
      } elseif($entity->deleted) {
        // Note this is ChangelogBehavior turning a delete to an update
        $laction = ActionEnum::MVEADeleted;
      }
    }
    
    if(!$lcomment) {
      $langKey = 'edited.mvea';
      
      if($entity->isNew()) {
        $langKey = 'added.mvea';
      } elseif($entity->deleted) {
        // Note this is ChangelogBehavior turning a delete to an update
        $langKey = 'deleted.mvea';
      }
      
      $lcomment = __d('result', 
                      $langKey,
                      Inflector::singularize($entity->getSource()),
                      $entity->id, 
                      $this->changesToString($entity));
    }
    
    $HistoryRecords = $this->getTableLocator()->get('HistoryRecords');
    
    return $HistoryRecords->recordForPerson(
      $entity->person_id,
      $laction,
      $lcomment
    );
  }
}
