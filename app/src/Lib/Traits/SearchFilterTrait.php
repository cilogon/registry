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
        // todo: Probably the following line is redundant but i am leaving it for now
        'label' => (__d('field', $column) ?? Inflector::humanize($column))
      ];
    }

    return $this->searchFilters ?? [];
  }

  /**
   * Build a query where() clause for the configured attribute.
   *
   * @since  COmanage Registry v5.0.0
   * @param  \Cake\ORM\Query $query     Cake ORM Query object
   * @param  string          $attribute Attribute to filter on (database name)
   * @param  string          $q         Value to filter on
   * @return \Cake\ORM\Query            Cake ORM Query object
   */
  
  public function whereFilter(\Cake\ORM\Query $query, string $attribute, string $q): object {
    // not a permitted attribute
    if(empty($this->searchFilters[$attribute])) {
      return $query;
    }

    $search = $q;
    $sub = false;
    if( $this->searchFilters[$attribute]['type'] == "string") {
      $search = "%" . $search . "%";
      $sub = true;
    }

    // Boolean Values
    if($this->searchFilters[$attribute]['type'] == 'boolean') {
      return $query->where([$attribute => $search]);
    }

    // String values
    return $query->where(function (\Cake\Database\Expression\QueryExpression $exp, \Cake\ORM\Query $query) use ($attribute, $search, $sub) {
        $lower = $query->func()->lower([$attribute => 'identifier']);
        return ($sub) ? $exp->like($lower, strtolower($search))
                      : $exp->eq($lower, strtolower($search));
      });
  }
}
