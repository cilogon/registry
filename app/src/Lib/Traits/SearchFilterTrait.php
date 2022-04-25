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

trait SearchFilterTrait {
  // Array (and configuration) of permitted search filters
  private $searchFilters = array();
  
  /**
   * Determine the UI label for the specified attribute.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $attribute Attribute
   * @return string            Label
   * @todo   Merge this with _column_key from index.ctp
   */
  
  public function getLabel(string $attribute): string {
    if(isset($this->searchFilters[$attribute]['label'])
      && $this->searchFilters[$attribute]['label'] !== null) {
      return $this->searchFilters[$attribute]['label'];
    }
    
    // Try to construct a label from the language key.
    $l = __d('field', $attribute);
    
    if($l != $attribute) {
      return $l;
    }
    
    // If we make it here, just return $attribute
    return $attribute;
  }
  
  /**
   * Obtain the set of permitted search attributes.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of permitted search attributes and configuration elements needed for display
   */
  
  public function getSearchableAttributes(): array {
    // Not every configuration element is necessary for the search form, and
    // some need to be calculated, so we do that work here.
    
    $ret = [];
    
    foreach(array_keys($this->searchFilters) as $attr) {
      $ret[ $attr ] = [
        'label' => $this->getLabel($attr)
      ];
    }
    
    return $ret;
  }
  
  /**
   * Add a permitted search filters.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $attribute     Attribute that filtering is permitted on (database name)
   * @param  bool   $caseSensitive Whether this attribute is case sensitive
   * @param  string $label         Label for this search field, or null to autocalculate
   * @param  bool   $substring     Whether substring searching is permitted for this attribute
   */
  
  public function setSearchFilter(string $attribute,
                                  bool   $caseSensitive=false,
                                  string $label=null,
                                  bool   $substring=true): void {
    $this->searchFilters[$attribute] = compact('caseSensitive', 'label', 'substring');
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
    if(!empty($this->searchFilters[$attribute])) {
      $cs = (isset($this->searchFilters[$attribute]['caseSensitive'])
        && $this->searchFilters[$attribute]['caseSensitive']);
      
      $sub = (isset($this->searchFilters[$attribute]['substring'])
        && $this->searchFilters[$attribute]['substring']);
      
      $search = $q;
      
      if($sub) {
        // Substring
        // note, for now at least, a user may infix their own %
        $search .= "%";
      }
      
      if($cs) {
        // Case sensitive
        $query->where([$attribute => $search]);
      } else {
        // Case insensitive
        $query->where(function (\Cake\Database\Expression\QueryExpression $exp, \Cake\ORM\Query $query) use ($attribute, $search, $sub) {
          $lower = $query->func()->lower([
            // https://book.cakephp.org/3/en/orm/query-builder.html#function-arguments
            $attribute => 'identifier'
          ]);
          if($sub) {
            return $exp->like($lower, strtolower($search));
          } else {
            return $exp->eq($lower, strtolower($search));
          }
        });
      }
    }
    // else not a permitted attribute
    
    return $query;
  }
}
