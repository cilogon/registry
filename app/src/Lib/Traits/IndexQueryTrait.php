<?php
/**
 * COmanage Registry IndexQuery Trait
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

use App\Lib\Enum\StatusEnum;
use App\Lib\Util\StringUtilities;
use Cake\Database\Expression\QueryExpression;
use Cake\ORM\Query;

trait IndexQueryTrait {
  /**
   * Construct the Index Contain array
   *
   * @param   Query  $query
   *
   * @return object Cake ORM Query object
   * @since  COmanage Registry v5.0.0
   */
  public function constructGetIndexContains(Query $query): object {
    /** var Cake\ORM\Table $table */
    $table = $this->getCurrentTable();
    // Initialize the containClause
    $containClause = [];

    // Get whatever the table configuration has
    if(method_exists($table, 'getIndexContains')
      && !empty($table->getIndexContains())) {
      $containClause = $table->getIndexContains();
    }

    if($this->request->is('restful') || $this->request->is('ajax')) {
      $containClause = $this->containClauseFromQueryParams();
    }

    return empty($containClause) ? $query : $query->contain($containClause);
  }

  /**
   * Construct the Picker Contain array
   *
   * @param   Query  $query
   *
   * @return object Cake ORM Query object
   * @since  COmanage Registry v5.0.0
   */
  public function constructGetPickerContains(Query $query): object {
    /** var Cake\ORM\Table $table */
    $table = $this->getCurrentTable();
    // Initialize the containClause
    $containClause = [];

    // Get whatever the table configuration has
    if(method_exists($table, 'getPickerContains')
      && $table->getPickerContains()) {
      $containClause = $table->getPickerContains();
    }

    return empty($containClause) ? $query : $query->contain($containClause);
  }


  /**
   * Construct the Contain Clause from the query parameters of an AJAX or REST call
   *
   *  Examples:
   *  1. GET https://example.com/registry-pe/api/v2/people?co_id=2&limit=10&extended=PrimaryName,EmailAddresses,Identifiers
   *  2. GET https://example.com/registry-pe/api/v2/people?co_id=2&limit=10&extended=on
   *  3. GET https://example.com/registry-pe/api/v2/people?co_id=2&limit=10&extended=all
   *  4. GET https://example.com/registry-pe/api/v2/people?co_id=2&limit=10
   *
   * @return array        Contain Clause
   * @since  COmanage Registry v5.0.0
   */
  public function containClauseFromQueryParams(): array
  {
    /** var Cake\ORM\Table $table */
    $table = $this->getCurrentTable();

    // Restfull and ajax do not include the IndexContains by default.
    $containClause = [];
    // Set the extended query param to `on` in order to fetch the indexContains
    if(
      $this->request->getQuery('extended') &&
      filter_var($this->request->getQuery('extended'), FILTER_VALIDATE_BOOLEAN)
    ) {
      $containClause = $table->getIndexContains();
    } elseif(
      $this->request->getQuery('extended') &&
      $this->request->getQuery('extended') === 'all'
    ) {
      // Get all the associated models
      $associations = $table->associations();
      foreach($associations->getIterator() as $a) {
        $containClause[] = $a->getName();
      }
    } elseif (
      $this->request->getQuery('extended')
      && \is_string($this->request->getQuery('extended'))
    ) {
      // Get ONLY the associated models requested
      $associations = $table->associations();
      $containQueryList = str_getcsv($this->request->getQuery('extended'));
      foreach($associations->getIterator() as $a) {
        if(\in_array($a->getName(), $containQueryList, true)) {
          $containClause[] = $a->getName();
        }
      }
    }

    return $containClause;
  }

  /**
   * Build the Index Query
   *
   * @params  boolean $pickerMode  True for OR and False for AND. AND is the default behavior
   * @params  array $requestParams
   *
   * @return object Cake ORM Query object
   * @since  COmanage Registry v5.0.0
   */
  public function getIndexQuery(bool $pickerMode = false, array $requestParams = []): object {
    /** var string $modelsName */
    $modelsName = $this->getName();
    /** var Cake\ORM\Table $table */
    $table = $this->getCurrentTable();
    // PrimaryLinkTrait
    $link = $this->getPrimaryLink(true);
    // Initialize the Query Object
    $query = $table->find();
    // Get a pointer to my expression list
    $newexp = $query->expr();
    // The searchable attributes can have an AND or an OR conjunction. The first one is used from the filtering block
    // while the second one from the picker vue module.
    $newexp = $newexp->setConjunction($pickerMode ? 'OR' : 'AND');

    if(!empty($link->attr) && !empty($link->value)) {
      // If a link attribute is defined but no value is provided, then query
      // where the link attribute is NULL
      // "all" is the default finder. But since we are utilizing the paginator here, we will check the configuration
      // for any custom finder.
      $query = $query->where([$table->getAlias().'.'.$link->attr => $link->value]);
    }

    // Get Associated Model Data
    $query = $pickerMode ? $this->constructGetPickerContains($query) : $this->constructGetIndexContains($query);

    // Attributes to search for
    if(method_exists($table, 'getSearchableAttributes')) {
      $searchableAttributes = $table->getSearchableAttributes($this->name, $this->viewBuilder());

      // Extra View Variables
      foreach ($table->getViewVars() as $key => $variable) {
        $this->set($key, $variable);
      }
      // Pass any additional field filter configuration in the view
      $this->set('vv_searchable_attributes_extras', $table->getSearchFiltersExtras());
      if(!empty($searchableAttributes)) {
        $this->set('vv_searchable_attributes', $searchableAttributes);

        // Here we iterate over the attributes, and we add a new where clause for each one
        foreach($searchableAttributes as $attribute => $options) {
          $jointype = 'INNER';
          if (
            $pickerMode
            && !empty($options['model'])
            && \in_array($options['model'], ['Identifiers', 'EmailAddresses'], true)
          ) {
            // XXX People picker is different than people filtering. A people picker has the following requirements:
            //     - Name is required
            //     - Identifiers, EmailAddresses are optional
            //     Having that said, we LEFT JOIN the Identifiers and EmailAddresses models instead of INNER JOIN them.
            $jointype = 'LEFT';
          }
          // Add the Join Clauses
          $query = $table->addJoins(
              $query,
              $attribute,
              $this->request,
              $jointype
          );

          // Construct and apply the where Clause
          if(!empty($this->request->getQuery($attribute))) {
            $newexp = $table->expressionsConstructor($query, $newexp, $attribute, $this->request->getQuery($attribute));
          } elseif (!empty($this->request->getQuery($attribute . '_starts_at'))
            || !empty($this->request->getQuery($attribute . '_ends_at'))) {
            $search_date = [];
            // We allow empty for dates since we might refer to infinity (from whenever or to always)
            $search_date[] = $this->request->getQuery($attribute . '_starts_at') ?? '';
            $search_date[] = $this->request->getQuery($attribute . '_ends_at') ?? '';
            $newexp = $table->expressionsConstructor($query, $newexp, $attribute, $search_date);
          }
        }

        // Append the new conditions
        $query = $query->where($newexp);
      }
    }

    return $query;
  }
}