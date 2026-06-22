<?php
/**
 * COmanage Registry Plugin Table
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

use Cake\Log\Log;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;

use App\Lib\Enum\PluginLocationEnum;
use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Util\SchemaManager;

class PluginsTable extends Table {
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;
  
  // Plugin paths
  protected $paths = [
    PluginLocationEnum::Core      => [
      'path'    => ROOT . DS . 'plugins',
      'status'  => SuspendableStatusEnum::Active
    ],
    PluginLocationEnum::Available => [
      'path'    => ROOT . DS . 'availableplugins',
      'status'  => SuspendableStatusEnum::Suspended
    ],
    PluginLocationEnum::Local     => [
      'path'    => LOCAL . DS . 'plugins',
      'status'  => SuspendableStatusEnum::Suspended
    ]
  ];

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // $this->addBehavior('Changelog');
    // $this->addBehavior('Log');
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Timestamp');
 
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Metadata);
    
    $this->setDisplayField('plugin');
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'activate'    => ['platformAdmin'],
        'applySchema' => ['platformAdmin'],
        'deactivate'  => ['platformAdmin'],
        'delete'      => false,
        'edit'        => false,
        'view'        => false
      ],
      // Actions that are permitted on readonly entities (besides view)
      'readOnly' =>    ['applySchema'],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        // Plugins are added automatically
        'add' =>       false,
        'index' =>     ['platformAdmin']
      ]
    ]);
  }
  
  /**
   * Activate a plugin.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int $id  Plugin ID
   * @return bool     True on success
   */

  public function activate(int $id): bool {
    $plugin = $this->get($id);

    if($plugin->canActivate()) {
      $plugin->status = SuspendableStatusEnum::Active;
      $plugin->comment = __d('information', 'plugin.active');
      $this->saveOrFail($plugin);

      // AR-Plugin-6 If a plugin is activated, apply its schema. Note it's possible
      // the schema was previously applied, but that's OK, this will just bring it
      // up to date (which might imply no changes).

      $pSchemaConfig = $this->getPluginSchema($plugin);

      if($pSchemaConfig) {
        $SchemaManager = new SchemaManager();

        $SchemaManager->applySchemaObject($pSchemaConfig);
      }
    }

    return true;
  }

  /**
   * Apply the database schema for a plugin.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int $id  Plugin ID
   * @return bool     True on success
   */

  public function applySchema(int $id): bool {
    $plugin = $this->get($id);

    $pSchemaConfig = $this->getPluginSchema($plugin);

    if($pSchemaConfig) {
      $SchemaManager = new SchemaManager();

      $SchemaManager->applySchemaObject($pSchemaConfig);
    } else {
      $this->llog('debug', "Plugin $plugin->plugin does not define a database schema");
    }

    return true;
  }

  /**
   * Define business rules.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    // AR-Plugin-8 A Plugin cannot be suspended or deleted if it is in use (referenced in 
    // a configuration object). (Strictly speaking we don't need to do this for create/add
    // since nothing should reference the pluing at that point.)
    $rules->addUpdate([$this, 'ruleInUse'],
                      'inUse',
                      ['errorField' => 'status']);
    
    $rules->addDelete([$this, 'ruleInUse'],
                      'inUse',
                      ['errorField' => 'status']);
    
    return $rules;
  }
  
  /**
   * Deactivate a plugin.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int $id  Plugin ID
   * @return bool     True on success
   */

  public function deactivate(int $id): bool {
    $plugin = $this->get($id);

    if($plugin->canDeactivate()) {
      $plugin->status = SuspendableStatusEnum::Suspended;
      $plugin->comment = __d('information', 'plugin.inactive');
      $this->saveOrFail($plugin);

      // AR-Plugin-7 If a plugin is suspended, its associated database schema is NOT removed
    }

    return true;
  }

  /**
   * Find the set of active Plugins.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Query $query Cake Query object
   * @return Query $query Cake Query object
   */

  public function findActive(Query $query): Query {
    // We might be called (eg) RemotePlugins via CloneCommand
    return $query->where([$this->getAlias().'.status' => SuspendableStatusEnum::Active])
                 ->orderBy(['plugin' => 'ASC']);
  }

  /**
   * Obtain the set of active plugin models of a given type.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $type Plugin type
   * @return array        Array of plugin models implementing $type
   */

  public function getActivePluginModels(string $type): array {
    // First get the list of enabled plugins

    $plugins = $this->find('active')->all();

    $active = [];

    foreach($plugins as $p) {
      // Interrogate each plugin for its models implementing $type
      $active = array_merge($active, 
                            array_map(function($v) use ($p) { 
                              return $p->plugin . "." . $v;
                            }, $this->getPluginModelsByType($p, $type)));
    }

    // For use in populating the select, we want the keys and values to be the same
    return array_combine($active, $active);
  }

  /**
   * Read the value for a configuration key for a plugin, which must be Active.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $plugin Plugin name
   * @param  string $key    Configuration key
   * @param  array          Array of configuration information
   */

  public function getPluginConfig(string $plugin, string $key) {
    // While most calls to this table accept a plugin object, this one takes
    // a string to simplify code that needs a value out of plugin.json.
    $pObj = $this->find()
                 ->where([
                   'plugin'  => $plugin,
                   'status'  => SuspendableStatusEnum::Active
                 ])
                 ->firstOrFail();

    return $this->readPluginConfig($pObj, $key);
  }

  /**
   * Obtain the Entry Point Models implemented by a plugin for a specific plugin type.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Plugin $plugin Cake Plugin object
   * @param  string $type   Plugin type
   * @return array          Array of Entry Point Models
   */
  
  public function getPluginModelsByType(\App\Model\Entity\Plugin $plugin, string $type): array {
    // Plugins can implement multiple plugin types, and multiple "entry point" models
    // into each type. The index for this configuration is maintained in the plugin's
    // src/config/plugin.json file.

    $pConfig = $this->readPluginConfig(plugin: $plugin, key: "types");

    if(!empty($pConfig)) {
      if(isset($pConfig->$type) && is_array($pConfig->$type)) {
        return $pConfig->$type;
      } else {
        // This plugin does not implement the requested type. Don't log here since it'll
        // cause noise and confusion in the logs.
        // $this->llog('debug', "Plugin $plugin->plugin does not have a valid types configuration");
      }
    } else {
      $this->llog('debug', "Plugin $plugin->plugin does not have a plugin.json file");
    }

    return [];
  }

  /**
   * Obtain the database schema defined for a plugin.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Plugin $plugin Cake Plugin object
   * @return object         Database schema in object form
   */

  public function getPluginSchema(\App\Model\Entity\Plugin $plugin): ?object {
    return $this->readPluginConfig($plugin, 'schema');
  }

  /**
   * Determine the filesystem path to a file within a plugin.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Plugin $plugin Plugin object
   * @param  string $file   File name
   * @return string         Path to file
   * @throws InvalidArgumentException
   */

  public function pluginPath(\App\Model\Entity\Plugin $plugin, string $file): string {
    $fileName = $this->paths[$plugin->location]['path'] . DS . $plugin->plugin . DS . $file;

    // "tests/" is optional in deployed environments. Return the computed path
    // without treating absence as an error so callers can probe as needed.
    if($file === 'tests' || str_starts_with($file, 'tests' . DS)) {
      return $fileName;
    }

    if(is_readable($fileName)) {
      // This is the plugin we're looking for
      $this->llog('debug', "Found plugin $plugin->plugin in $plugin->location directory");

      return $fileName;
    }

    $this->llog('error', "Could not find $fileName");
    
    throw new \InvalidArgumentException("Could not find $fileName");
  }

  /**
   * Read the plugin configuration.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Plugin $plugin Plugin object
   * @param  string $key    Configuration key
   * @return object         Configuration, as a parsed json object
   */

  protected function readPluginConfig(\App\Model\Entity\Plugin $plugin, string $key): ?object {
    $cfg = $this->pluginPath($plugin, 'config' . DS . 'plugin.json');;

    $json = file_get_contents($cfg);

    if(!empty($json)) {
      $jcfg = json_decode($json);

      if(isset($jcfg->$key)) {
        return $jcfg->$key;
      }
    }

    return null;
  }

  /**
   * Application Rule to determine if the current entity is in use (is referenced
   * by a configuration object).
   *
   * @param   Entity  $entity   Entity to be validated
   * @param   array   $options  Application rule options
   *
   * @return string|bool true if the Rule check passes, false otherwise
   * @since  COmanage Registry v5.0.0
   */

  public function ruleInUse($entity, array $options): string|bool {
    // This rule only applies if the Plugin was suspended.
    if(!$entity->isDirty('status') || $entity->status != SuspendableStatusEnum::Suspended) {
      return true;
    }

    // $entity->plugin is the physical plugin directory on the filesystem
    // (the Registry Plugin), which can implement multiple Entry Points,
    // each of which can be a different type. First, pull that configuration.

    $pConfig = $this->readPluginConfig(plugin: $entity, key: "types");

    // We now need to query each Pluggable Model ("ReportsTable" for plugins
    // of type "report") to see if this Plugin is in use for that Model.
    // If any Pluggable Model returns true, we fail validation.

    foreach($pConfig as $type => $entryPoints) {
      if(!empty($entryPoints)) {
        // Convert $type to a table class (eg: "report" to "Reports")
        $tableName = Inflector::pluralize(Inflector::classify($type));
        $table = TableRegistry::getTableLocator()->get($tableName);

        // This rule only applies to _configuration_ objects, so (eg) Jobs
        // (which are artifacts) can continue to reference a Plugin after
        // it has been suspended. Note that if a Plugin is polymorphic, this
        // rule still applies to the Configuration based Entry Point Models.
        if($table->isConfigurationTable()) {
          // We don't actually need $entryPoints here, we just wanted to make sure
          // the plugin implements at least one Entry Point for this $type.
          $r = $table->pluginInUse($entity->plugin);

          if(!empty($r) && count($r) > 0) {
            // There could be other plugin types in use, but that's probably the
            // exception and anyway just returning a single error will be sufficient
            // for now

            $displayField = $table->getDisplayField();

            return __d('error', 'Plugins.inuse', [count($r), $type, $r->first()->$displayField, $r->first()->co_id]);
          }
        }
      }
    }

    return true;
  }
  
  /**
   * Examine the available set of plugins and update the global Plugins table appropriately.
   * 
   * @since  COmanage Registry v5.0.0
   */

  public function syncPluginRegistry() {
    // Determine which plugins are on the filesystem in the various supported locations

    // The plugins we've found
    $plugins = [
      PluginLocationEnum::Core      => [],
      PluginLocationEnum::Available => [],
      PluginLocationEnum::Local     => []
    ];

    $pluginIndex = [];

    // First pull the set of available plugins

    foreach($this->paths as $t => $cfg) {
      if(!file_exists($cfg['path'])) {
        continue;
      }

      $dh = opendir($cfg['path']);

      while(($d = readdir($dh)) !== false) {
        if($d == "." || $d == ".." || $d == ".DS_Store") {
          continue;
        }

        $plugins[$t][] = $d;

        if(empty($pluginIndex[$d])) {
          // We want the first path we find for a given plugin so as not to violate AR-Plugin-5
          $pluginIndex[$d] = $t;
        }
      }

      closedir($dh);
    }

    // Pull our current Plugin configuration

    $registeredIndex = [];

    $registered = $this->find()->all();

    // Create an array of the already registered plugins
    foreach($registered as $rp) {
      $registeredIndex[$rp->plugin] = $rp;
    }

    // Insert rows for any plugin not currently in the Registry.
    // Core plugins are inserted as active, others as suspended.

    $newPlugins = [];

    foreach(array_keys($plugins) as $pluginType) {
      foreach($plugins[$pluginType] as $p) {
        if(!isset($registeredIndex[$p])) {
          // This is a new plugin
          $obj = $this->newEntity([
            'plugin'    => $p,
            'location'  => $pluginType,
            'status'    => $this->paths[$pluginType]['status'],
            'comment'   => __d('information', 
                               $this->paths[$pluginType]['status'] == SuspendableStatusEnum::Active
                               ? 'plugin.active.only'
                               : 'plugin.inactive')
          ]);

          $this->saveOrFail($obj);
        } elseif($registeredIndex[$p]->location != $pluginIndex[$p]) {
          // The plugin location moved. This won't typically happen, but might
          // if a developer moves a plugin around.

          $rp = $registeredIndex[$p];

          if($rp->location == PluginLocationEnum::Core) {
            // If the old location was core, update the comment but leave the plugin as active
            $rp->comment = __d('information', 'plugin.active');
          } elseif($pluginIndex[$p] == PluginLocationEnum::Core) {
            // If the new location is core, make sure the plugin is active
            $rp->status = SuspendableStatusEnum::Active;
            $rp->comment = __d('information', 'plugin.active.only');
          }

          $rp->location = $pluginIndex[$p];

          $this->saveOrFail($rp);
        }
      }
    }

    // Remove rows for any plugin that no longer exists on disk
    foreach($registered as $rp) {
      if(!isset($pluginIndex[$rp->plugin])) {
        $this->deleteOrFail($rp);
      }
    }
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
    
    $this->registerStringValidation($validator, $schema, 'plugin', true);
    
    $validator->add('location', [
      'content' => ['rule' => ['inList', PluginLocationEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('location');

    $validator->add('status', [
      'content' => ['rule' => ['inList', SuspendableStatusEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('status');

    $this->registerStringValidation($validator, $schema, 'comment', false);
    
    return $validator; 
  }
}