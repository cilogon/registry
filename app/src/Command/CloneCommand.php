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

    // Target database connection, for transacation management
    $tcxn = null;

    try {
      $Table = TableRegistry::getTableLocator()->get($className);
      
      $related = [];

      // The clonable table can declare related models to be cloned with it
      if(method_exists($Table, "getCloneRelations")) {
        $related = $Table->getCloneRelations();
      }

      $query = $Table->find()->where([$className.'.id' => $id]);

      if(!empty($related)) {
        $query = $query->contain($related);
      }
      
      $original = $query->firstOrFail();

      if(isset($original->do_not_clone) && $original->do_not_clone) {
        $this->io->out($original->uuid . ": Do Not Clone is set on original object, skipping " . $className . " " . $id);
        return;
      }

      // This callback shouldn't do any work, it's just a pre-flight check
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
                    . " from CO " . $sourceCoId . " to CO " . $targetCoId);

      // Clone any predecessor objects first. There is a default implementations in
      // ClonableTrait that should cover most scenarios, so we don't need to check
      // if it exists on the table
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

      // We start a transaction on the _target_ table, since we're just performing
      // reads on the source table. (In theory we could get a read lock...)

      $tcxn = $TargetTable->getConnection();
      $tcxn->begin();

      // Convert to an array, which is what we need to create the new entities,
      // and filter out the metadata fields.

      $copy = $TargetTable->filterMetadataForCopy($TargetTable, $original, $related);

      // Replace the CO ID. Clonable model must FK directly to CO.
      $copy['co_id'] = $targetCoId;

      // Check to see if there is an existing entity in the target already. We're effectively
      // doing an upsert, but spread out over multiple steps in order to allow table specific
      // callbocks to manipulate the prepared entity.

      $query = $TargetTable->find()->where([
        $TargetTable->getAlias().'.co_id' => $targetCoId, 
        $TargetTable->getAlias().'.uuid' => $original->uuid
      ]);

      $targetRelated = $related;

      if(!empty($targetRelated)) {
        if($targetDataSource != 'default') {
          // We need to convert $related to use the same prefix that getTableWithDataSource uses.
          // We create our own anonymous function here rather than use array_map to prefix
          // the array entries because array_may doesn't quite work correctly with Cake's
          // complicated relations notation. (We don't use normalizeAssocationArray because we
          // want to keep $related in the same form as it was originally specified.)

          $prefix = \Cake\Utility\Inflector::camelize($targetDataSource);

          $fn = function($related, $prefix) use (&$fn) {
            $ret = [];

            foreach($related as $k => $v) {
              if(is_int($k)) {
                // [0 = 'Foo']

                $ret[] = $prefix.$v;
              } elseif(is_array($v)) {
                // ['Foo' => ['Bar']]

                $ret[$prefix.$k] = $fn($v, $prefix);
              } else {
                // ['Foo' => 'Bar]

                $ret[$prefix.$k] = [$prefix.$v];
              }
            }

            return $ret;
          };

          $targetRelated = $fn($related, $prefix);

          // While we're here, rekey $copy as well. This is similar to what we do
          // for related models, below, but here we operate on an array and below
          // we operate on an entity.
          
          $fn2 = function($copy, $related, $targetDataSource) use (&$fn2) {
            // Because $related is passed in normalized, we can expect it to always
            // be in $model => [ $associated ] notation.

            foreach($related as $rm => $ra) {
              // We need to check both singular (hasOne) and plural (hasMany)

              // eg: http_servers
              $pluralSource = Inflector::underscore($rm);
              // eg: remote_http_servers
              $pluralTarget = $targetDataSource . "_" . $pluralSource;

              if(!empty($copy[$pluralSource])) {
                $copy[$pluralTarget] = $copy[$pluralSource];
                unset($copy[$pluralSource]);

                if(!empty($ra)) {
                  $copy[$pluralTarget] = $fn2($copy[$pluralTarget], $ra, $targetDataSource);
                }
              }

              $singularSource = Inflector::singularize($pluralSource);
              $singularTarget = Inflector::singularize($pluralTarget);

              if(!empty($copy[$singularSource])) {
                $copy[$singularTarget] = $copy[$singularSource];
                unset($copy[$singularSource]);

                if(!empty($ra)) {
                  $copy[$singularTarget] = $fn2($copy[$singularTarget], $ra, $targetDataSource);
                }
              }
            }

            return $copy;
          };

          $copy = $fn2($copy, TableUtilities::normalizeAssociationArray($related), $targetDataSource);
        }

        $query = $query->contain($targetRelated);
      }
      
      $clone = $query->first();

      if($clone) {
        // We also honor do_not_clone being set in the target CO
        if(isset($clone->do_not_clone) && $clone->do_not_clone) {
          $this->io->out($clone->uuid . ": Do Not Clone is set on the cloned object, skipping " . $className . " " . $id);
          return;
        }

        // Patch the record, related models should be correctly handled by Cake

        $TargetTable->patchEntity($clone, $copy, ['validate' => false]);
      } else {
        // Convert the array into a new entity. We disable validation since it's possible
        // validation rules changed since the original was persisted (either via code
        // changes or configuration) but we'll honor the original since at some point it
        // saved successfully.

        $entityOptions = [
          'associated'  => $targetRelated,
          // For comparison, we do not disable rules checking below
          'validate'    => false
        ];

        $clone = $TargetTable->newEntity($copy, $entityOptions);
      }

      // If $clone has foreign keys to other tables, they now point to the wrong CO.
      // Since this is a general problem, we fix it here rather than requiring each
      // model to resolve its own keys. Note the implication is that the foreign key
      // target models have been cloned already, either by getClonePredecessors or
      // by the admin having cloned all entities of the target model already, and
      // more specifically that foreign key targets can be resolved via UUID lookup.

      // This is defined by default in ClonableTrait
      $clone = $TargetTable->fixCloneForeignKeys($original, $clone, $targetCoId, $targetDataSource);

      if(method_exists($TargetTable, "prepareClone")) {
        $clone = $TargetTable->prepareClone($original, $clone, $targetDataSource);
      }

      // Prepare for the save

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
      }
      
      // Since we're managing the entity, we can skip the UUID duplication check
      $clone->_uuidCloned = true;

      // By default Cake will save one level of associations, but we want to save
      // anything in $related. Unlike validation above, we want rule check to run
      // so (eg) uniqueness checks can be enforced.
      $TargetTable->saveOrFail($clone, [
        'associated' => $targetRelated,
        // 'checkRules' => false,
        'actor' => __d('result', 'clone.actor', [$sourceCoId]),
        'clone' => true
      ]);
      
      $tcxn->commit();
    }
    catch(\Exception $e) {
      $this->io->err($e->getMessage());

      if($tcxn) {
        $tcxn->rollback();
      }
    }
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
}
