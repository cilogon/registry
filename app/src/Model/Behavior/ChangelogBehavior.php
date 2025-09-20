<?php
/**
 * COmanage Registry Changelog Behavior
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

namespace App\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\ORM\Query;
use Cake\Utility\Inflector;

class ChangelogBehavior extends Behavior 
{
  /**
   * Handle changelog delete of entity.
   *
   * @since  COmanage Registry v5.0.0
   * @param  CakeEventEvent $event   The beforeDelete event
   * @param                 $entity  Entity
   * @param  ArrayObject    $options Options
   * @return boolean                 True on success
   */
  
  public function beforeDelete(Event $event, $entity, \ArrayObject $options) {
    if(isset($options['useHardDelete']) && $options['useHardDelete']) {
      // Hard delete requested, so just return
      $event->setResult(true);
      return;
    }

    $subject = $event->getSubject();
    $table = $subject->getTable();
    $alias = $subject->getAlias();
    $parentfk = Inflector::singularize($table) . "_id";
    
    // Before we do anything else, make sure we're not trying to update an archive record
    if($entity->deleted || !empty($entity->$parentfk)) {
      LogBehavior::serror($alias, 'Attempt to delete archived record ' . $entity->id . ' has been declined');
      throw new \RuntimeException(__d('error', 'edit.readonly'));
    }
    
    LogBehavior::strace($alias, 'Changelog converting delete to update');
    
    // Since we stop the delete event, we need to manually trigger cascades.
    // Note Cake defaults to delete via deleteAll(), which skips callbacks,
    // which means we wouldn't be called. Models need to declare "cascadeCallbacks" to
    // true in association definitions.
    // XXX though this will slow hard delete, which doesn't need it...

    $subject->associations()->cascadeDelete($entity, $options->getArrayCopy());
    
    // Update this record as deleted

    $entity->deleted = true;
    $subject->saveOrFail(
      $entity,
      array_merge($options->getArrayCopy(), ['checkRules' => false, 'archive' => false])
    );
    
    // Stop the delete from actually happening
    $event->stopPropagation();

    // But return success
    $event->setResult(true);
    return;
  }

  /**
   * Adjust find query conditions for changelog.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Event       $event   The beforeFind event
   * @param  Query       $query   Query
   * @param  ArrayObject $options The options for the query
   * @param  boolean     $primary Whether or not this is the root query (vs an associated query)
   * @return void
   */
  
  public function beforeFind(Event $event, Query $query, \ArrayObject $options, bool $primary): void {
    if(isset($options['archived']) && $options['archived']) {
      // Archived records requested (including possibly expunge), so just return
      $event->setResult(true);
      return;
    }

    $subject = $event->getSubject();
    $table = $subject->getTable();
    $alias = $subject->getAlias();
    $parentfk = Inflector::singularize($table) . "_id";
    
    LogBehavior::strace($alias, 'Changelog altering find conditions');
    
    // Take into account all joined associations
    if(!empty($query->clause('join'))) {
      foreach($query->clause('join') as $mdl => $opts) {
        $ascParentfk = Inflector::singularize($opts['table']) . '_id';

        $query->where([$opts['alias'] . '.deleted IS NOT true'])
              ->where([$opts['alias'] . '.' . $ascParentfk . ' IS NULL']);
      }
    }
    // We use IS NOT TRUE to check for null || false, since pre-Changelog data
    // may have null instead of false.
    // (Alternately we could join two clauses for false || IS NULL.)
// Transmogrification will backfill this... do we still need it? Are there any tables
// that will be not-changelog but might become changelog?
    $query->where([$alias . '.deleted IS NOT true'])
          ->where([$alias . '.' . $parentfk . ' IS NULL']);
    
    // XXX need to also check parent key IS NULL
  }
  
  /**
   * Handle changelog archive during (before) save of object.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Event           $event   The beforeSave event
   * @param  EntityInterface $entity  Entity
   * @param  ArrayObject     $options Options
   * @return void
   */
  
  public function beforeSave(Event $event, EntityInterface $entity, \ArrayObject $options): void {
    if(isset($options['archive']) && !$options['archive']) {
      // Archiving disabled for this request, don't do anything
      return;
    }
    
    $subject = $event->getSubject();
    $table = $subject->getTable();
    $alias = $subject->getAlias();
    $parentfk = Inflector::singularize($table) . "_id";

    // Before we do anything else, make sure we're not trying to update an archive record
    if($entity->deleted || !empty($entity->$parentfk)) {
      LogBehavior::serror($alias, 'Attempt to edit archived record ' . $entity->id . ' has been declined');
      throw new \RuntimeException(__d('error', 'edit.readonly'));
    }
    
    $actor = '';
    
    if(!empty($options['actor'])) {
      // The actor information is passed via $options, which is populated by ChangelogEventListener
      
      if(isset($options['apiuser']) && $options['apiuser']) {
        $actor = 'API:';
      }
      
      $actor .= $options['actor'];
      
      // Make sure the string fits
      $actor = substr($actor, 0, 256);
    }
    
    if($entity->isNew()) {
      // This is an add, just set default metadata
      
      LogBehavior::strace($alias, 'Changelog setting default changelog metadata on add');
      $entity->deleted = false;
      $entity->revision = 0;
      $entity->actor_identifier = $actor;
      
      return;
    } else {
      // This is an edit, so copy on write
      
      LogBehavior::strace($alias, 'Changelog creating archive copy of record ' . $entity->id);
      
// XXX start a transaction that gets finished in afterSave
//     we're normally already in a transaction (if atomic), maybe we don't need to manage another one?
      $class = get_class($entity);
      
      // We disable setters because we want an exact copy of the original record
      $archive = new $class($entity->getOriginalValues(), ['useSetters' => false]);
      
      // Update the appropriate attributes
      unset($archive->id);
      $archive->$parentfk = $entity->id;
      
      // We actually want to update the *current* record with $actor, not the
      // archived copy (which was presumably updated by the previous $actor)
      $entity->actor_identifier = $actor;
      
      // We also increment the revision on the entity, not the archive
      $entity->revision++;

      // Cake 3+ doesn't have callbacks=false, so we use the archive flag so we
      // don't recurse indefinitely. We also skip validation in case (eg) validation
      // rules changed since the original record was created.

      $archiveOptions = [
        'checkRules' => false,
        'archive' => false
      ];

      // Are we relinking associated models to the archive copy?

      $relinkToArchive = isset($options['relinkToArchive']) && $options['relinkToArchive'];

      if(!$relinkToArchive) {
        // We don't want to save associated models by default since
        // it will rekey them to the new archive copy.

        $archiveOptions['associated'] = false;
      } else {
        // This means relink to archive is simple to implement
      }

      $subject->saveOrFail($archive, $archiveOptions);
      
      return;
    }
  }
}