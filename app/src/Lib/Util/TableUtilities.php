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
   * We calculate the model name from the primary link, the primary link value is the id
   * of the record. We use these to traverse backwards to all the records associations
   * Then we return a list where the keys are the model names and the values are the ids
   *
   * @param   string  $primaryLinkKey
   * @param   int     $primaryLinkValue
   * @param   array   $results
   *
   * @return void
   * @since  COmanage Registry v5.0.0
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
    // Get the Record from the database
    $resp = $ModelTable->find()
      ->where(['id' => $primaryLinkValue])
      ->first()
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

  /**
   * With a model name and the id know we return a list where the
   * keys are the model names and the values are the ids
   *
   * @param   string  $modelName
   * @param   int     $id
   * @param   array   $results
   *
   * @return void
   * @since  COmanage Registry v5.0.0
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