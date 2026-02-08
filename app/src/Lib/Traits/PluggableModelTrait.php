<?php
/**
 * COmanage Registry PluggableModel Trait
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

use Cake\ORM\ResultSet;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Util\PaginatedSqlIterator;
use App\Lib\Util\StringUtilities;
use App\Lib\Util\TableUtilities;

trait PluggableModelTrait {
  // The set of plugin entry point models used in configurations for this model
  protected $_pluginModels = [];

  /**
   * Callback after data is marshaled into an entity.
   *
   * @since  COmanage Registry v5.1.0
   * @param  EventInterface   $event   afterMarshal event
   * @param  Entity Interface $entity  Marshalled entity
   * @param  ArrayObject      $data    Entity data
   * @param  ArrayObject      $options Callback options
   */

  public function afterMarshal(
    \Cake\Event\EventInterface $event, 
    $entity, //\Cake\Event\EntityInterface $entity, 
    \ArrayObject $data,
    \ArrayObject $options
  ) {
    // For some reason Cake doesn't seem to marshal our related plugin model,
    // possibly because it can't find the table to create the new entity.
    // If we see we have a plugin defined and an array of data, convert it to
    // an entity instead. (CopyTrait relies on this behavior.)

    if(!empty($entity->plugin)) {
      // The plugin is the full model path, eg CoreEnroller.InvitationAccepters.
      // Since plugins all have a hasOne relation, we need to convert that to
      // the singular form (eg invitation_accepter).

      // Get the plugin component of the path and lowercase it
      $m = Inflector::singularize(Inflector::underscore(StringUtilities::pluginModel($entity->plugin)));

      if(!empty($entity->$m) && is_array($entity->$m)) {
        // Convert this array to an entity. We'll need to obtain the table, too

        $PluginTable = TableRegistry::getTableLocator()->get($entity->plugin);

        $entity->$m = $PluginTable->newEntity($entity->$m);

// XXX CFM-127, CFM-31 when plugins want to do more complex operations on duplicate, add it here
      }
    }
  }

  /**
   * Check for any dependencies that must be in place before cloning begins.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  EntityInterface  $original         Original entity
   * @param  string           $targetDataSource Target DataSource connection name
   */

  public function checkCloneDependencies(
    \Cake\Datasource\EntityInterface $original,
    string $targetDataSource='default'
  ) {
    // Verify the plugin in use is active in the target database. If we're on the same
    // datasource (ie: on the same platform) then the Plugin set is by definition the same,
    // so we only need to perform this check when the target datasource is different.

    if(!empty($original->plugin) && $targetDataSource != 'default') {
      $TargetPlugins = TableUtilities::getTableWithDataSource(
        tableName: "Plugins",
        connectionName: $targetDataSource
      );

      // $TargetPlugins = TableUtilities::getTableFromRegistry(alias: $options['alias'], options: $options);

      // We need the physical plugin name
      $pluginName = StringUtilities::pluginPlugin($original->plugin);

      // Just running find() will be sufficient for now, though the error may not be obvious
      $TargetPlugins->find()
                    ->where([
                      'plugin'  => $pluginName,
                      'status'  => SuspendableStatusEnum::Active
                    ])
                    ->firstOrFail();
      
      // While we're here, we instantiate RemoteModel aliases for each of the plugin
      // relations (recursively) so Cake's TableLocator can find the correctly instantiated
      // model (attached to the remote data source) at save(). (For further discussion, see
      // the comments in CloneCommand::execute().) We don't actually need the Table
      // objects here, we just want to make sure the TableRegistry is correctly set up.
      
      $related = TableUtilities::normalizeAssociationArray($this->getCloneHasMany());

      $fn = function($related, $targetDataSource, $pluginName) use (&$fn) {
        foreach($related as $rm => $ra) {
          TableUtilities::getTableWithDataSource(
            tableName: $pluginName.".".$rm,
            connectionName: $targetDataSource
          );

          $fn($ra, $targetDataSource, $pluginName);
        }
      };

      // getCloneRelations() will return all possible relations for all plugins for the
      // current model (eg if $original is ExternalIdentitySource, we'll get back an
      // array of all EIS plugins), but we only want to instantiate related models
      // for $plugin.

      $pluginModel = StringUtilities::pluginPlugin($original->plugin);

      if(!empty($related[$pluginModel])) {
        $fn($related[$pluginModel], $targetDataSource, $pluginName);
      }
    }
  }

  /**
   * Get the set of hasMany related models that are to be duplicated along with
   * this one, or its hasOne relations.
   * 
   * @since  COmanage Registry v5.2.0
   * @return array    Array of models, in contain() format
   */

  public function getCloneHasMany(): array {
    $ret = [];

    foreach($this->_pluginModels as $entryPoint) {
      $PluginTable = TableRegistry::getTableLocator()->get($entryPoint);

      $hasMany = $PluginTable->associations()->getByType('hasMany');

      if(!empty($hasMany)) {
        foreach($hasMany as $h) {
          // getClassName should return the fully qualified Plugin.Model name
          $ret[$entryPoint][] = $h->getClassName();
        }
      }
    }

    return $ret;
  }

  /**
   * Get the set of hasOne related models that most be duplicated along with this one.
   * 
   * @since  COmanage Registry v5.2.0
   * @return array    Array of models, in contain() format
   */

  public function getCloneHasOne(): array {
    $ret = [];

    foreach($this->_pluginModels as $entryPoint) {
      $ret[] = $entryPoint; //StringUtilities::pluginModel($entryPoint);
    }

    return $ret;
  }

  /**
   * Get the set of entities that are to be cloned after $original.
   * 
   * The returned array may include both UUIDs (strings) and PaginatedSqlIterators,
   * where the Iterator returns only clonable entities.
   * 
   * @since  COmanage Registry v5.2.0
   * @param   EntityInterface $original Current entity being cloned
   * @return  array                     Array of UUIDs and/or PaginatedSqlIterators
   */

  public function getCloneSuccessors(
    \Cake\Datasource\EntityInterface $original
  ): array {
    // We don't really know whether we need to use PaginatedSqlIterator for every
    // hasMany relation (without adding annotations of some form), and indeed in most
    // cases we probably don't need it (smaller deployments, models with only a few
    // related entities), but for the cases where we need it we really need it
    // (ApiSourceRecords, EnvSourceIdentities) so we always use it. (The overhead
    // for smoller data sets should be marginal.)

    // Because PaginatedSqlIterators only operate over a single table, we need to
    // return one per hasMany relation.
    $ret = [];

    return $ret;
  }

  /**
   * Determine the plugin type used by this Pluggable Model. This is the lowercased
   * singular prefix of the Pluggable Model Table name. eg: For "ReportsTable" the
   * plugin type is "report".
   * 
   * @since  COmanage Registry v5.0.0
   * @return string     Plugin model type
   */

  public function getPluggableModelType(): string {
    return Inflector::underscore(StringUtilities::tableToEntityName($this));
  }

  /**
   * Obtain the list of plugin relations, suitable for passing to contains().
   * 
   * @since  COmanage Registry v5.0.0
   * @return array          Array of strings of model names
   */

  public function getPluginRelations(): array {
    // _pluginModels is an array with entries of the form Plugin.Model
    // (eg: SqlConnector.SqlProvisioners) but we want to return an array 
    // of just the Models for use in contains().

    return array_map(
      function($v) { $bits = explode('.', $v); return $bits[1] ;},
      $this->_pluginModels
    );
  }

  /**
   * Instantiate a plugin model that is NOT a Cake Table model.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $pmodel   Model, in Plugin.Model format
   * @param  string $path     Path to model within Plugin, in namespace format (eg: \Lib\Jobs)
   * @return object           Newly instantiated object of Model class
   */

  protected function instantiatePluginModel(string $pmodel, string $path) {
    $pluginName = StringUtilities::pluginPlugin($pmodel);
    $pluginModel = StringUtilities::pluginModel($pmodel);

    // First check that the requested plugin is actually enabled.
    // We can use Cake's check here since we would have loaded the plugin
    // in Application.php already if it were enabled.

    if(!\Cake\Core\Plugin::isLoaded($pluginName)) {
      throw new \InvalidArgumentException(__d('error', 'Plugins.inactive', [$pluginName]));
    }

    // Next try to instantiate the model. Since instantiatePluginModel() is called for
    // models that do not represent Cake Tables, we can't use the TableLocator here,
    // we just use plain PHP "new".

    $pluginClassName = "\\" . $pluginName . $path . "\\" . $pluginModel;
    $pClass = new $pluginClassName();

    return $pClass;
  }

  /**
   * Determine if a Registry Plugin is in use, specifically if an Entry Point Model
   * from the requested Plugin is in a configuration object for this Pluggable Model.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $plugin Plugin to examine
   * @return ResultSet      Set of configuration objects in use
   */

  public function pluginInUse(string $plugin): ResultSet {
    return $this->find()
                ->where(['plugin LIKE' => $plugin . ".%"])
                ->all();
  }

  /**
   * Obtain the Plugin Model from an entity ID.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int    $id       Entity ID
   * @param  array  $options  Options, as supported by get()
   */

  public function pluginModelForEntityId(int $id, array $options=[]) {
    $entity = $this->get($id, ...$options);
    $pModel = StringUtilities::pluginModel($entity->plugin);

    return $this->$pModel;
  }
  
  /**
   * Set up hasMany relations for instantiated plugin models.
   * 
   * @since  COmanage Registry v5.0.0
   */

  protected function setPluginRelations() {
    // We originally queried the configurations for the pluggable model to see which
    // plugins were in use, but that doesn't work when cloning, when the Target CO
    // is empty and has no active plugins. The alternate approach is to look at each
    // Plugin and query it for available plugins, and it turns out we already have
    // utility functions that will do that for us...

    // Under certain circumstances (eg: CloneCommand) we may not be using the
    // default datasource
    $datasource = $this->getConnection()->configName();

    $Plugins = TableUtilities::getTableWithDataSource(
      tableName: "Plugins",
      connectionName: $datasource
    );

    $models = $Plugins->getActivePluginModels($this->getPluggableModelType());

    foreach($models as $plugin) {
      // Derive association alias from "Plugin.Model"
      [$pluginName, $modelAlias] = explode('.', $plugin, 2);

      if($datasource != 'default') {
        // Add the aliasPrefix

        $modelAlias = Inflector::camelize($datasource) . $modelAlias;
      }

      if ($this->associations()->has($modelAlias)) {
        // Association already defined elsewhere; don't rebind
        $this->llog('debug', "Association '{$modelAlias}' already exists, skipping plugin relation '{$plugin}'");
        continue;
      }

      // In general, a model with a "plugin" field has a 1-1 relation
      // with the instantiated plugin configuration. eg: One instance
      // of a Server has exactly one SqlServer associated with it.
      // Bind by alias and explicitly set the className.

      // We also explicitly set the foreign key because creating a table alias (as for example
      // done by CloneCommand) will create a default foreign key of the alias (eg: target_server_id)
      // instead of the physical table name.

      $assn = $this->hasOne($modelAlias)
        ->setClassName($plugin)
        ->setDependent(true)
        ->setForeignKey(StringUtilities::tableToForeignKey($this))
        ->setCascadeCallbacks(true);
      
      if($datasource != 'default') {
        // We can't just set the connection on getTarget or we'll clobber the datasource.
        // We have to create a new Table attached to the alternate datasource.
        // (Strictly speaking we don't need to test for default, in which case we'd just
        // re-set the same target table that hasOne would have used by default.)

        $targetTable = TableUtilities::getTableWithDataSource(
          // aliasPrefix: Inflector::camelize($datasource),  // XXX was Remote?`
          tableName: $plugin,
          connectionName: $datasource
        );

        $assn->setTarget($targetTable);
      }

      // Cache the list of entry points that we found (avoid duplicates)
      if (!in_array($plugin, $this->_pluginModels, true)) {
        $this->_pluginModels[] = $plugin;
      }
    }

    // isArtifactTable() might not be the exact right test here...
    // for now, we only want to exclude Jobs (since there's nothing
    // to configure) but this may change. Also, Traffic Detours don't
    // have a primary link.

    if(!$this->isArtifactTable() 
       && method_exists($this, 'setAllowLookupPrimaryLink')) {
      $this->setAllowLookupPrimaryLink(['configure']);
    }
  }
}
