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

use Cake\Core\Plugin;
use Cake\ORM\TableRegistry;
use \Cake\Utility\Inflector;

class StringUtilities {
  /**
   * Converts a token by adding dashes for improved readability.
   *
   * @since  COmanage Registry v5.2.0
   * @param  string $token The token to be formatted
   * @param  int    $jump  Characters to skip before adding a dash
   * @return string        The formatted token with dashes
   */

  public static function addDashesToToken(string $token, int $jump = 4): string {
    // Insert some dashes to improve readability
    $dtoken = '';

    for($i = 0, $iMax = strlen($token); $i < $iMax; $i++) {
      $dtoken .= $token[$i];

      if((($i + 1) % $jump == 0)
        && ($i + 1 < strlen($token))) {
        $dtoken .= '-';
      }
    }

    return $dtoken;
  }


  /**
   * Convert empty or whitespace-only strings to null.
   * Trims the input string and returns null if empty after trimming.
   *
   * @param string|null $s String to process
   * @return string|null    Trimmed string or null if empty
   * @since  COmanage Registry v5.2.0
   */
  public static function blankToNull(?string $s): ?string {
    if ($s === null) {
      return null;
    }
    $t = trim($s);
    return $t === '' ? null : $t;
  }

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
    ?\DateTimeZone $tz=null,
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
    return ($cfield !== $c) ? $cfield : Inflector::humanize($c);
  }

    /**
   * Convert a database column name to an auto view variable name
   *
   * @param string $column Database column name to convert
   * @return string Converted view variable name
   * @since  COmanage Registry v5.2.0
   */
  public static function columnToAutoViewVar(string $column): string {
    // strip trailing _type_id if present, else _id
    $base = preg_replace('/_type_id$/', '', $column);
    $base = preg_replace('/_id$/', '', $base);

    // if it originally was *_type_id, we want “...Types”
    $isType = str_ends_with($column, '_type_id');

    // convert snake_case to camelCase
    $camel = Inflector::variable($base); // eg email_address -> emailAddress

    if ($isType) {
      // ensure a plural sense by appending “Types” (matches repo usage)
      return $camel . 'Types';
    }

    // default pluralization
    return Inflector::variable(Inflector::pluralize($base));
  }

  /**
   * Determines the translation domain for a plugin
   *
   * @param string|null $plugin Plugin name
   * @return string|null Translation domain
   * @since  COmanage Registry v5.2.0
   */
  public static function pluginToTextDomain(?string $plugin): ?string
  {
    if (empty($plugin)) {
      return null;
    }
    return Inflector::underscore($plugin);
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

    return self::classPathClassName($classPath);
  }

  /**
   * Extracts and pluralizes the class name from a fully qualified class path.
   *
   * @param string $classPath Fully qualified class path (eg: App\Model\Entity\Name)
   * @return string           Pluralized class name (eg: Names)
   * @since  COmanage Registry v5.2.0
   */
  public static function classPathClassName(string $classPath): string {
    return Inflector::pluralize(substr($classPath, strrpos($classPath, '\\')+1));
  }

  /**
   * Determine the foreign key name to point to a Cake Entity (eg: foo_id for a Foo object).
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity $entity Entity
   * @return string         Foreign key name
   * @todo   Merge with entityToPluginClassName
   */

  public static function entityToForeignKey($entity): string {
    // $classPath will be something like App\Model\Entity\Name, but we want to return "name_id"
    $classPath = get_class($entity);

    return Inflector::underscore(Inflector::singularize(substr($classPath, strrpos($classPath, '\\')+1))) . "_id";
  }

  /**
   * Determine the class basename of a Cake Entity.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Entity $entity Entity
   * @return string         Entity Class Basename, potentially in Plugin notation (Plugin.Model)
   * @todo   Merge with entityToClassName (some code that calls that function can't handle plugin notation)
   */

  public static function entityToPluginClassName($entity): string {
    // $classPath will be something like App\Model\Entity\Name, but we want to return "Names".
    // We also support plugins, if the first component is _not_ App, we'll prefix it.

    $bits = explode("\\", get_class($entity));

    $model = Inflector::pluralize($bits[3]);

    if($bits[0] == 'App') {
      return $model;
    } else {
      // Plugin
      return $bits[0] . "." . $model;
    }
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
   * @param   Entity|null  $entity         Entity object
   * @param   string|null  $modelPath      The path of the Model, from core Models it is the Model Name. For plugins it is the Plugin.ModelName
   * @param   string|null  $action         Request Action
   * @param   string       $domain         The po file the message ID is located in
   *
   * @return array                 List of title, supertitle, subtitle
   */
  public static function entityAndActionToTitle($entity,
                                                ?string $modelPath,
                                                ?string $action,
                                                string $domain = 'operation'): array {

    if($entity === null && $modelPath === null) {
      return [__d($domain, "$action", [99]), '', ''];
    }

    // Initialize return slots
    $supertitle = '';
    $subtitle   = '';
    $title      = '';

    // Extract plugin and model names: "Plugin.Model" → ["Plugin", "Model"]
    $plugin = '';
    $modelsName = $modelPath;
    if(str_contains($modelPath, '.')) {
      [$plugin, $modelsName] = explode('.', $modelPath, 2);
    }

    // Index view → use the controller plural form (token 99 convention)
    if($action === 'index') {
      if(!empty($plugin)) {
        $domain = StringUtilities::pluginToTextDomain($plugin);
        return [__d($domain, "controller.$modelsName", [99]), '', ''];
    }
      return [__d('controller', $modelsName, [99]), '', ''];
    }

     // Base table and default message IDs for translation
    $linkTable      = TableRegistry::getTableLocator()->get($modelPath);
    $msgId          = "{$action}.a";               // eg: "edit.a"
    $msgIdOverride  = "{$action}.{$modelsName}.a"; // eg: "edit.People.a"
    // If the model is a configuration table and a plugin, we render Configure instead of Edit
    if (method_exists($linkTable, 'isConfigurationTable')
      && $linkTable->isConfigurationTable()
      && str_contains($linkTable->getRegistryAlias(), '.')
    ) {
      $msgId          = "configure.a";               // eg: "edit.a"
      $msgIdOverride  = "configure.{$modelsName}.a"; // eg: "edit.People.a"
    }

    // If the entity actually belongs to a different model than the provided $modelsName,
    // switch to that table and adjust the default message id pattern accordingly.
    // This is necessary for TAB oriented views
    if(
      $entity !== null
      && Inflector::singularize(self::entityToClassName($entity)) !== Inflector::singularize($modelsName)
    ) {
      $linkTable  = TableRegistry::getTableLocator()->get(self::entityToClassName($entity));
      // If modelPath and action are equal, don’t concatenate (preserve legacy behavior)
      $msgId = $modelPath === $action ? $modelPath : "{$modelPath}.{$action}";
    }

    // No action → default to the controller label for the model (singular)
    if($action === null) {
      return [__d('controller', $modelsName), '', ''];
    }

    // Add/Edit/View
    // The MVEA Models have an entityId. The one from the parent model.
    // We need to have a condition for this and exclude it.
    $display = null;
    if (method_exists($linkTable, 'generateDisplayField')) {
      $display = $linkTable->generateDisplayField($entity);
    } else {
      $field = $linkTable->getDisplayField();
      $display = $entity->$field ?? null;
    }

    // Edit/View-like case for an existing entity with a usable display
    // Title: translate with override key first; if not found, fall back to default key.
    // Super/Sub titles: set to the display (needed for External IDs in UI).
    if (
      $entity?->id !== null &&
      $action !== 'add' &&
      $action !== 'delete' &&
      $display !== null
    ) {
      $title = self::translateWithOverride($domain, $msgIdOverride, $msgId, $display);
      $supertitle = $display;
      $subtitle   = $display;

      return [$title, $supertitle, $subtitle];
    }

    // Fallbacks:
    // - New entities (no id),
    // - Add/Delete actions,
    // - Or we simply lack a display.
    // Use the display if we have it; otherwise singular controller label for the model.
    $displayOrDefault = empty($display) ? __d('controller', $modelsName, [1]) : $display;
    $title = self::translateWithOverride($domain, $msgIdOverride, $msgId, $displayOrDefault);

    return [$title, $supertitle, $subtitle];
  }


  /**
   * Attempts to translate a message using an override key first, falling back to a default key if not found.
   *
   * @param string $domain Translation domain to use
   * @param string $overrideKey Primary translation key to try first
   * @param string $fallbackKey Fallback translation key if override not found
   * @param string|int $value Value to substitute in translation
   * @return string            Translated string using either override or fallback key
   * @since  COmanage Registry v5.2.0
   */
  private static function translateWithOverride(
    string $domain,
    string $overrideKey,
    string $fallbackKey,
    string|int $value
  ): string {
    $translated = __d($domain, $overrideKey, [$value]);
    return ($translated === $overrideKey)
      ? __d($domain, $fallbackKey, [$value])
      : $translated;
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
   * Determine the controller name from a foreign key (eg: report_id -> reports).
   *
   * @since  COmanage Registry v5.1.0
   * @param  string $s Foreign Key name
   * @return string    Class name
   */

  public static function foreignKeyToController(string $s): string {
    if($s === 'affiliation_type_id') {
      $s = 'type_id';
    }
    return Inflector::underscore(Inflector::pluralize(substr($s, 0, strlen($s)-3)));
  }


  /**
   * Get the fully qualified name by combining plugin and name with a dot separator.
   *
   * @param string|null $plugin Plugin name, or null if no plugin
   * @param string $name Base name to qualify
   * @return string Qualified name in format "Plugin.Name" or just "Name" if no plugin
   * @since COmanage Registry v5.2.0
   */
  public static function getQualifiedName(?string $plugin, string $name): string
  {
    return $plugin !== null && $plugin !== ''
      ? $plugin . '.' . $name
      : $name;
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
  public static function localizeController(string $controllerName, ?string $pluginName, bool $plural = false): string {
    // If "Plugin.Model" was passed, reduce to just "Model"
    if (str_contains($controllerName, '.')) {
      $controllerName = self::pluginModel($controllerName); // returns the part after the dot
    }

    if ($pluginName) {
      // Localize via plugin
      return __d(Inflector::underscore($pluginName), 'controller.' . $controllerName, [$plural ? 99 : 1]);
    }
    // Standard Localization
    return __d('controller', $controllerName, [$plural ? 99 : 1]);
  }

  /**
   * Qualifies a model path with its plugin name if not already qualified
   *
   * @param string $modelPath Model path to qualify
   * @param string|null $plugin Plugin name
   * @since  COmanage Registry v5.2.0
   * @return string Fully qualified model path
   */
  public static function qualifyModelPath(string $modelPath, ?string $plugin = null): string
  {
    if (empty($plugin) || str_starts_with($modelPath, $plugin . '.')) {
      return $modelPath;
    }
    return self::getQualifiedName($plugin, $modelPath);;
  }

  /**
   * Determine the model component of a Plugin path.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $s Plugin path, in Plugin.Model format.
   * @return string    Model name
   */

  public static function pluginModel(string $s): string {
    if (str_contains($s, '.')) {
      [, $model] = explode('.', $s, 2);
      return $model;
    }
    return $s;
  }


  /**
   * Infer a model name from a foreign key name (eg: report_id -> Reports) and attempt
   * to qualify it with a plugin by looking for a matching Table class.
   *
   * STRICT MODE:
   * This method now requires $requesterModel and will resolve via belongsTo association
   * metadata only. It will throw if the resolution is not possible or is ambiguous.
   *
   * @param string      $foreignKey       Foreign key name (eg: report_id)
   * @param string|null $requesterModel   Requester model path (eg "CoreModel" or "Plugin.CoreModel") (required)
   * @return string                       Model name, qualified as "Plugin.Model" when applicable
   * @since  COmanage Registry v5.2.0
   * @throws \InvalidArgumentException    When requester model is not provided or cannot be resolved
   * @throws \RuntimeException            When the association is missing/ambiguous or the target alias cannot be determined
   */
  public static function foreignKeyToQualifiedModelName(string $foreignKey, ?string $requesterModel = null): string
  {
    if ($requesterModel === null || trim($requesterModel) === '') {
      throw new \InvalidArgumentException(
        "foreignKeyToQualifiedModelName requires a requester model path to resolve '$foreignKey' canonically"
      );
    }

    $requesterTable = TableRegistry::getTableLocator()->get($requesterModel);

    $matches = [];

    foreach ($requesterTable->associations()->getByType(['belongsTo']) as $assoc) {
      if ($assoc->getForeignKey() === $foreignKey) {
        $matches[] = $assoc;
      }
    }

    if (count($matches) === 0) {
      // Provide a helpful diagnostic: what belongsTo FKs ARE defined on the requester?
      $defined = [];

      foreach ($requesterTable->associations()->getByType(['belongsTo']) as $assoc) {
        $target = $assoc->getTarget();
        $registryAlias = $target->getRegistryAlias();

        $defined[] = sprintf(
          "%s(fk=%s,class=%s,registry=%s)",
          $assoc->getName(),
          $assoc->getForeignKey(),
          $assoc->getClassName(),
          ($registryAlias !== '' ? $registryAlias : '?')
        );
      }

      $definedText = !empty($defined) ? implode('; ', $defined) : '(none)';

      throw new \RuntimeException(
        "No belongsTo association found on '$requesterModel' for foreign key '$foreignKey'. "
        . "Defined belongsTo associations: " . $definedText
      );
    }

    if (count($matches) > 1) {
      throw new \RuntimeException(
        "Ambiguous belongsTo associations on '$requesterModel' for foreign key '$foreignKey' (" . count($matches) . " matches)"
      );
    }

    $target = $matches[0]->getTarget();
    $alias = $target->getRegistryAlias();

    if ($alias !== '') {
      return $alias;
    }

    throw new \RuntimeException(
      "Unable to determine registry alias for belongsTo target of '$requesterModel.$foreignKey'"
    );
  }

  /**
   * Resolve an (unqualified) model name to a canonical registry alias ("Plugin.Model")
   * by inspecting the requester's association metadata.
   *
   * STRICT:
   * - Requires $requesterModel (no guessing, no plugin scanning).
   * - Throws if no association matches or if multiple associations match.
   *
   * Accepts:
   * - $modelName = "Plugin.Model" (already qualified): returned as-is after basic validation
   * - $modelName = "Models" (unqualified): resolved via associations on $requesterModel
   *
   * @param string      $modelName       Model name (eg "MatchServers" or "CoreServer.MatchServers")
   * @param string|null $requesterModel  Requester model path (eg "MatchServerAttributes" or "CoreServer.MatchServerAttributes")
   * @return string                      Canonical registry alias for the target table (eg "CoreServer.MatchServers")
   * @since  COmanage Registry v5.2.0
   * @throws \InvalidArgumentException
   * @throws \RuntimeException
   */
  public static function modelNameToQualifiedModelName(string $modelName, ?string $requesterModel = null): string
  {
    // Already qualified: validate it can be instantiated, then return it
    if (str_contains($modelName, '.')) {
      TableRegistry::getTableLocator()->get($modelName);
      return $modelName;
    }

    if ($requesterModel === null || trim($requesterModel) === '') {
      throw new \InvalidArgumentException(
        "modelNameToQualifiedModelName requires a requester model path to resolve '$modelName' canonically"
      );
    }

    // If the model being resolved is the same as the requester model, resolve to the requester.
    // This avoids requiring a self-association (which typically doesn't exist).
    if ($modelName === self::pluginModel($requesterModel)) {
      $requesterTable = TableRegistry::getTableLocator()->get($requesterModel);

      $alias = $requesterTable->getRegistryAlias();
      if ($alias !== '') {
        return $alias;
      }

      return $requesterModel;
    }

    // If the requester model has exactly one primary link, prefer that as the canonical parent
    // (for breadcrumb "parent hook" resolution), instead of traversing the association graph.
    $requesterTable = TableRegistry::getTableLocator()->get($requesterModel);

    if (
      method_exists($requesterTable, 'getPrimaryLinks')
      && method_exists($requesterTable, 'getPrimaryLinkTableName')
    ) {
      $primaryLinks = (array)$requesterTable->getPrimaryLinks();

      if (count($primaryLinks) === 1) {
        $plField = (string)$primaryLinks[0];
        $parentModelPath = (string)$requesterTable->getPrimaryLinkTableName($plField);

        // Only short-circuit when the caller is asking for the primary-link parent model.
        if ($parentModelPath !== '' && $modelName === self::pluginModel($parentModelPath)) {
          $parentTable = TableRegistry::getTableLocator()->get($parentModelPath);

          $alias = $parentTable->getRegistryAlias();
          if ($alias !== '') {
            return $alias;
          }

          return $parentModelPath;
        }
      }
    }

    $assocTypes = ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany'];

    // Prefer matching by association *name* first (eg "People" vs "ManagerPeople")
    // then fall back to target alias/className/registryAlias heuristics.
    $isMatch = static function ($assoc) use ($modelName): bool {
      if ($assoc->getName() === $modelName) {
        return true;
      }

      $target = $assoc->getTarget();

      if ($target->getAlias() === $modelName) {
        return true;
      }

      $className = (string)$assoc->getClassName();
      if ($className === $modelName || str_ends_with($className, '.' . $modelName)) {
        return true;
      }

      $ra = (string)$target->getRegistryAlias();
      if ($ra === $modelName || str_ends_with($ra, '.' . $modelName)) {
        return true;
      }

      return false;
    };

    // 1) Prefer direct associations
    $requesterTable = TableRegistry::getTableLocator()->get($requesterModel);
    $directMatches = [];

    foreach ($requesterTable->associations()->getByType($assocTypes) as $assoc) {
      if ($isMatch($assoc)) {
        $directMatches[] = $assoc;
      }
    }

    if (count($directMatches) === 1) {
      $target = $directMatches[0]->getTarget();
      $alias = $target->getRegistryAlias();

      if ($alias !== '') {
        return $alias;
      }

      throw new \RuntimeException(
        "Unable to determine registry alias for association target '$requesterModel -> $modelName'"
      );
    } elseif (count($directMatches) > 1) {
      // Disambiguation: prefer an association whose *association alias/name* exactly
      // matches the requested model name (eg "People" vs "ManagerPeople"/"SponsorPeople").
      $named = array_values(array_filter(
        $directMatches,
        static fn($a) => $a->getName() === $modelName
      ));

      if (count($named) === 1) {
        $target = $named[0]->getTarget();
        $alias = (string)$target->getRegistryAlias();

        if ($alias !== '') {
          return $alias;
        }

        throw new \RuntimeException(
          "Unable to determine registry alias for association target '$requesterModel -> $modelName'"
        );
      }

      throw new \RuntimeException(
        "Ambiguous direct associations on '$requesterModel' targeting model '$modelName' (" . count($directMatches) . " matches)"
      );
    }

    // 2) Fallback: traverse (depth 2) with path info
    $candidates = \App\Lib\Util\TableUtilities::findAssociationsByTraversalWithPath(
      startModel: $requesterModel,
      isMatch: $isMatch,
      types: $assocTypes,
      maxDepth: 2
    );

    if (count($candidates) === 0) {
      throw new \RuntimeException(
        "No association found on '$requesterModel' that targets model '$modelName'"
      );
    }

    if (count($candidates) > 1) {
      // Prefer smallest depth
      $minDepth = min(array_map(static fn($c) => $c['depth'], $candidates));
      $candidates = array_values(array_filter($candidates, static fn($c) => $c['depth'] === $minDepth));

      // If still ambiguous, prefer paths that go through the primary link parent (best effort)
      if (count($candidates) > 1 && method_exists($requesterTable, 'getPrimaryLinks')) {
        $primaryLinks = (array)$requesterTable->getPrimaryLinks();

        $parentAliases = [];
        foreach ($primaryLinks as $pl) {
          if (is_string($pl) && str_ends_with($pl, '_id')) {
            $parentAliases[] = self::foreignKeyToClassName($pl);
          }
        }

        if (!empty($parentAliases)) {
          $preferred = array_values(array_filter($candidates, static function ($c) use ($parentAliases): bool {
            foreach ($parentAliases as $pa) {
              if (in_array($pa, $c['path'], true)) {
                return true;
              }
            }
            return false;
          }));

          if (count($preferred) === 1) {
            $candidates = $preferred;
          }
        }
      }

      if (count($candidates) > 1) {
        $paths = array_map(
          static fn($c) => implode(' -> ', $c['path']) . ' -> ' . $c['assoc']->getTarget()->getAlias(),
          $candidates
        );

        throw new \RuntimeException(
          "Ambiguous associations on '$requesterModel' targeting model '$modelName' (" . count($candidates) . " matches): " . implode(' | ', $paths)
        );
      }
    }

    $target = $candidates[0]['assoc']->getTarget();
    $alias = (string)$target->getRegistryAlias();

    if ($alias !== '') {
      return $alias;
    }

    throw new \RuntimeException(
      "Unable to determine registry alias for association target '$requesterModel -> $modelName'"
    );
  }


  /**
   * Determine the plugin component of a Plugin path.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $s Plugin path, in Plugin.Model format.
   * @return string    Plugin name
   */

  /**
   * Determine the plugin component of a Plugin path.
   * Returns "" (empty string) if no plugin is present.
   */
  public static function pluginPlugin(string $s): string {
    if (str_contains($s, '.')) {
      [$plugin] = explode('.', $s, 2);
      return $plugin;
    }
    return '';
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
   * Strips action prefix (Edit|Delete|View) from a title
   *
   * @param string $title Title to process
   * @return string Title without action prefix
   * @since  COmanage Registry v5.2.0
   */
  public static function stripActionPrefix(string $title): string
  {
    return preg_replace('/^(Edit|Delete|View)\s+/u', '', $title) ?? $title;
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
