<?php
/**
 * COmanage Registry Search Filter Trait
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

use App\Lib\Util\StringUtilities;
use Bake\Utility\Model\AssociationFilter;
use Cake\Database\Expression\QueryExpression;
use Cake\Http\ServerRequest;
use Cake\ORM\Query;
use Cake\Utility\Inflector;
use Cake\I18n\FrozenTime;

trait SearchFilterTrait {
  /**
   * Array (and configuration) of permitted search filters
   *
   * @var array
   */
  private array $searchFilters = [];

  /**
   * Extra Configurations for each filter
   *
   * @var array
   */
  private array $searchFiltersExtras = [];

  /**
   * List of view Vars
   * @var array
   */
  private array $viewVars = [];

  /**
   * Optional filter configuration that dictates display state and allows for related models
   *
   * @var array
   */
  private array $filterConfig = [];

  /**
   * Build the query join associations
   *
   * @param   Query          $query
   * @param   string         $attribute
   * @param   ServerRequest  $request
   * @param   string         $joinType
   *
   * @return object Cake ORM Query object
   * @since  COmanage Registry v5.0.0
   */
  public function addJoins(Query $query, string $attribute, ServerRequest $request, string $joinType = 'INNER'): object {
    // not a permitted attribute
    if(empty($this->searchFilters[$attribute])
       || $request->getQuery($attribute) === null
       || !isset($this->searchFilters[$attribute]['model'])) {
      return $query;
    }

    $parentTable = $this->_alias;
    $joinAssociations = [];
    // Iterate over the dot notation and add the joins in the correct order
    // People.Names
    foreach (explode('.', $this->searchFilters[$attribute]['model']) as $associationsdModel) {
      $mtable_name = Inflector::tableize(Inflector::pluralize($associationsdModel));
      $mtable_alias = Inflector::pluralize($associationsdModel);

      $AssociationFilter = new AssociationFilter();
      $associatedModel = $AssociationFilter->filterAssociations($this->fetchTable($associationsdModel));
      $relation = null;
      $conditions =  [];
      if(isset($associatedModel['HasOne'])
         && !empty($associatedModel['HasOne'][$parentTable])
      ) {
        $relation = $associatedModel['HasOne'][$parentTable];
        $conditions[] = $relation['alias'] . '.' . $relation['foreignKey'] . '=' .  $mtable_alias . '.id';
      } elseif(isset($associatedModel['HasMany'])
        && !empty($associatedModel['HasMany'][$parentTable])
      ) {
        $relation = $associatedModel['HasMany'][$parentTable];
        $conditions[] = $relation['alias'] . '.' . $relation['foreignKey'] . '=' .  $mtable_alias . '.id';
      } elseif(isset($associatedModel['BelongsTo'])
        && !empty($associatedModel['BelongsTo'][$parentTable])
      ) {
        $relation = $associatedModel['BelongsTo'][$parentTable];
        $conditions[] = $relation['alias'] . '.id' . '=' .  $mtable_alias . '.' . $relation['foreignKey'];
      }

      $joinAssociations[$mtable_alias] = [
        'table' => $mtable_name,
        'conditions' => $conditions,
        'type' => $joinType
      ];

      $parentTable = $associationsdModel;
    }


    return $query->join($joinAssociations);
    // XXX We can not use the innerJoinWith since it applies EagerLoading and includes all the fields which
    //     causes problems
//    return $query->innerJoinWith($this->searchFilters[$attribute]['model']);
  }

  /**
   * Construct the Date comparison clause from the query parameters
   *
   * @param   QueryExpression  $exp
   *
   * @param   string           $attributeWithModelPrefix Model.attribute as required by CAKE ORM
   * @param   array            $dates  Contains the list of starting and ending dates in the following order [starts_at, ends_at]
   *
   * @return QueryExpression
   * @since  COmanage Registry v5.0.0
   */
  public function constructDateComparisonClause(QueryExpression $exp, string $attributeWithModelPrefix, array $dates): QueryExpression {
    // Both are empty, return
    if (empty($dates[0]) && empty($dates[1])) {
      return $exp;
    }

    // The starts_at is empty or the ends_at is empty
    if (
      (!empty($dates[0]) && empty($dates[1]))
      ||
      (empty($dates[0]) && !empty($dates[1]))
    ) {
      $date = empty($dates[0]) ? $dates[1] : $dates[0];
      if(str_contains($attributeWithModelPrefix, 'valid_from')) {
        return $exp->gte($attributeWithModelPrefix, FrozenTime::parse($date));
      } elseif(str_contains($attributeWithModelPrefix, 'valid_through')) {
        return $exp->lte($attributeWithModelPrefix, FrozenTime::parse($date));
      }
    }

    return $exp->between($attributeWithModelPrefix, "'" . $dates[0] . "'", "'" . $dates[1] . "'");
  }

  /**
   * Build a query where() clause for the configured attribute.
   *
   * @param   Query            $query
   * @param   QueryExpression  $exp
   * @param   string           $attribute  Attribute to filter on (database name)
   * @param   string|array     $q          Value to filter on
   *
   * @return object Cake ORM Query object
   * @since  COmanage Registry v5.0.0
   */

  public function expressionsConstructor(Query $query, QueryExpression $exp, string $attribute, string|array $q): object {
    // not a permitted attribute
    if(empty($this->searchFilters[$attribute])) {
      return $exp;
    }

    // Prepend the Model name to the attribute
    $modelPrefix = $this->_alias;
    if(isset($this->searchFilters[$attribute]['model'])) {
      $associationNamesPath = explode('.', $this->searchFilters[$attribute]['model']);
      $modelPrefix = Inflector::pluralize(end($associationNamesPath));
    }

    $attributeWithModelPrefix = $modelPrefix . '.' . $attribute;

    $search = $q;

    // Handle special expression functions here
    if(\in_array($search, ['isnull', 'isnotnull'])) {
      return match($search) {
        'isnull'    => $exp->isNull($attributeWithModelPrefix),
        'isnotnull' => $exp->isNotNull($attributeWithModelPrefix)
      };
    }


    // XXX Strings and Enums are not treated the same. Enums require an exact match but strings
    //     are partially/non-case sensitive matched
    return match ($this->searchFilters[$attribute]['type']) {
      // Use the `lower` function to apply uniformity for the search
      'string'             => $exp->like($query->func()->lower([$attributeWithModelPrefix => 'identifier']),
                                         strtolower('%' . $search . '%')),
      'select',           // AutoviewVar type
      'parent',           // AutoviewVar type
      'boolean',
      'integer'            => $exp->add([$attributeWithModelPrefix => $search]),
      'date'               => $exp->add([$attributeWithModelPrefix => FrozenTime::parseDate($search, 'y-M-d')]),
      'timestamp'          => $this->constructDateComparisonClause($exp, $attributeWithModelPrefix, $search),
      default              => $exp->eq($query->func()->lower([$attributeWithModelPrefix => 'identifier']),
                                       strtolower($search))
    };
  }

  /**
   * Get explicilty defined filter configuration defined in the table class.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function getFilterConfig(): array {
    return $this->filterConfig;
  }

  /**
   * Obtain the set of permitted search attributes.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string       $controller Controller name
   * @param  DateTimeZone $vv_tz      Current time zone, if known
   * @return array Array of permitted search attributes and configuration elements needed for display
   */

  public function getSearchableAttributes(string $controller, \DateTimeZone $vv_tz=null): array {
    $modelname = Inflector::classify(Inflector::underscore($controller));
    $filterConfig = $this->getFilterConfig();

    // We get the filter keys and we will force include the fields that we
    // have excluded in the filterMetadataFields() method. This way we have a
    // method to exclude a field globally but then force its usage when needed through
    // configuration
    $filterKeys = array_keys($filterConfig);

    // Gather up related models defined in the $filterConfig
    // XXX For now, we'll list these first - but we should probably provide a better way to order these.
    foreach ($filterConfig as $field => $f) {
      $fieldName = Inflector::classify(Inflector::underscore($field));

      if(isset($f['extras'])) {
        $this->searchFiltersExtras[$field] = $f['extras'];
        continue;
      }

      $filterType = $f['type'] ?? 'string';
      // Custom boolean use cases
      if(\in_array($f['type'], ['isNull', 'isNotNull'])) {
        $filterType = 'boolean';
      }
      // Picker configuration
      if(isset($f['picker'])) {
        $autocompleteArgs = [
          'type' => 'search',
          'fieldName' => $field,
          'personType' => $f['picker']['type'],
          'htmlId' => Inflector::dasherize($field) . '-picker', // This is the input ID
          'viewConfigParameters' => $f['picker']['configuration']
        ];
        $this->viewVars['vv_autocomplete_arguments'] = $autocompleteArgs;
      }

      $this->searchFilters[$field] = [
        'type' => $filterType,
        'label' => $f['label'] ?? StringUtilities::columnKey($fieldName, $field, $vv_tz, true),
        'active' => $f['active'] ?? true,
        'model' => $f['model'],
        'order' => $f['order']
      ];
    }

    // Include meta fields that are defined in the configuration
    // FORCE USAGE
    $filterMetadatFielsList = $this->filterMetadataFields();
    foreach ($filterKeys as $key) {
      if (isset($filterMetadatFielsList['meta'][$key])) {
        $filterMetadatFielsList[$key] = $filterMetadatFielsList['meta'][$key];
      }

    }

    foreach ($filterMetadatFielsList as $column => $type) {
      // If the column is an array, then we are accessing the Metadata fields. Skip
      if(\is_array($type)) {
        continue;
      }

      // Set defaults
      $fieldIsActive = true;

      // Gather filter configurations, if any, for local table fields.
      // An active field is visible in the filter form. An inactive field is not but can be enabled.
      if(!empty($filterConfig[$column])
         && isset($filterConfig[$column]['active'])) {
        $fieldIsActive = $filterConfig[$column]['active'];
      }

      $attribute = [
        'type' => $type,
        'label' => StringUtilities::columnKey($modelname, $column, $vv_tz, true),
        'active' => $fieldIsActive,
        'order' => 99 // this is the default
      ];

      // The column name should always go first, then the description will follow.
      if($column == 'name') {
        $this->searchFilters = [ $column => $attribute, ...$this->searchFilters];
      } else if ($column == 'description') {
        if(isset($this->searchFilters['name'])) {
          $this->searchFilters = array_slice($this->searchFilters, 0, 1)
            + [ $column => $attribute ]
            + array_slice($this->searchFilters, 1);
        } else {
          $this->searchFilters = [ $column => $attribute, ...$this->searchFilters];
        }
      } else {
        $this->searchFilters[$column] = $attribute;
      }

      // For the date fields we search ranges
      if($type === 'timestamp') {
        $this->searchFilters[$column]['alias'][] = $column . '_starts_at';
        $this->searchFilters[$column]['alias'][] = $column . '_ends_at';
      }
    }

    return $this->searchFilters ?? [];
  }

  /**
   * Set explicit defined filter configuration defined in the table class.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function setFilterConfig(array $filterConfig): void {
    $this->filterConfig = $filterConfig;
  }

  /**
   * Get field extra configurations calculated in getSearchableAttributes
   *
   * @since  COmanage Registry v5.0.0
   */
  public function getSearchFiltersExtras(): array
  {
    return $this->searchFiltersExtras;
  }

  /**
   * Get View Vars
   *
   * @since  COmanage Registry v5.0.0
   */
  public function getViewVars(): array
  {
    return $this->viewVars;
  }
}
