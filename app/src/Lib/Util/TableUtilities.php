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
    string $primaryLinkClassName = null
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