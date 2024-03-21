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

use Cake\Database\Expression\QueryExpression;
use Cake\Http\ServerRequest;
use Cake\ORM\Query;
use Cake\Utility\Inflector;
use Cake\I18n\FrozenTime;

trait SearchFilterTrait {
  // Array (and configuration) of permitted search filters
  private array $searchFilters = [];
  // Optional filter configuration that dictates display state and allows for related models
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

    $changelog_fk = strtolower(Inflector::underscore($this->searchFilters[$attribute]['model'])) . '_id';
    $fk = strtolower(Inflector::underscore(Inflector::singularize($this->_alias))) . '_id';
    $mtable_name = Inflector::tableize(Inflector::pluralize($this->searchFilters[$attribute]['model']));
    $mtable_alias = Inflector::pluralize($this->searchFilters[$attribute]['model']);

    return $query->join([$mtable_alias => [
      'table' => $mtable_name,
      'conditions' => [
        $mtable_alias . '.' . $fk . '=' .  $this->_alias . '.id',
// XXX Moved to changelong Behavior
//        $mtable_alias . '.' . 'deleted IS NOT TRUE',
//        $mtable_alias . '.' . $changelog_fk . ' IS NULL'
      ],
      'type' => $joinType
    ]]);
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
    // Both are empty, just return
    if (empty($dates[0]) && empty($dates[1])) {
      return $exp;
    }
    // The starts_at is non-empty. So the data should be greater than the starts_at date
    if (!empty($dates[0]) && empty($dates[1])) {
      return $exp->gte("'" . FrozenTime::parse($dates[0]) . "'", $attributeWithModelPrefix);
    }
    // The ends_at is non-empty. So the data should be less than the ends_at date
    if (!empty($dates[1])
      && empty($dates[0])) {
      return $exp->lte("'" . FrozenTime::parse($dates[1]) . "'", $attributeWithModelPrefix);
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
    $attributeWithModelPrefix = isset($this->searchFilters[$attribute]['model']) ?
      Inflector::pluralize($this->searchFilters[$attribute]['model']) . '.' . $attribute :
      $this->_alias . '.' . $attribute;

    $search = $q;
    // Use the `lower` function to apply uniformity for the search
    $lower = $query->func()->lower([$attributeWithModelPrefix => 'identifier']);

    return match ($this->searchFilters[$attribute]['type']) {
      'string'             => $exp->like($lower, strtolower('%' . $search . '%')),
      'integer', 'boolean' => $exp->add([$attributeWithModelPrefix => $search]),
      'date'               => $exp->add([$attributeWithModelPrefix => FrozenTime::parseDate($search, 'y-M-d')]),
      'timestamp'          => $this->constructDateComparisonClause($search),
      default              => $exp->eq($lower, strtolower($search))
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
   * @return array Array of permitted search attributes and configuration elements needed for display
   */

  public function getSearchableAttributes(string $controller, string $vv_tz=null): array {
    $modelname = Inflector::classify(Inflector::underscore($controller));
    $filterConfig = $this->getFilterConfig();

    // Gather up related models defined in the $filterConfig
    // XXX For now, we'll list these first - but we should probably provide a better way to order these.
    foreach ($filterConfig as $field => $f) {
      if($f['type'] == 'relatedModel') {
        $fieldName = Inflector::classify(Inflector::underscore($field));
        $this->searchFilters[$field] = [
          'type' => 'string', // XXX for now - this needs to be looked up.
          'label' => \App\Lib\Util\StringUtilities::columnKey($fieldName, $field, $vv_tz, true),
          'active' => $f['active'] ?? true,
          'model' => $f['model'],
          'order' => $f['order']
        ];
      }
    }

    foreach ($this->filterMetadataFields() as $column => $type) {
      // If the column is an array then we are accessing the Metadata fields. Skip
      if(is_array($type)) {
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
        'label' => \App\Lib\Util\StringUtilities::columnKey($modelname, $column, $vv_tz, true),
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
   * Set explicilty defined filter configuration defined in the table class.
   *
   * @since  COmanage Registry v5.0.0
   */

  public function setFilterConfig(array $filterConfig): void {
    $this->filterConfig = $filterConfig;
  }

}
