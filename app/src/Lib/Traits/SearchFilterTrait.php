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

use Cake\Utility\Inflector;
use Cake\I18n\FrozenTime;

trait SearchFilterTrait {
  // Array (and configuration) of permitted search filters
  private $searchFilters = array();
  /**
   * Obtain the set of permitted search attributes.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of permitted search attributes and configuration elements needed for display
   */
  
  public function getSearchableAttributes(): array {
    foreach ($this->filterMetadataFields() as $column => $type) {
      // If the column is an array then we are accessing the Metadata fields. Skip
      if(is_array($type)) {
        continue;
      }
      $this->searchFilters[$column] = [
        'type' => $type,
        'label' => (__d('field', $column) ?? Inflector::humanize($column))
      ];

      // For the date fields we search ranges
      if($type === 'timestamp') {
        $this->searchFilters[$column]['alias'][] = $column . '_starts_at';
        $this->searchFilters[$column]['alias'][] = $column . '_ends_at';
      }
    }

    return $this->searchFilters ?? [];
  }

  /**
   * Build a query where() clause for the configured attribute.
   *
   * @param   \Cake\ORM\Query  $query      Cake ORM Query object
   * @param   string           $attribute  Attribute to filter on (database name)
   * @param   string|array     $q          Value to filter on
   *
   * @return \Cake\ORM\Query            Cake ORM Query object
   * @since  COmanage Registry v5.0.0
   */
  
  public function whereFilter(\Cake\ORM\Query $query, string $attribute, string|array $q): object {
    // not a permitted attribute
    if(empty($this->searchFilters[$attribute])) {
      return $query;
    }

    $search = $q;
    $sub = false;
    // Primitive types
    $search_types = ['integer', 'boolean'];
    if( $this->searchFilters[$attribute]['type'] == "string") {
      $search = "%" . $search . "%";
      $sub = true;
    } elseif(in_array($this->searchFilters[$attribute]['type'], $search_types, true)) {
      return $query->where([$attribute => $search]);
    } elseif( $this->searchFilters[$attribute]['type'] == "timestamp") {
      // Date between dates
      if(!empty($search[0])
         && !empty($search[1])) {
        return $query->where(function (\Cake\Database\Expression\QueryExpression $exp, \Cake\ORM\Query $query) use ($attribute, $search) {
          return $exp->between($attribute, "'" . $search[0] . "'", "'" . $search[1] . "'");
        });
        // The starts at is non empty. So the data should be greater than the starts_at date
      } elseif(!empty($search[0])
        && empty($search[1])) {
        return $query->where(function (\Cake\Database\Expression\QueryExpression $exp, \Cake\ORM\Query $query) use ($attribute, $search) {
          return $exp->gte("'" . FrozenTime::parse($search[0]) . "'", $attribute);
        });
        // The ends at is non-empty. So the data should be less than the ends at date
      } elseif(!empty($search[1])
        && empty($search[0])) {
        return $query->where(function (\Cake\Database\Expression\QueryExpression $exp, \Cake\ORM\Query $query) use ($attribute, $search) {
          return $exp->lte("'" . FrozenTime::parse($search[1]) . "'", $attribute);
        });
      } else {
        // We return everything
        return $query;
      }

    }

    // String values
    return $query->where(function (\Cake\Database\Expression\QueryExpression $exp, \Cake\ORM\Query $query) use ($attribute, $search, $sub) {
        $lower = $query->func()->lower([$attribute => 'identifier']);
        return ($sub) ? $exp->like($lower, strtolower($search))
                      : $exp->eq($lower, strtolower($search));
      });
  }
}
