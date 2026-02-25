<?php
/**
 * COmanage Clone Command
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

namespace App\Command;

use Cake\Console\Arguments;
use Cake\Console\BaseCommand;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;

use App\Lib\Util\PaginatedSqlIterator;
use App\Lib\Util\SearchUtilities;
use App\Lib\Util\StringUtilities;
use App\Lib\Util\TableUtilities;

class CloneCommand extends BaseCommand {
  protected $io = null;

  // We track which entities we've seen because it's possible we'll end up being asked to
  // clone the same entity multiple times due to predecessor and successor processing.
  protected $seenEntities = [];

  /**
   * Build an Option Parser.
   *
   * @since  COmanage Registry v5.2.0
   * @param  ConsoleOptionParser $parser ConsoleOptionParser
   * @return ConsoleOptionParser         ConsoleOptionParser
   */

  protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser {
    $parser->addOption(
      'co_id',
      [
        'required'  => true,
        'short'     => 'c',
        'help'      => __d('command', 'opt.clone.co_id')
      ]
    )->addOption(
      'target_co_id',
      [
        'required'  => true,
        'short'     => 't',
        'help'      => __d('command', 'opt.clone.target_co_id')
      ]
    )->addOption(
      'target_server_id',
      [
        'required'  => false,   // If false, use default datasource
        'short'     => 's',
        'help'      => __d('command', 'opt.clone.target_server_id')
      ]
    )->addOption(
      'all',
      [
        'required'  => false,
        'short'     => 'a',
        'boolean'   => true,
        'help'      => __d('command', 'opt.clone.all')   // This must be explicitly requested
      ]
    )->addOption(
      'cri',
      [
        'required'  => false,
        'short'     => 'r',
        'boolean'   => true,
        'help'      => __d('command', 'opt.clone.cri')
      ]
    )->addOption(
      'model',
      [
        'required'  => false,
        'short'     => 'm',
        'boolean'   => true,
        'help'      => __d('command', 'opt.clone.model')   // clonable model to copy all of
      ]
    )->addOption(
      'uuid',
      [
        'required'  => false,
        'short'     => 'u',
        'boolean'   => true,
        'help'      => __d('command', 'opt.clone.uuid')
      ]
    );

    return $parser;
  }

  /**
   * Execute the Database Command.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Arguments $args Command Arguments
   * @param  ConsoleIo $io   Console IO
   * @throws RuntimeException
   */

  public function execute(Arguments $args, ConsoleIo $io) {
    // In general we don't want fallback classes (CFM-405) but they're particularly
    // painful here (in debugging errors), and we can safely turn them off in a
    // command line context.

    TableRegistry::getTableLocator()->allowFallbackClass(false);

    $this->io = $io;

    // By default, the target database is the same as the source (ie: cloning from one
    // CO to another on the same platform), but if -t is specified we'll set up a
    // connection to that one as the target. This assumes that the SqlServer plugin
    // is enabled, but it's a core plugin so it should be there by default.
    $targetDS = 'default';

    if(!empty($args->getOption('target_server_id'))) {
      // Create a new "remote" datasource for writing the target records.
      // Note the command line flag is "target" but we use the connection name
      // "remote" to clarify that it's a different datasource.

      // In order for the remote datasource to work correctly, all the stars must be
      // correctly aligned. In particular, the related model assocations will be rekeyed
      // below from (eg) ["FooWidgets" => ["FooWidgetRecords"]] to
      // ["RemoteFooWidgets" => ["RemoteFooWidgetRecords"]]. This then implies that the
      // entity values must also be rekeyed ($entity->foo_widgets becomes
      // $entity->remote_foo_widgets) and (importantly) that the TableLocator can resolve
      // "RemoteFooWidgets" to a Table with alias "RemoteFooWidgets" but class
      // "FooWidgetPlugin.FooWidgets". Most of this is handled below, but the plugin
      // resolution is handled in PluggableModelTrait.

      // When debugging this, keep in mind Cake will autocreate tables that it can't find
      // Table files for ($TableLocator->allowFallbackClass). (As of Registry v5.2.0 we
      // can't simply turn that off since a bunch of other stuff breaks; CFM-405.) Telltale
      // signs include entities whose path is \Cake\ORM\Entity rather than
      // \FooWidget\Model\Entity\FooWidget (and Tables with similarly generic classpaths.)

      $targetDS = 'remote';
      
      $SqlServer = TableRegistry::getTableLocator()->get('CoreServer.SqlServers');

      $SqlServer->connect((int)$args->getOption('target_server_id'), $targetDS);
    }

    if($args->getBooleanOption('all')) {
      $this->cloneAll(
        (int)$args->getOption('co_id'),
        (int)$args->getOption('target_co_id'),
        $targetDS
      );
    } elseif($args->getBooleanOption('cri')) {
      foreach($args->getArguments() as $cri) {
        $this->cloneByCri(
          $cri,
          (int)$args->getOption('co_id'),
          (int)$args->getOption('target_co_id'),
          $targetDS
        );
      }
    } elseif($args->getBooleanOption('model')) {
      foreach($args->getArguments() as $model) {
        $this->cloneByModel(
          $model,
          (int)$args->getOption('co_id'),
          (int)$args->getOption('target_co_id'),
          $targetDS
        );
      }
    } elseif($args->getBooleanOption('uuid')) {
      foreach($args->getArguments() as $uuid) {
        $this->cloneByUuid(
          $uuid,
          (int)$args->getOption('co_id'),
          (int)$args->getOption('target_co_id'),
          $targetDS
        );
      }
    }
  }

  /**
   * Clone all available objects in a CO.
   *
   * @since  COmanage Registry v5.2.0
   * @param  int    $sourceCoId       Source CO ID (in "default" Datasource)
   * @param  int    $targetCoId       Target CO ID
   * @param  string $targetDataSource Target Dataource
   * @throws InvalidArgumentException
   */

  public function cloneAll(
    int $sourceCoId,
    int $targetCoId,
    string $targetDataSource='default'
  ) {
    $clonableModels = SearchUtilities::getClonableModels();

    foreach($clonableModels as $m) {
      $this->cloneByModel($m, $sourceCoId, $targetCoId, $targetDataSource);
    }
  }

  /**
   * Clone an object via CRI.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string $cri              Change Request Identifier
   * @param  int    $sourceCoId       Source CO ID (in "default" Datasource)
   * @param  int    $targetCoId       Target CO ID
   * @param  string $targetDataSource Target Dataource
   * @throws InvalidArgumentException
   */

  public function cloneByCri(
    string $cri,
    int $sourceCoId,
    int $targetCoId,
    string $targetDataSource='default'
  ) {
    $this->io->out("Searching for CRI " . $cri);

    $results = SearchUtilities::criSearch($sourceCoId, $cri);

    foreach($results as $class => $entities) {
      $this->io->out("Found " . $entities->count() . " " . $class . " records to clone");

      foreach($entities as $e) {
        $this->cloneEntity($class, $e->id, $sourceCoId, $targetCoId, $targetDataSource);
      }
    }
  }

  /**
   * Clone all objects of a specific model.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string $className        Model class name in Table format (eg: "People")
   * @param  int    $sourceCoId       Source CO ID (in "default" Datasource)
   * @param  int    $targetCoId       Target CO ID
   * @param  string $targetDataSource Target Dataource
   * @throws InvalidArgumentException
   */

  public function cloneByModel(
    string $className,
    int $sourceCoId,
    int $targetCoId,
    string $targetDataSource='default'
  ) {
    if(!in_array($className, SearchUtilities::getClonableModels())) {
      $this->io->err($className . " is not a clonable model");
      return;
    }

    // We handle Types specially
    if($className == 'Types') {
      $this->cloneTypes($sourceCoId, $targetCoId, $targetDataSource);
    } else {
      // Pull all records for this model within the CO. We use PaginatedSqlIterator
      // because certain models can have large numbers of records (eg People).

      $Table = TableRegistry::getTableLocator()->get($className);

      // Clonable model must FK directly to CO
      $iterator = new PaginatedSqlIterator($Table, ['co_id' => $sourceCoId]);

      $this->io->out($iterator->count() . " records available for " . $className);

      foreach($iterator as $entity) {
        $this->cloneEntity($className, $entity->id, $sourceCoId, $targetCoId, $targetDataSource);
      }
    }
  }

  /**
   * Clone an object via UUID.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string $uuid             UUID for source object
   * @param  int    $sourceCoId       Source CO ID (in "default" Datasource)
   * @param  int    $targetCoId       Target CO ID
   * @param  string $targetDataSource Target Dataource
   * @throws InvalidArgumentException
   */

  public function cloneByUuid(
    string $uuid,
    int $sourceCoId,
    int $targetCoId,
    string $targetDataSource='default'
  ) {
    $this->io->out("Searching for UUID " . $uuid);

    $result = SearchUtilities::uuidSearch($sourceCoId, $uuid);

    if(!$result) {
      $this->io->err(__d('error', 'notfound', [$uuid]));
      return;
    }

    $this->cloneEntity($result['class'], $result['entity']->id, $sourceCoId, $targetCoId, $targetDataSource);
  }

  /**
   * Clone a single entity.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string $className        Class Name, in Table format (ie: "People")
   * @param  int    $id               Entity ID
   * @param  int    $sourceCoId       Source CO ID (in "default" Datasource)
   * @param  int    $targetCoId       Target CO ID
   * @param  string $targetDataSource Target Dataource
   * @throws InvalidArgumentException
   */

  protected function cloneEntity(
    string $className,
    int    $id,
    int    $sourceCoId,
    int    $targetCoId,
    string $targetDataSource
  ) {
    // First check if we've already processed this entity.
    if(isset($this->seenEntities[$className][$id])) {
      $this->io->out("$className $id already processed, skipping");
      return;
    }

    // If for some reason we fail, we won't try again the second time
    $this->seenEntities[$className][$id] = time();

    // In general, we're going to have to pull each record twice. The various cloneBy*
    // calls will do a high level search to find records to clone, but then we need to
    // re-find() the entity in case its table wants to pull associated models or perform
    // other callbacks.

    // We wrap everything in a try/catch so an individual error doesn't cause the whole
    // process to abort.

    // Target database connection, for transaction management
    $tcxn = null;

    try {
      $Table = TableRegistry::getTableLocator()->get($className);
      
      // $related = [];
      $hasOne = [];         // hasOne relations, in Cake Model-only notation
      $hasOnePlugin = [];   // hasOne relations, in Plugin.Model format
      $hasMany = [];        // hasMany relations, in Cake Model-only notation
      $hasManyPlugin = [];  // hasMany relations, in Plugin.Model format

      // Determine if there are any hasOne relations that must be cloned with
      // the Clonable Object. This is to ensure a consistent configuration, and is
      // primarily intended for (eg) Pluggable Models and their Entry Point Models.

      if(method_exists($Table, "getCloneHasOne")) {
        $hasOnePlugin = $Table->getCloneHasOne();
        // Note this won't work if we support more complex relations (see hasMany
        // handling below, which is written slightly differently)
        $hasOne = array_map('\App\Lib\Util\StringUtilities::pluginModel', $hasOnePlugin);
      }

      $query = $Table->find()->where([$className.'.id' => $id]);

      if(!empty($hasOne)) {
        $query = $query->contain($hasOne);
      }
      
      // Pull the original from the Source and check for do_not_clone.

      $original = $query->firstOrFail();

      if(isset($original->do_not_clone) && $original->do_not_clone) {
        $this->io->out($original->uuid . ": Do Not Clone is set on original object, skipping " . $className . " " . $id);
        return;
      }

      // Verify any necessary dependencies before the cloning begins.
      // This callback shouldn't do any work, it's just a pre-flight check.

      if(method_exists($Table, "checkCloneDependencies")) {
        try {
          $Table->checkCloneDependencies($original, $targetDataSource);
        }
        catch(\Exception $e) {
          $this->io->out($original->uuid . ": checkCloneDependencies failed, skipping " . $className . " " . $id . ": " . $e->getMessage());
          return;
        }
      }

      $this->io->out($original->uuid . ": Cloning " . $className . " " . $id
                    . " from CO " . $sourceCoId . " to CO " . $targetCoId . " using $targetDataSource target");

      // Resolve any dependencies, and clone any predecessor objects first.

      // There is a default implementations in ClonableTrait that should cover most
      // scenarios, so we don't need to check if it exists on the table

      $uuids = $Table->getClonePredecessors($original);

      if(!empty($uuids)) {
        $this->io->out("-- Processing Predecessor Entities --");

        foreach($uuids as $uuid) {
          $this->cloneByUuid($uuid, $sourceCoId, $targetCoId, $targetDataSource);
        }

        $this->io->out("-- Finished Processing Predecessor Entities --");
      }

      $TargetTable = TableUtilities::getTableWithDataSource(
        tableName: $className,
        connectionName: $targetDataSource
      );

      // Prepare a copy of $original for upsert ($copy) and search for the data
      // in the Target database ($clone). There is some fairly complex recursion here
      // in order to process $related models, however we now only support direct hasOne
      // relations in $related (hasMany relations are handled below) so mostly that
      // code is here in case we need it again in the future.

      // We start a transaction on the _target_ table, since we're just performing
      // reads on the source table. (In theory we could get a read lock...)

      $tcxn = $TargetTable->getConnection();
      $tcxn->begin();

      // Convert to an array, which is what we need to create the new entities,
      // and filter out the metadata fields.

      $copy = $TargetTable->filterMetadataForCopy($TargetTable, $original, $hasOne);

      // Replace the CO ID. Clonable model must FK directly to CO.
      $copy['co_id'] = $targetCoId;

      // Check to see if there is an existing entity in the target already. We're effectively
      // doing an upsert, but spread out over multiple steps in order to allow table specific
      // callbocks to manipulate the prepared entity.

      $query = $TargetTable->find()->where([
        $TargetTable->getAlias().'.co_id' => $targetCoId, 
        $TargetTable->getAlias().'.uuid' => $original->uuid
      ]);

      $prefix = \Cake\Utility\Inflector::camelize($targetDataSource);
      $targetHasOne = [];

      if(!empty($hasOne)) {
        if($targetDataSource != 'default') {
          foreach($hasOne as $h) {
            // Prefix the hasOne relations (we're assuming only one level for now,
            // so no recursion) with the data source label.

            $targetHasOne[] = $prefix.$h;

            // Also, while we're here prefix the property, if set.
            // (eg: $copy['api_source'] -> $copy['remote_api_source'])
            // (This could arguably be done in filterMetadataForCopy, but as a general
            // rule we seem to be managing datasource-specific prefixing here in
            // CloneCommand.)

            $property = Inflector::singularize(Inflector::underscore($h));
            $targetProperty = $targetDataSource . "_" . $property;

            if(array_key_exists($property, $copy)) {
              $copy[$targetProperty] = $copy[$property];
              unset($copy[$property]);
            }
          }
        } else {
          $targetHasOne = $hasOne;
        }

        $query->contain($targetHasOne);
      }

      $clone = $query->first();

      // We want a new or patched entity to have any hasOne relations copied
      // along with it, and we disable validation since it's possible validation
      // rules changed since the original was persisted (either via code changes
      // or configuration) but we'll honor the original since at some point it
      // saved successfully.

      $entityOptions = [
        'associated'  => $targetHasOne,
        // For comparison, we do not disable rules checking below
        'validate'    => false
      ];

      if($clone) {
        // We also honor do_not_clone being set in the target CO
        if(isset($clone->do_not_clone) && $clone->do_not_clone) {
          $this->io->out($clone->uuid . ": Do Not Clone is set on the cloned object, skipping " . $className . " " . $id);
          return;
        }

        // Patch the record, including any hasOne relation

        $TargetTable->patchEntity($clone, $copy, $entityOptions);
      } else {
        // Convert the array into a new entity. We 

        $clone = $TargetTable->newEntity($copy, $entityOptions);
      }

      // If $clone has foreign keys to other tables, they now point to the wrong CO.
      // Since this is a general problem, we fix it here rather than requiring each
      // model to resolve its own keys. Note the implication is that the foreign key
      // target models have been cloned already, either by getClonePredecessors or
      // by the admin having cloned all entities of the target model already, and
      // more specifically that foreign key targets can be resolved via UUID lookup.

      // This is defined by default in TableMetaTrait
      $clone = $TargetTable->fixCloneForeignKeys($original, $clone, $targetCoId, $targetDataSource);

      if(method_exists($TargetTable, "prepareClone")) {
        $clone = $TargetTable->prepareClone($original, $clone, $targetDataSource);
      }

      // Prepare for the save
/*
      if($targetDataSource != 'default' && !empty($related)) {
        // We need to rekey the related models, and we need to check both singular (hasOne)
        // and plural (hasMany). This is similar to $fn2 above, but we operate on an entity
        // rather than an array here.

        $fn3 = function($clone, $related, $targetDataSource) use(&$fn2) {
          // Because $related is passed in normalized, we can expect it to always
          // be in $model => [ $associated ] notation.

          foreach($related as $rm => $ra) {
            // eg: http_servers
            $pluralSource = Inflector::underscore($rm);
            // eg: remote_http_servers
            $pluralTarget = $targetDataSource . "_" . $pluralSource;

            if(!empty($clone->$pluralSource)) {
              $clone->$pluralTarget = $clone->$pluralSource;
              unset($clone->$pluralSource);

              if(!empty($ra)) {
                $clone->$pluralTarget = $fn3($clone->pluralTarget, $ra, $targetDataSource);
              }
            }

            $singularSource = Inflector::singularize($pluralSource);
            $singularTarget = Inflector::singularize($pluralTarget);

            if(!empty($clone->$singularSource)) {
              $clone->$singularTarget = $clone->$singularSource;
              unset($clone->$singularSource);

              if(!empty($ra)) {
                $clone->$singularTarget = $fn3($clone->singularTarget, $ra, $targetDataSource);
              }
            }
          }

          return $clone;
        };

        $clone = $fn3($clone, TableUtilities::normalizeAssociationArray($related), $targetDataSource);
      }*/
      
      // Since we're managing the entity, we can skip the UUID duplication check
      $clone->_uuidCloned = true;

      // By default Cake will save one level of associations, but we want to save
      // anything in $related. Unlike validation above, we want rule check to run
      // so (eg) uniqueness checks can be enforced.
      $TargetTable->saveOrFail($clone, [
        'associated' => $targetHasOne,
        // 'checkRules' => false,
        'actor' => __d('result', 'clone.actor', [$sourceCoId]),
        'clone' => true
      ]);

      // Handle related models. We can't rely on Cake's related model patching
      // because it can't deterministically identify which source record correlates
      // to which target record for hasMany relations (ie: it only patches records
      // that have the same primary key).

      // We handle hasMany relations on $className (eg: People -> EmailAddresses)
      // as well as on $className's hasOne relations (eg: ExternalIdentitySource
      // -> ApiSource -> ApiSourceRecords).

      if(method_exists($Table, "getCloneHasMany")) {
        // $hasMany relations, in Plugin.Model notation (for relations to plugin models)
        // so that we can identify the plugin we need to bind
        $hasManyPlugin = TableUtilities::normalizeAssociationArray($Table->getCloneHasMany());

        if(!empty($hasManyPlugin)) {
          // We have an array of models (eg: MatchServers, EmailAddresses)
          // associated with the current model (Server, People). In the former
          // example we're operating via hasOne relations, in the latter we're
          // operating direct. We'll need to recurse here.

          $this->cloneEntityRelations($original, $clone->id, $hasManyPlugin, $targetDataSource, $targetCoId);

          foreach($hasManyPlugin as $rm => $ra) {
            if(in_array($rm, $hasOnePlugin)) {
              // $rm is a hasOne to the current model, eg Server -> MatchServer,
              // which we've already handled, so we jump directly to the next
              // relation, eg MatchServer -> MatchServerAttribute.

              // Convert eg CoreServer.MatchServers -> match_server
              $singularSource = Inflector::singularize(Inflector::underscore(StringUtilities::pluginModel($rm)));
              $singularTarget =
                ($targetDataSource != 'default')
                // eg: remote_match_server
                ? Inflector::underscore($targetDataSource . "_" . $singularSource)
                : $singularSource;

              // In particular for hasOne relations, we may get a set of available
              // relations (all active plugins) but only one (and exactly one) will
              // actually be in use. if $original->$singularSource is not defined
              // (or more accurately not populated), simply skip this relation and move on.

              if(!empty($original->$singularSource)) {
                $this->cloneEntityRelations(
                  $original->$singularSource,
                  $clone->$singularTarget->id,
                  $hasManyPlugin[$rm],
                  $targetDataSource,
                  $targetCoId
                );
              }
            } else {
              // $rm is a hasMany to the current model, eg Person -> EmailAddress
              // or Pipeline -> Flange, so we start there.

              $this->cloneEntityRelations($original, $clone->id, $hasManyPlugin, $targetDataSource, $targetCoId);
            }
          }
        }
      }
      
      // Perform any table specific follow up tasks. Unlike checkCloneDependencies
      // this call may perform work.

      if(method_exists($TargetTable, "postClone")) {
        try {
          $TargetTable->postClone($clone, $targetDataSource);
        }
        catch(\Exception $e) {
          // We catch the Exception to provide context
          throw new \Exception($clone->uuid . ": postClone failed: " . $e->getMessage());
        }
      }

      $tcxn->commit();  
    }
    catch(\Exception $e) {
      $this->io->err($e->getMessage());

      if($tcxn) {
        $tcxn->rollback();
      }
    }

    if(method_exists($Table, "getCloneSuccessors")) {
      // Now clone any successor entities

      $uuids = $Table->getCloneSuccessors($original);

      // We accept both UUIDs and PaginatedSqlIterators in the array. We have to accept
      // multiple PaginatedSqlIterators because each one can only handle a single model.
      // Failure to clone a successor entity does _not_ fail the primary clone action,
      // and does not prevent other successors from being cloned.

      $this->io->out("== Processing Successor Entities ==");

      foreach($uuids as $uuid) {
        if(is_string($uuid)) {
          // Simple UUID
          try {
            $this->cloneByUuid($uuid, $sourceCoId, $targetCoId, $targetDataSource);
          }
          catch(\Exception $e) {
            $this->io->err($uuid . ": " . $e->getMessage());
          }
        } else {
          // PaginatedSqlIterator
          foreach($uuid as $entity) {
            try {
              $this->cloneByUuid($entity->uuid, $sourceCoId, $targetCoId, $targetDataSource);
            }
            catch(\Exception $e) {
              $this->io->err($uuid . ": " . $e->getMessage());
            }
          }
        }
      }

      $this->io->out("== Finished Processing Successor Entities ==");
    }
  }

  /**
   * Clone the relations of the entity currently being cloned.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Entity   $original         The entity being cloned
   * @param  int      $cloneId          The record ID (in the target datasource) of the cloned entity
   * @param  array    $related          Array of related models, in normalized, Plugin.Model format
   * @param  string   $targetDataSource Label for the target datasource
   * @param  int      $targetCoId       CO ID in the target datasource
   */

  protected function cloneEntityRelations(
    \Cake\ORM\Entity  $original,
    int               $cloneId,
    array             $related,
    string            $targetDataSource,
    int               $targetCoId
  ): void {
    // $original is the parent of the hasMany relation, eg MatchServer $cloneId is the id of the
    // (already saved) target copy in $targetDataSource. Because $related is passed in normalized,
    // we can expect it to always be in $model => [ $associated ] notation.

    foreach($related as $rt => $subrelations) {
      $this->io->out("=== Processing Related Entities ($rt) ===");

      // Sync the source and target tables for this related model. This code is optimized to work
      // with the PaginatedSqlIterator for related models that might have large enough sets of data
      // to require it, otherwise we'll simply pull all records the old fashioned way.

      // eg: MatchServerAttributes
      $SourceRelatedTable = TableRegistry::getTableLocator()->get($rt);
      $TargetRelatedTable = TableUtilities::getTableWithDataSource(
        tableName: $rt,
        connectionName: $targetDataSource
      );

      // eg: match_server_id
      $parent_key = StringUtilities::entityToForeignKey($original);

      // We need to check if $original hasOne $related, since we handle that differently from
      // hasMany. We could simply check if $related defines originalid (since that's our
      // correlation key), but at some point someone adding a new clonable model will forget
      // to define originalid on the hasMany relations and that will cause endless debugging pain.
      // So instead we get the table for $original and check its relations.

      $OriginalTable = TableRegistry::getTableLocator()->get($original->getSource());

      // For Plugins, $rt will be in Plugin.Model format, but getAssociation wants Model (Alias) format

      $rtAlias = StringUtilities::pluginModel($rt);

      if($OriginalTable->getAssociation($rtAlias)->type() == \Cake\ORM\Association::ONE_TO_ONE) {
        // hasOne

        // This is (eg) a Flange's Plugin configuration (such as IdentifierMappers), which in turn
        // might have subrelations. There should be at most one record in $SourceRelatedTable with
        // a $parent_key of $original->id, upsert it into $TargetRelatedTable.

        $srcent = $SourceRelatedTable->find()
                                      ->where([$parent_key => $original->id])
                                      ->first();
        
        $targetent = $TargetRelatedTable->find()
                                        ->where([$parent_key => $cloneId])
                                        ->first();

        if(!empty($srcent)) {
          $this->cloneUpsert(
            $SourceRelatedTable,
            $srcent,
            $TargetRelatedTable,
            $targetent,
            $parent_key,
            $cloneId,
            $targetDataSource,
            $targetCoId
          );

          if(!empty($related[$rt])) {
            // Recurse on any subrelations, but only on upserts.
            // (Cake's dependency handling should deal with deletes.)

            $this->cloneEntityRelations($srcent, $targetent->id, $related[$rt], $targetDataSource, $targetCoId);
          }
        } elseif(!empty($targetent)) {
          // If there is no $srcent but there is a $targetent, delete $targetent.

          $this->io->out("Deleting target record " . $targetent->id);

          $TargetRelatedTable->delete($targetent);
        }
      } elseif($OriginalTable->getAssociation($rtAlias)->type() == \Cake\ORM\Association::ONE_TO_MANY) {
        // hasMany

        // Pull the records in the source table, to process adds and updates. We always use
        // PaginatedSqlIterator here even though most of the time we won't need it, because
        // in order to detect the cases where we do we either need to (1) annotate each model
        // that _might_ need it, which is problematic especially for plugins we don't control,
        // or (2) perform a count() to see if we do need it. But PaginatedSqlIterator for small
        // datasets is just a count() followed by a select all, which reduces down to the same thing.

        $sourceIterator = new PaginatedSqlIterator($SourceRelatedTable, [$parent_key => $original->id]);
        
        // Track which entries we've processed from source
        $foundEntities = [];

        // Iterate over all source entities. For each one, we basically perform an upsert, but
        // since not all models currently implement UpsertTrait we do it manually.

        $this->io->out($sourceIterator->count() . " records in source to sync");

        foreach($sourceIterator as $srcent) {
          $targetent = $TargetRelatedTable->find()
                                          ->where(['originalid' => $srcent->id])
                                          // There should be at most one
                                          ->first();
          
          $foundEntities[] = $this->cloneUpsert(
            $SourceRelatedTable,
            $srcent,
            $TargetRelatedTable,
            $targetent,
            $parent_key,
            $cloneId,
            $targetDataSource,
            $targetCoId,
            true
          );

          if(!empty($related[$rt])) {
            // Recurse on any subrelations. We have to recurse on _each_ source entity.
            // We only recurse on inserts and updates. We assume that on a delete
            // Cake's dependency declarations will remove the related models.

            // If $original was Pipeline and $srcent was Flange, we're now calling ourselves
            // with Flange and its relations (its Plugin instantiations, eg PipelineToolkit.PersonRoleMappers)
            $this->cloneEntityRelations($srcent, $targetent->id, $related[$rt], $targetDataSource, $targetCoId);
          }
        }

        // Now pull the records in the target table, to process deletes
        $targetIterator = new PaginatedSqlIterator($TargetRelatedTable, [$parent_key => $cloneId]);

        $this->io->out("Reviewing " . $targetIterator->count() . " records in target for deletions");

        foreach($targetIterator as $targetent) {
          if(!in_array($targetent->id, $foundEntities)) {
            // We didn't see this target entry in the source data, so remove it

            $this->io->out("Deleting target record " . $targetent->id);

            $TargetRelatedTable->delete($targetent);
          }
        }                
      }
      // else unsupported association type

      $this->io->out("=== Finished Processing Related Entities ($rt) ===");
    }

    // We don't need to return anything because we processed our own saves
  }

  /**
   * Clone all Types.
   *
   * @since  COmanage Registry v5.2.0
   * @param  int    $sourceCoId       Source CO ID (in "default" Datasource)
   * @param  int    $targetCoId       Target CO ID
   * @param  string $targetDataSource Target Dataource
   * @throws InvalidArgumentException
   */

  protected function cloneTypes(
    int $sourceCoId,
    int $targetCoId,
    string $targetDataSource
  ) {
    // Because we handle Types by mapping database value to/from primary key, the UUID
    // is less important, and the administrator can decide to clone Types or just make
    // sure the requisite database values are defined on both sides. When syncing by
    // models, we'll handle all Types by database value, and as a side effect we'll
    // sync the UUIDs.

    $Types = TableRegistry::getTableLocator()->get('Types');

    $sourceTypes = $Types->find()->where(['co_id' => $sourceCoId])->all();

    $TargetTypes = TableUtilities::getTableWithDataSource(
      tableName: 'Types',
      connectionName: $targetDataSource
    );

    foreach($sourceTypes as $t) {
      if(isset($t->do_not_clone) && $t->do_not_clone) {
        $this->io->out($t->uuid . ": Do Not Clone is set, skipping Type " . $t->id);
        continue;
      }

      $this->io->out($t->uuid . ": Cloning Type " . $t->id
                    . " from CO " . $sourceCoId . " to CO " . $targetCoId);

      // We can just use UpsertTrait since we're not relying on UUID
      $targetData = $TargetTypes->filterMetadataForCopy($TargetTypes, $t);

      $targetData['co_id'] = $targetCoId;

      try {
        $TargetTypes->upsertOrFail(
          data: $targetData,
          whereClause: [
            'co_id' => $targetCoId,
            'attribute' => $t->attribute,
            'value' => $t->value,
          ],
          options: ['actor' => __d('result', 'clone.actor', [$sourceCoId])]
        );
      }
      catch(\Exception $e) {
        $this->io->err($e->getMessage());
      }
    }
  }

  /**
   * Perform a contextually appropriate upsert.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Table    $SourceRelatedTable   Source Table
   * @param  Entity   $srcent               Source (original) Entity
   * @param  Table    $TargetRelatedTable   Target Table
   * @param  Entity   $targetent            Target Entity, if one exists
   * @param  string   $parentKey            Parent key from $targetent to its parent record
   * @param  int      $cloneId              The clone record ID
   * @param  string   $targetDataSource     Label for the target datasource
   * @param  int      $targetCoId           CO ID in the target datasource
   * @param  bool     $injectOriginalId     Whether to inject the original source record ID into the entity (for hasMany)
   * @return int                            The record ID of the upserted entity
   */

  protected function cloneUpsert(
    \Cake\ORM\Table   $SourceRelatedTable,
    \Cake\ORM\Entity  $srcent,
    \Cake\ORM\Table   $TargetRelatedTable,
    ?\Cake\ORM\Entity $targetent,
    string            $parentKey,
    int               $cloneId,
    string            $targetDataSource,
    int               $targetCoId,
    bool              $injectOriginalId=false
  ): int {
    // We don't use UpsertTrait because not every model is currently UpsertTrait enabled
    // (though maybe they should be).

    if($targetent) {
      // Update

      // Filter the metadata
      $targetdata = $TargetRelatedTable->filterMetadataForCopy(
        $SourceRelatedTable,
        $srcent
      );

      $targetent = $TargetRelatedTable->patchEntity($targetent, $targetdata);

      // Fix the foreign keys
      $targetent = $TargetRelatedTable->fixCloneForeignKeys(
        $srcent,
        $targetent,
        $targetCoId,
        $targetDataSource
      );

      // We can't just call $targetent->getDirty() here because all foreign keys will flip
      // when we patchEntity with the filtered $srcent data, even though they flip back when
      // fixCloneForeignKeys() runs. (Cake just notes that the values changed, not that they
      // changed back to what they were.) To determine if $targetent is really dirty we walk
      // the list of dirty fields reported by Cake and compare them against their original
      // values (which the entity does correctly track).

      $dirty = false;

      foreach($targetent->getDirty() as $d) {
        if($targetent->$d !== $targetent->getOriginal($d)) {
          // This field is dirty, we don't need to check anything else

          $dirty = true;
          break;
        }
      }

      if($dirty) {
        $this->io->out("Updating copy of source record " . $srcent->id
                      . " as target record " . $targetent->id);
        
        $TargetRelatedTable->saveOrFail($targetent);
      }
    } else {
      // Insert

      // Filter the metadata
      $targetdata = $TargetRelatedTable->filterMetadataForCopy(
        $SourceRelatedTable,
        $srcent
      );

      $targetent = $TargetRelatedTable->newEntity($targetdata);

      // Fix the foreign keys
      $targetent = $TargetRelatedTable->fixCloneForeignKeys(
        $srcent,
        $targetent,
        $targetCoId,
        $targetDataSource
      );

      // Insert the parent key, _after_ fixing the foreign keys. (We could actually insert the
      // source FK and let fixCloneForeignKeys correct it, but this is clearer)
      $targetent->$parentKey = $cloneId;

      if($injectOriginalId) {
        // Insert originalid, _after_ fixing the foreign keys
        $targetent->originalid = $srcent->id;
      }

      $TargetRelatedTable->saveOrFail($targetent);

      $this->io->out("Inserted copy of source record " . $srcent->id
                    . " as target record " . $targetent->id);
    }

    return $targetent->id;
  }
}
