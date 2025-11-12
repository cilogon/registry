<?php
/**
 * COmanage Registry AutoViewVars Trait
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Lib\Traits;

use App\Lib\Enum\SuspendableStatusEnum;
use App\Lib\Util\FunctionUtilities;
use App\Lib\Util\StringUtilities;
use Cake\ORM\TableRegistry;

trait AutoViewVarsTrait {
  // Array (and configuration) of view variables to automatically populate
  private $autoViewVars = null;
  
  /**
   * Obtain the set of auto view variables.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of auto view variables
   */
  
  public function getAutoViewVars() {
    return $this->autoViewVars;
  }
  
  /**
   * Set the auto view variables.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $vars Array of auto view variables
   */
  
  public function setAutoViewVars($vars): void {
    $this->autoViewVars = $vars;
  }

  /**
   * Calculate the AutoView Vars
   *
   * @since  COmanage Registry v5.1.0
   * @param  int|null     $coId
   * @param  Object|null  $obj    Current object (eg: from edit), if set
   * @param  string       $action Controller action
   * @return \Generator
   */

  public function calculateAutoViewVars(
    ?int $coId,
    ?Object $obj = null,
    ?string $action = null
  ): \Generator {
    /** var Cake\ORM\Table $table */
    $table = $this;

    foreach($table->getAutoViewVars() as $vvar => $avv) {
      $generatedValue = null;

      switch($avv['type']) {
        case 'array':
          // Use the provided array of values. By default, we use the values
          // for the keys as well, to generate HTML along the lines of
          // <option value="Foo">"Foo"</option>. (See also 'hash'.)
          $generatedValue = array_combine($avv['array'], $avv['array']);
          break;
        case 'enum':
          // We just want the localized text strings for the defined constants.
          $class = '\\App\\Lib\\Enum\\'.$avv['class'];
          // We support plugin notation for plugin defined enumerations.
          if(strstr($avv['class'], ".")) {
            $bits = explode('.', $avv['class'], 2);
            $class = '\\'.$bits[0].'\\Lib\\Enum\\'.$bits[1];
          }

          $generatedValue = $class::getLocalizedConsts();
          break;
        case 'hash':
          // Like 'array' but we assume we are passed key/value pairs
          $generatedValue = $avv['hash'];
          break;
        // "auxiliary" and "select" do basically the same thing, but the former
        // returns the full object and the latter just returns a hash suitable
        // for a select. "type" is a shorthand for "select" for type_id.
        case 'type':
          // Inject configuration. Since we're only ever looking at the types
          // table, inject the current CO along with the requested attribute
          $avv['model'] = 'Types';
          $avv['where']['status'] = SuspendableStatusEnum::Active;
          if(!empty($avv['attribute'])) {
            if(\is_array($avv['attribute'])) {
              $avv['where']['attribute IN'] = $avv['attribute'];
            } else {
              $avv['where']['attribute'] = $avv['attribute'];
            }
          }
          // else we allow for 'attribute' to be omitted for cases where type_id
          // can be populated by multiple types (eg Match Server Attributes)
        // fall through
        case 'auxiliary':
// XXX add list as in match?
        case 'select':
          $avvmodel = $avv['model'];
          $AModel = TableRegistry::getTableLocator()->get($avvmodel);
          // XXX We should probably move to a more generic approach.
          // Models can have various types of parent keys (and sometimes multiple concurrently),
          // so it’s better to use PrimaryLinkTrait to handle this.
          // if(method_exists($this->$avvmodel, "calculateCoForRecord")) {
          //  $avv['where']['co_id'] = $this->$avvmodel->calculateCoForRecord($obj)
          // }
          if($AModel->getSchema()->hasColumn('co_id')) {
            $avv['where']['co_id'] = $coId;
          }

          $query = $AModel->find($avv['type'] == 'auxiliary' ? 'all' : 'list');

          if(!empty($avv['find'])) {
            if($avv['find'] == 'filterPrimaryLink') {
              // We're filtering the requested model, not our current model.
              // See if the requested key is available, and if so run the find.

              $linkFilter = $table->getPrimaryLink();

              if($linkFilter) {
                // Try to find the $linkFilter value
                $v = null;

                // We might have been passed an object with the current value
                if($obj && !empty($obj->$linkFilter)) {
                  $v = $obj->$linkFilter;
                } elseif(!empty($this->request->getQuery($linkFilter))) {
                  $v = $this->request->getQuery($linkFilter);
                }
// XXX also need to check getData()?
// XXX shouldn't this use $this->getPrimaryLink() instead? Or maybe move $this->primaryLink
//     to PrimaryLinkTrait and call it there?

                if($v) {
                  $avv['where'][$table->getAlias().'.'.$linkFilter] = $v;
                  //$query = $query->where([$table->getAlias().'.'.$linkFilter => $v]);
                }
              }
            } else {
              // Use the specified finder, if configured
              $query = $query->find($avv['find']);
            }
          } elseif($table->getSchema()->hasColumn('co_id')) {
            // XXX is this the best logic? maybe some relation to filterPrimaryLink?
            // By default, filter everything on CO ID
            $avv['where']['co_id'] = $coId;
            //$query = $query->where([$table->getAlias().'.co_id' => $coId]);
          }

          // Where Rule. The rule will be transfered as is
          if(!empty($avv['where'])) {
            // Filter on the specified clause (of the form [column=>value])
            $query = $query->where($avv['where']);
          }

          // Where rule that will be evaluated. We use the custom whereEvan key to
          // distinguish from the plain where. Also it might contain more than one conditions
          if(!empty($avv['whereEval'])) {
            foreach ($avv['whereEval'] as $whereClauseColumn => $chainedMethodDescription) {
              $calculatedValue = FunctionUtilities::dynamicChainedFunction(
                $this,
                $chainedMethodDescription
              );
              $query = $query->where([$whereClauseColumn => $calculatedValue]);
            }
          }

          // Sort the list by display field
          if(!empty($avv['model']) && method_exists($AModel, "getDisplayField")) {
            $query->orderBy([$AModel->getDisplayField() => 'ASC']);
          } elseif(method_exists($table, "getDisplayField")) {
            $query->orderBy([$table->getDisplayField() => 'ASC']);
          }

          $generatedValue = $query->toArray();
          break;
        case 'parent':
          // Supported models must use TreeTrait
          $generatedValue = $table->potentialParents(
            coId: $coId, 
            // We only want the hierarchical prefixes ("-") for add and edit
            id: $obj ? $obj->id : null,
            hierarchy: in_array($action, ['add', 'edit'])
          );
          break;
        case 'plugin':
          if(!empty($avv['pluginType'])) {
            // Return the list of Entry Point Models implementing the specified Plugin Type
            // (eg: "source")
            $PluginTable = TableRegistry::getTableLocator()->get('Plugins');
            $generatedValue = $PluginTable->getActivePluginModels($avv['pluginType']);
          } elseif(!empty($avv['model'])) {
            // Return the list of instantiations of the specified Entry Point Model
            // (eg: "ApiConnector.ApiSources")
            $ModelTable = TableRegistry::getTableLocator()->get($avv['model']);

            // We need to find the parent ("pluggable") model, but there actually isn't a way
            // for a Plugin to explicitly say what it's pluggable model parent is. (The reverse
            // is possible via the PluggableModel Trait.) Instead, we walk the model's primary
            // links until we find one that implements the Pluggable functions, at which point
            // it's safe to assume that's the correct parent model since Entry Point Models
            // can't implement two pluggable interfaces.
            $primaryLinks = $ModelTable->getPrimaryLinks();

            foreach($primaryLinks as $l) {
              $PluggableTable = TableRegistry::getTableLocator()->get(StringUtilities::foreignKeyToClassName($l));

              if(method_exists($PluggableTable, "getPluggableModelType")) {
                // This is the correct primary link.

                // This isn't really an Application Rule, but per the documentation at
                // https://spaces.at.internet2.edu/display/COmanage/Adding+a+Pluggable+Model
                // we require all Pluggable Models to have a status field, and define "S" as the
                // value that will remove values from this list. (The recommendation is to use "X"
                // to disable but allow the value to still appear.) 
                $generatedValue = $PluggableTable->find('list',
                                     keyField: 'id',
                                     valueField: 'description',
                                   )
                                   // We assume Pluggable tables always FK to co_id which
                                   // is currently true but might not always be true
                                   ->where([
                                    'plugin' => $avv['model'],
                                    'co_id' => $coId,
                                    'status IS NOT' => "S"
                                   ])
                                   ->all();
                break;
              }
            }
          }
          break;
        default:
          throw new \LogicException(__d('error', 'auto.viewvar.type.unknown', [$avv['type']]));
      }

      yield $vvar => $generatedValue;
    }
  }
}
