<?php
/**
 * COmanage Registry String Utilities
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

use Cake\ORM\TableRegistry;
use \Cake\Utility\Inflector;

class StringUtilities {
  /**
   * Determine the foreign key name to point to a Cake Class Name (eg: foo_id for Foo).
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $className  Class Name
   * @return string             Foreign key name
   */

  public static function classNameToForeignKey(string $className): string {
    return Inflector::underscore(Inflector::singularize($className)) . "_id";
  }

  /**
   * Construct the Column human-readable key
   *
   * @param   string              $modelsName           The name of the Model
   * @param   string              $c                    The name of the column
   * @param   \DateTimeZone|null  $tz                   The timezone
   * @param   boolean             $useCustomClMdlLabel  Whether to use a custom `Model.column` field entry or rely on the default
   *
   * @return string                               Column friendly name
   * @since  COmanage Registry v5.0.0
   */

  public static function columnKey(
    string $modelsName, 
    string $c,
    \DateTimeZone $tz=null,
    bool $useCustomClMdlLabel=false
  ): string {
    if(strpos($c, '_id', strlen($c)-3)) {
      $postfix = '';
      if($c === 'parent_id') {
        // This means we are working with a model that implements a Tree behavior
        // XXX If i add the parentheses before the name the the Inflecto::Humanize will not
        //     work as expected. Which means that
        //     this: parent_(cou)
        //     will become Parent (cou) and not Parent (Cou)
        //     because humanize looks for the first character after parentheses
        // $postfix = " ({$modelsName})";
        $postfix = " {$modelsName}";
      }

      // Key is of the form field_id, use .ct label instead
      $k = self::foreignKeyToClassName($c);

      return __d('controller', $k, [1])  . $postfix;
    }

    // Look for a model specific key first
    $label = __d('field', $modelsName.'.'.$c);

    if($label != $modelsName.'.'.$c && !$useCustomClMdlLabel) {
      return $label;
    }

    if($tz) {
      // If there is a timezone aware label, use that
      $label = __d('field', $c.'.tz', [$tz->getName()]);

      if($label != $c.'.tz') {
        return $label;
      }
    }

    // XXX for the case of eduPersonAffiliation names we could
    //     consider the Inflector solution. First underscore and then
    //     Humanize

    // Otherwise look for the general key
    $cfield = __d('field', $c);
    return ($cfield !== $c) ? $cfield : \Cake\Utility\Inflector::humanize($c);
  }

  /**
   * Determine the class basename of a Cake Entity.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity Entity
   * @return string         Entity Class Basename
   * @todo   Refactor existing code to use these calls (Standard/index.php, MVETrait, ReadOnlyTrait, TableMetaTrait, and ChangelogBehavior)
   */

  public static function entityToClassName($entity): string {
    // $classPath will be something like App\Model\Entity\Name, but we want to return "Names"
    $classPath = get_class($entity);

    return Inflector::pluralize(substr($classPath, strrpos($classPath, '\\')+1));
  }

  /**
   * Determine the foreign key name to point to a Cake Entity (eg: foo_id for a Foo object).
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity Entity
   * @return string         Foreign key name
   */

  public static function entityToForeignKey($entity): string {
    // $classPath will be something like App\Model\Entity\Name, but we want to return "name_id"
    $classPath = get_class($entity);

    return Inflector::underscore(Inflector::singularize(substr($classPath, strrpos($classPath, '\\')+1))) . "_id";
  }

  /**
   * Construct the title, supertitle and subtitle for a given Model and action
   *
   * - if the Entity is null then we construct the message ID by concatenating the modelPath and the action
   * - if the action is null then the message ID is the modelsName
   * - if the action is the Index View then the message ID is the modelsName + "others", which in this case is the plural of the name
   * - in all other cases the message id is constructed by the displayField. Either it is defined or dynamically
   *   constructed
   *
   * @param   Entity|null  $entity     Entity object
   * @param   string       $modelPath  The path of the Model, from core Models it is the Model Name. For plugins it is the Plugin.ModelName
   * @param   string|null  $action     Request Action
   * @param   string       $domain     The po file the message ID is located in
   *
   * @return array                 List of title, supertitle, subtitle
   */
  public static function entityAndActionToTitle($entity,
                                                string $modelPath,
                                                ?string $action,
                                                string $domain='operation'): array {
    $supertitle = '';
    $subtitle   = '';
    $title      = '';

    if($entity === null) {
      return [__d($domain, "{$modelPath}.{$action}"), '', ''];
    }

    $plugin = '';
    $modelsName = $modelPath;
    if(str_contains($modelPath, '.')) {
      [$plugin, $modelsName] = explode('.', $modelPath, 2);
    }

    $linkTable  = TableRegistry::getTableLocator()->get($modelPath);
    $msgId = "{$action}.a";
    $msgIdOverride = "{$action}.{$modelsName}.a";

    if(Inflector::singularize(self::entityToClassName($entity)) !== Inflector::singularize($modelsName)) {
      $linkTable  = TableRegistry::getTableLocator()->get(self::entityToClassName($entity));
      // if the modelPath and the action are equal then we skip the concatenation
      $msgId = $modelPath === $action ? $modelPath : "{$modelPath}.{$action}";
    }

    if($action === null) {
      return [__d('controller', $modelsName), '', ''];
    }

    // Index view
    if($action === 'index') {
      // 99 is the default for plural
      return [__d('controller', $modelsName, [99]), '', ''];
    }

    // Add/Edit/View
    // The MVEA Models have a entityId. The one from the parent model.
    // We need to have a condition for this and exclude it.
    if($entity->id !== null
       && $action !== 'add'
       && $action !== 'delete'
       && method_exists($linkTable, 'generateDisplayField')) {
      // We don't use a trait for this since each table will implement different logic

      $title = __d($domain, $msgIdOverride, $linkTable->generateDisplayField($entity));
      if ($msgIdOverride === $title) {
        $title = __d($domain, $msgId, $linkTable->generateDisplayField($entity));
      }
      $supertitle = $linkTable->generateDisplayField($entity);
      // Pass the display field also into subtitle for dealing with External IDs
      $subtitle = $linkTable->generateDisplayField($entity);
    } else {
      // Default view title is edit object display field
      $field = $linkTable->getDisplayField();

      if(!empty($entity->$field)) {
        $title = __d($domain, $msgIdOverride, $entity->$field);
        if($msgIdOverride === $title) {
          $title = __d($domain, $msgId, $entity->$field);
        }
      } else {
        $title = __d($domain, $msgId, __d('controller', $modelsName, [1]));
      }
    }

    return [$title, $supertitle, $subtitle];
  }

  /**
   * Determine the class name from a foreign key (eg: report_id -> Reports).
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $s Foreign Key name
   * @return string    Class name
   */

  public static function foreignKeyToClassName(string $s): string {
    return Inflector::camelize(Inflector::pluralize(substr($s, 0, strlen($s)-3)));
  }

  /**
   * Localize a controller name, accounting for plugins.
   *
   * @param   string       $controllerName  Name of controller to localize
   * @param   string|null  $pluginName      Plugin name, if appropriate
   * @param   bool         $plural          Whether to use plural localization
   *
   * @return string                 Localized text string
   * @since  COmanage Registry v5.0.0
   */
  
  public static function localizeController(string $controllerName, ?string $pluginName, bool $plural=false): string {
    if($pluginName) {
      // Localize via plugin
      return __d(Inflector::underscore($pluginName), 'controller.'.$controllerName, [$plural ? 99 : 1]);
    } else {
      // Standard localization

      return __d('controller', $controllerName, [$plural ? 99 : 1]);
    }
  }

  /**
   * Determine the model component of a Plugin path.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $s Plugin path, in Plugin.Model format.
   * @return string    Model name
   */
  
  public static function pluginModel(string $s): string {
    $bits = explode('.', $s, 2);

    return $bits[1];
  }

  /**
   * Determine the plugin component of a Plugin path.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string $s Plugin path, in Plugin.Model format.
   * @return string    Plugin name
   */
  
  public static function pluginPlugin(string $s): string {
    $bits = explode('.', $s, 2);

    return $bits[0];
  }

  /**
   * Convert a plugin name (in Plugin.Model format) to the field name it will be found
   * in as a related model to the Pluggable Entity (ie: $entity->my_plugin).
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string $plugin   Plugin path, in Plugin.Model format
   * @return string           Plugin field name, in underscore_format
   */

  public static function pluginToEntityField(string $plugin): string {
    return Inflector::singularize(Inflector::underscore(self::pluginModel($plugin)));
  }

  /**
   * Determine the Entity name from a Table object.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Table $table Cake Table object
   * @return string       Entity name (eg: Report)
   */
  
  public static function tableToEntityName($table): string {
    $classPath = $table->getEntityClass();

    return substr($classPath, strrpos($classPath, '\\')+1);
  }

  /**
   * Determine the foreign key name to point to a Cake Entity (eg: foo_id for FooTable).
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Table  $table  Table
   * @return string         Foreign key name
   */

  public static function tableToForeignKey($table): string {
    // $classPath will be something like App\Model\Entity\Name, but we want to return "name_id"
    $classPath = $table->getEntityClass();

    return Inflector::underscore(Inflector::singularize(substr($classPath, strrpos($classPath, '\\')+1))) . "_id";
  }

  // The following two utilities provide base64 encoding and decoding for
  // strings that might contain special characters that could interfere with
  // URLs. base64 can generate reserved characters, so we handle those specially
  // according to common (but not standardized) conventions. See CO-1667 and
  // https://stackoverflow.com/questions/1374753/passing-base64-encoded-strings-in-url
  // The mapping we use is the same as the YUI library. RFC 4648 base64url is
  // another option, but strangely doesn't map the padding character (=).
  
  /**
   * base64 decode a string.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $s String to decode
   * @return string    Decoded string
   */
  
  public static function urlbase64decode(string $s): string {
    return !empty($s)
           ? base64_decode(str_replace(array(".", "_", "-"),
                                       array("+", "/", "="),
                                       $s))
           : "";
  } 
  
  /**
   * base64 encode a string.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $s String to encode
   * @return string    Encoded string
   */
  
  public static function urlbase64encode(string $s): string {
    return !empty($s)
           ? str_replace(array("+", "/", "="),
                         array(".", "_", "-"),
                         base64_encode($s))
           : "";
  }
}
