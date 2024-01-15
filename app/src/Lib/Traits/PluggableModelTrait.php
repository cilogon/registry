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
use Cake\Utility\Inflector;

use App\Lib\Util\StringUtilities;

trait PluggableModelTrait {
  // The set of plugin entry point models used in configurations for this model
  protected $_pluginModels = [];

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
    $entity = $this->get($id, $options);
    $pModel = StringUtilities::pluginModel($entity->plugin);

    return $this->$pModel;
  }
  
  /**
   * Set up hasMany relations for instantiated plugin models.
   * 
   * @since  COmanage Registry v5.0.0
   */

  protected function setPluginRelations() {
    // To determine which plugin models are instantiated, we'll query the configuration
    // for this pluggable model. We only need to do this once per plugin model, not
    // once per instantiation.

    $models = $this->find()
                   ->select(['id', 'plugin'])
                   ->distinct(['plugin'])
                   ->all();
    
    foreach($models as $m) {
      if(empty($m->plugin) || !strstr($m->plugin, '.')) {
        // This plugin is not valid. We could filter this in the find() using a
        // where() clause, but checking here allows us to emit a warning.

        $this->llog('error', "Ignoring invalid plugin found in " . $this->getTable() . " record " . $m->id);
        continue;
      }

      // In general, a model with a "plugin" field has a 1-1 relation
      // with the instantiated plugin configuration. eg: One instance
      // of a Server has exactly one SqlServer associated with it.
      $this->hasOne($m->plugin)
           ->setDependent(true)
           ->setCascadeCallbacks(true);
      
      // Cache the list of entry points that we found
      $this->_pluginModels[] = $m->plugin;
    }

    // isArtifactTable() might not be the exact right test here...
    // for now, we only want to exclude Jobs (since there's nothing
    // to configure) but this may change.

    if(!$this->isArtifactTable()) {
      $this->setAllowLookupPrimaryLink(['configure']);
    }
  }
}
