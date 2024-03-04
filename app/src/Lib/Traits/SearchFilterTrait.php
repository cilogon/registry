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
  // Optional filter configuration that dictates display state and allows for related models
  private $filterConfig = array();
  
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
          'active' => isset($f['active']) ? $f['active'] : true,
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
      if(!empty($filterConfig[$column])) {
        if(isset($filterConfig[$column]['active'])) {
          $fieldIsActive = $filterConfig[$column]['active'];
        }
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

    if(isset($this->searchFilters[$attribute]['model'])) {
      $changelog_fk = strtolower(Inflector::underscore($this->searchFilters[$attribute]['model'])) . '_id';
      $fk = strtolower(Inflector::underscore(Inflector::singularize($this->_alias))) . '_id';
      $mtable_name = Inflector::tableize(Inflector::pluralize($this->searchFilters[$attribute]['model']));
      $mtable_alias = Inflector::pluralize($this->searchFilters[$attribute]['model']);
      $query->join([$mtable_alias => [
        'table' => $mtable_name,
        'conditions' => [
          $mtable_alias . '.' . $fk . '=' .  $this->_alias . '.id',
          $mtable_alias . '.' . 'deleted IS NOT TRUE',
          $mtable_alias . '.' . $changelog_fk . ' IS NULL'
        ],
        'type' => 'INNER'
      ]]);
    }

    // Prepend the Model name to the attribute
    $attributeWithModelPrefix = isset($this->searchFilters[$attribute]['model']) ?
      Inflector::pluralize($this->searchFilters[$attribute]['model']) . '.' . $attribute :
      $this->_alias . '.' . $attribute;

    $search = $q;
    $sub = false;
    // Primitive types
    $search_types = ['integer', 'boolean'];
    if( $this->searchFilters[$attribute]['type'] == "string") {
      $search = "%" . $search . "%";
      $sub = true;
    // Search type
    } elseif(in_array($this->searchFilters[$attribute]['type'], $search_types, true)) {
      return $query->where([$attributeWithModelPrefix => $search]);
      // Date
    } elseif($this->searchFilters[$attribute]['type'] == "date") {
      // Parse the date string with FrozenTime to improve error handling
      return $query->where([$attributeWithModelPrefix => FrozenTime::parseDate($search, 'y-M-d')]);
    // Timestamp
    } elseif( $this->searchFilters[$attribute]['type'] == "timestamp") {
      // Date between dates
      if(!empty($search[0])
         && !empty($search[1])) {
        return $query->where(function (\Cake\Database\Expression\QueryExpression $exp, \Cake\ORM\Query $query) use ($attributeWithModelPrefix, $search) {
          return $exp->between($attributeWithModelPrefix, "'" . $search[0] . "'", "'" . $search[1] . "'");
        });
        // The starts at is non-empty. So the data should be greater than the starts_at date
      } elseif(!empty($search[0])
        && empty($search[1])) {
        return $query->where(function (\Cake\Database\Expression\QueryExpression $exp, \Cake\ORM\Query $query) use ($attributeWithModelPrefix, $search) {
          return $exp->gte("'" . FrozenTime::parse($search[0]) . "'", $attributeWithModelPrefix);
        });
        // The ends at is non-empty. So the data should be less than the ends at date
      } elseif(!empty($search[1])
        && empty($search[0])) {
        return $query->where(function (\Cake\Database\Expression\QueryExpression $exp, \Cake\ORM\Query $query) use ($attributeWithModelPrefix, $search) {
          return $exp->lte("'" . FrozenTime::parse($search[1]) . "'", $attributeWithModelPrefix);
        });
      } else {
        // We return everything
        return $query;
      }

    }

    // String values
    return $query->where(function (\Cake\Database\Expression\QueryExpression $exp, \Cake\ORM\Query $query) use ($attributeWithModelPrefix, $search, $sub) {
        $lower = $query->func()->lower([$attributeWithModelPrefix => 'identifier']);
        return ($sub) ? $exp->like($lower, strtolower($search))
                      : $exp->eq($lower, strtolower($search));
      });
  }
}
