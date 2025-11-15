<?php
/**
 * COmanage Registry Filter Helper
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

namespace App\View\Helper;

use App\Lib\Util\StringUtilities;
use Cake\Collection\Collection;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\View\Helper;

class FilterHelper extends Helper
{
  /**
   * Calculate Form Default Field Options
   *
   * @param   string  $columnName
   * @param   string  $label
   *
   * @return array
   */
  public function calculateFieldParams(string $columnName, string $label): array{
    $queryParameters = $this->getView()->getRequest()->getQueryParams();
    $searchableAttributesExtras = $this->getView()->get('vv_searchable_attributes_extras') ?? [];
    $populatedVarData = $this->getView()->get(
    // The populated variables are in plural while the column names are singular
    // Convention: It is a prerequisite that the vvar should be the plural of the column name
      StringUtilities::columnToAutoViewVar($columnName)
    );

    // Field options
    $formParams = [
      'label' => $label,
      'type' => isset($populatedVarData) ? 'select' : 'text',
      // Options will be ignored for non-select fields
      'options' => $populatedVarData,
      'value' => $queryParameters[$columnName] ?? '',
      'required' => false,
      'class' => 'form-control',
      // Empty will be ignored for non-select fields
      'empty' => true
    ];

    // Custom/Additional option items defined in the ModelTable::initialize::setFilterConfig
    // Example: CousTable
    if(isset($searchableAttributesExtras[$columnName]['options'])) {
      // Flatten the custom options
      $customOptionsFlattened = Hash::flatten($searchableAttributesExtras[$columnName]['options']);
      // Get the key of the placeholder string
      $dataKey = array_search('@DATA@', $customOptionsFlattened, true);
      if($dataKey !== false) {
        $customOptionsFlattened[$dataKey] = $formParams['options'];
        $formParams['options'] = Hash::expand($customOptionsFlattened);
      }
    }

    return $formParams;
  }

  /**
   *
   * @return array[]   [search_params, $field_booleans_columns, $field_datetime_columns, $field_generic_columns]
   */
  public function explodeFieldsByType(): array
  {
     // Get the query string and separate the search params from the non-search params
    $queryParameters = $this->getView()->getRequest()->getQueryParams();
    $searchableAttributes = $this->getView()->get('vv_searchable_attributes') ?? [];

    // Filter the search params and take params with aliases into consideration
    $search_params = [];
    $field_booleans_columns = [];
    $field_datetime_columns = [];
    $field_generic_columns = [];
    foreach ($searchableAttributes as $attr => $value) {
      if($value['type'] == 'boolean') {
        $field_booleans_columns[$attr] = $value;
      } elseif ($value['type'] == 'timestamp') {
        $field_datetime_columns[$attr] = $value;
      } else {
        $field_generic_columns[$attr] = $value;
      }

      if(isset($queryParameters[$attr])) {
        $search_params[$attr] = $queryParameters[$attr];
        continue;
      }

      if(isset($value['alias']) && is_array($value['alias'])) {
        foreach ($value['alias'] as $alias_key) {
          if(isset($queryParameters[$alias_key])) {
            $search_params[$attr][$alias_key] = $queryParameters[$alias_key];
          }
        }
      }
    }

    return [
        $search_params,
        $field_booleans_columns,
        $field_datetime_columns,
        $field_generic_columns,
    ];
  }

  /**
   * Return an array of the Form hidden fields and values
   *
   * @return array
   */
  public function getHiddenFields(): array
  {
    // Get the query string and separate the search params from the non-search params
    $queryParameters = $this->getView()->getRequest()->getQueryParams();
    $searchableAttributes = $this->getView()->get('vv_searchable_attributes') ?? [];

    // Search attributes collection
    $alias_params = (new Collection($searchableAttributes))
      ->filter(fn ($val, $attr) => (\is_array($val) && \array_key_exists('alias', $val)) )
      ->extract('alias')
      ->unfold()
      ->toArray();

    // For the non-search params, we need to search the alias params as well
    $searchable_parameters = [
      ...array_keys($searchableAttributes),
      ...$alias_params
    ];

    // Pass back the non-search params as hidden fields, but always exclude the page parameter
    // because we need to start new searches on-page one (or we're likely to end up with a 404).
    return (new Collection($queryParameters))
      ->filter(fn($value, $key) => !\in_array($key, $searchable_parameters, true) && $key != 'page')
      ->toArray();
  }

  /**
   * Construct Full Name from Person ID
   *
   * @param   int  $personId
   *
   * @return string
   */
  public function getFullName(int $personId): string
  {
    if(empty($personId)) {
      return '';
    }
    $ModelTable = TableRegistry::getTableLocator()->get('Names');
    $person = $ModelTable->primaryName($personId);
    return "{$person->given} {$person->family}";
  }

  /**
   * Normalize the filter button title for display.
   * 1) Build initial title via humanize(underscore(label-source))
   * 2) If the title is a sequence of capital letters separated by single spaces (e.g., "C O U"),
   *    remove all spaces -> "COU"
   * 3) If the title is CamelCase with no spaces (e.g., "ThisIsAName"),
   *    insert a space before each capital and trim.
   *
   * @param string $rawLabel
   * @return string
   */
  public function buildFilterButtonTitle(string $rawLabel): string
  {
    // Humanize
    $filterTitle = Inflector::humanize(
      Inflector::underscore($rawLabel)
    );

    // If like "C O U" (series of capital letters separated by spaces), collapse spaces -> "COU"
    if (preg_match('/^[A-Z](?:\s[A-Z])+$/', $filterTitle) === 1) {
      return str_replace(' ', '', $filterTitle);
    }

    // If CamelCase with no spaces, insert spaces before capitals and trim
    if (!str_contains($filterTitle, ' ') && preg_match('/[A-Z]/', $filterTitle) === 1) {
      // Insert a space before every capital letter except the first character
      $filterTitle = preg_replace('/(?<!^)(?=[A-Z])/', ' ', $filterTitle);
      return trim((string)$filterTitle);
    }

    return $filterTitle;
  }
}