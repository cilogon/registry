<?php
/**
 * COmanage Registry Table Utilities
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

namespace App\Lib\Util;

use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Inflector;

class TableUtilities {
  /**
   * Dynamically obtain a Table model from the Table Registry based on the requested
   * datasource connection name. If the requested connection is 'default' the Table alias
   * will be the requested Table name (eg "People" for "People"). For any other connection,
   * the connection name will be CamelCased and prefixed to create the Table alias
   * (eg "RemotePeople" for "People"). For Plugins, the Plugin name will be removed and
   * the Plugin model used as described (eg "RemoteWidgets" for "MyPlugin.Widgets").
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string $tableName      Table name, in the usual format (including Plugin.Models)
   * @param  string $connectionName Name of database connection to use
   * @param  array  $options        Additional options to pass to TableLocator
   * @return Table
   */

  public static function getTableWithDataSource(
    string $tableName,
    string $connectionName,
    array  $options=[]
  ): Table {
    if($connectionName == 'default') {
      // We can simply pass through the request

      return self::getTableFromRegistry($tableName, $options);
    }

    // Start with the prefix (eg: "Remote")
    $prefix = Inflector::camelize($connectionName);
    $modelName = $prefix;
    $pluginName = null;

    if(str_contains($tableName, '.')) {
      // (eg) SqlServers
      $modelName .= StringUtilities::PluginModel($tableName);
      $pluginName = StringUtilities::PluginPlugin($tableName);
    } else {
      // eg, "People"
      $modelName .= $tableName;
    }

    // In order to prevent infinite recursion, first see if we have the requested table already
    $Locator = TableRegistry::getTableLocator();
    
    if($Locator->exists($modelName)) {
      return $Locator->get($modelName);
    }

    $mergedOptions = $options;

    $mergedOptions['alias'] = $modelName;
    $mergedOptions['className'] = $tableName;
    $mergedOptions['connectionName'] = $connectionName;

    $m = self::getTableFromRegistry($modelName, $mergedOptions);

    // Relabel associations with $prefix. Some will already be correctly set up, in particular
    // dynamic plugin associations created via PluggableModelTrait::setPluginRelations, so we
    // check for and skip those. (We're actually doing something similar to that code, here.)

    $assns = $m->associations();

    foreach($assns as $a) {
      // Each association will have the requested table ($tableName) as its source side,
      // so we just need to check that the target is also using $connectionName.

      $target = $a->getTarget();

      if($target->getConnection()->configName() != $connectionName) {
        // Association type: BelongsTo, HasMany, HasOne; we lowercase the initial letter
        // to match the Table function name.
        $r = new \ReflectionClass($a);
        $aType = Inflector::variable($r->getShortName());

        // The alias for the target as defined in the associations, eg "Identifiers"
        // or "PipelineMatchTypes", prefixed by the datasource alias (eg "RemoteIdentifiers"
        // or "RemotePipelineMatchTypes").
        $targetAlias = $prefix . $target->getAlias();

        // The class name we are trying to instantiate. We need to handle plugins here.
        // If $pluginName is set, we'll assume HasMany and HasOne relations are within
        // the same plugin. This must be the underlying class name ("Identifiers" or "Types")
        // so Cake can find it.
        $className = Inflector::camelize($target->getTable());

        if($pluginName && ($aType == 'hasMany' || $aType == 'hasOne')) {
          $className = $pluginName . "." . $className;
        }

        // We create a new association for the requested connection name.
        // Cake doesn't provide a mechanism to drop the old association, so we just ignore it.

        // Don't recurse here!
        $aTargetTable = self::getTableFromRegistry(
          alias: $targetAlias,
          options: [ 
            'alias' => $targetAlias,
            'className' => $className,
            'connectionName' => $connectionName
          ]
        );

        if(!$aTargetTable->hasAssociation($targetAlias)) {
          $m->$aType($targetAlias)
            ->setClassName($className)
            // For the relation (eg) RemoteMatchServers hasMany RemoteMatchServerAttributes
            // we need to set the foreign key to match_server_id (ie what the data model has
            // for match_server_attributes to fk back to match_servers)
            ->setForeignKey(StringUtilities::classNameToForeignKey(StringUtilities::pluginModel($tableName)))
            ->setCascadeCallbacks(true)
            ->setTarget($aTargetTable);
            // Unlike PluggableTrait we don't setDependent(), it's not clear if we need to...
        }
      }
    }

    return $m;
  }

  /**
   * Dynamically create a Table model via the Table Registry.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $alias    Table alias
   * @param  array  $options  Table options
   * @return Table            Table
   */

  public static function getTableFromRegistry(string $alias, array $options): Table {
    // When creating a dynamic table, Cake will throw an Exception if get() is called 
    // with $options after the table is first instantiated. While this is probably a
    // preventative measure to avoid issues with the same table being instantiated
    // twice with different options, in our case we typically try to create the table
    // a second time when the same code is called again -- eg when SqlProvisioner is
    // called on a second entity, for example via ProvisionerJob.

    $Locator = TableRegistry::getTableLocator();

    if($Locator->exists($alias)) {
      return $Locator->get($alias);
    } else {
      return $Locator->get($alias, $options);
    }
  }
  
  /**
   * Take an array of associations (as used for contains()) and normalize them
   * for easier handling. Specifically, all relations will always have a child array,
   * though it may be an empty array.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  array  $related  Array of associations in contain() format
   * @return array            Array in normalized format.
   */

  public static function normalizeAssociationArray(array $related): array {
    $ret = [];

    foreach($related as $k => $v) {
      if(is_int($k)) {
        // Simple relation, give it an empty set of related children
        $ret[$v] = [];
      } elseif(is_array($v)) {
        // Pass through the array, but we need to recurse over its elements
        $ret[$k] = self::normalizeAssociationArray($v);
      }
    }

    return $ret;
  }

  /**
   * Traverse backwards through model associations starting from a primary link.
   *
   * Calculates model name from primary link and traverses backwards through all record
   * associations. Returns list where keys are model names and values are record IDs.
   *
   * @param string $primaryLinkKey Primary link key name
   * @param int $primaryLinkValue ID value of the primary link record
   * @param array $results Reference to array to store results
   * @param string|null $primaryLinkClassName Optional override for model class name
   * @return void Results stored in $results parameter
   * @since COmanage Registry v5.0.0
   */
  public static function treeTraversalFromPrimaryLink(
    string $primaryLinkKey,
    int $primaryLinkValue,
    array &$results,
    ?string $primaryLinkClassName = null
  ): void
  {
    $db = ConnectionManager::get('default');
    // Create a schema collection.
    $collection = $db->getSchemaCollection();
    $listOfTables = $collection->listTables();

    $primaryLinkModelName = StringUtilities::foreignKeyToClassName(($primaryLinkKey));
    // Check if the table exists.
    // We can not handle

    // We need to save the id by its alias not the containing class
    $results[$primaryLinkModelName] = $primaryLinkValue;

    if ($primaryLinkClassName !== null) {
      $primaryLinkModelName = $primaryLinkClassName;
      $results[$primaryLinkModelName] = $primaryLinkValue;
    }

    // Get a table reference
    $ModelTable = TableRegistry::getTableLocator()->get($primaryLinkModelName);

    try {
      // Get the Record from the database
      $resp = $ModelTable->find()
        ->where(['id' => $primaryLinkValue])
        ->firstOrFail()
        ->toArray();

      // Find all the foreign keys and fetch the rest of the tree
      foreach($resp as $col => $val) {
        if (
          $val !== null
          && $col !== $primaryLinkKey
          && str_ends_with($col, '_id')
        ) {
          $fkModel = StringUtilities::foreignKeyToClassName(($col));
          $fk_table = Inflector::underscore($fkModel);
          if (\in_array($fk_table, $listOfTables, true)) {
            self::treeTraversalFromPrimaryLink($col, $val, $results);
          }
        }
      }
    }
    catch(\Exception $e) {
      // Because this code is trying to get the parent table from the foreign key name,
      // it doesn't work for foreign keys to plugin provided tables. (For example, when
      // trying to view an External Identity Source connected to a Pipeline that uses an
      // External Match Strategy configured for a Match Server), the edit view of the EIS 
      // no longer renders because at some point we run into a foreign key of match_server_id
      // and there's no way to resolve that to CoreServer::MatchServersTable since the
      // database doesn't know what plugin provides a table.

      // Note that $ModelTable _is_ created in this case, because Cake by default creates
      // a stub model if it can't find the corresponding model definition, so what actually
      // fails is the call to first(), and then the chain to toArray(). As a workaround,
      // we use firstOrFail() instead to cause an exception to be thrown, and then we ignore it.
    }
  }

  /**
   * Traverse backwards through model associations starting from model name and ID.
   *
   * Returns list where keys are model names and values are record IDs by traversing
   * through all associated records.
   *
   * @param string $modelName Name of the model to start from
   * @param int $id ID of the record to start from
   * @param array $results Reference to array to store results
   * @return void Results stored in $results parameter
   * @since COmanage Registry v5.0.0
   */
  public static function treeTraversalFromId(string $modelName, int $id, array &$results): void
  {
    $db = ConnectionManager::get('default');
    // Create a schema collection.
    $collection = $db->getSchemaCollection();
    $listOfTables = $collection->listTables();

    $results[$modelName] = $id;
    // Get a table reference
    $ModelTable = TableRegistry::getTableLocator()->get($modelName);
    // Get the Record from the database
    $resp = $ModelTable->find()
      ->where(['id' => $id])
      ->first()?->toArray();

    if ($resp !== null) {
      // Find all the foreign keys and fetch the rest of the tree
      foreach($resp as $col => $val) {
        if (
          $val !== null
          && $col !== $modelName
          && str_ends_with($col, '_id')
        ) {
          $fkModel = StringUtilities::foreignKeyToClassName(($col));
          $fk_table = Inflector::underscore($fkModel);
          if (\in_array($fk_table, $listOfTables, true)) {
            self::treeTraversalFromPrimaryLink($col, $val, $results);
          }
        }
      }
    }
  }
}