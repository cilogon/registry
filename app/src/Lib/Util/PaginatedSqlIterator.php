<?php
/**
 * COmanage Registry Paginated SQL Iterator
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
 * @since         COmanage Registry v3.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Lib\Util;

class PaginatedSqlIterator implements \Iterator {
  // PaginatedSqlIterator implements Keyset Pagination on a Cake model.
  // This is suitable for iterating large datasets sequentially, but not so
  // good for random pagination (eg: via the UI) of large datasets.
  
  // Number of results to pull at one time, for now this is not configurable,
  // though note that when $filter is used the actual result set may be smaller
  // on any given page load.
  private $pageSize = 100;
  
  // For now we only iterate over id, which we know is indexed and is an integer
  private $keyField = "id";
  
  // The table we are querying
  private $table = null;
  
  // Conditions for querying
  private $conditions = null;
  
  // Record count -- used for work estimates only, *not* for pagination
  private $count = null;
  
  // Most recent page of results
  private $results = null;
  
  // Current index (*not* $id) into $results
  private $position = 0;
  
  // The highest ID we've seen so far
  private $maxid = 0;
  
  // Options for find()
  private $options = [];

  // Filter to apply on individual results.
  
  // Why use a Filter instead of $conditions? Primarily to facilitate the encapsulation of
  // logic, rather than copy/paste. $filter will be passed entities, which can be worked with
  // in standard Cake style, avoiding the need to rewrite logic in direct $conditions.
  // Given that PaginatedSqlIterator is usually called in the context of processing (large
  // sets of) records anyway, there is already a cost being incurred in processing time, and
  // the savings in most cases of optimizing the database query will be trivial. Where they
  // are non-trivial, don't use a filter. Note that (additionally) $count will no longer be
  // accurate when $filter is used (it will be an upper bound instead).
  private $filter = null;

  /**
   * Construct a new PaginatedSqlIterator.
   * 
   * @since  COmanage Registry v3.3.0
   * @param  Table    $table        Table
   * @param  array    $condititions Query conditions (use direct queries only, avoid joins due to ChangelogBehavior complications)
   * @param  array    $options      Options to pass to cake find()
   * @param  callable $filter       Optional filter to apply to individual results
   * @return PaginatedSqlIterator
   */

  public function __construct($table, $conditions=null, $options=[], $filter=null) {
    $this->table = $table;
    $this->conditions = $conditions;
    $this->options = $options;
    $this->filter = $filter;
    
    $this->position = 0;
  }
  
  /**
   * Obtain the current element of the iteration.
   *
   * @since  COmanage Registry v3.3.0
   * @return mixed Element at the current position
   */
  
  public function current(): mixed {
    return $this->results[$this->position];
  }
  
  /**
   * Obtain the current count of records.
   *
   * @since  COmanage Registry v3.3.0
   * @param  bool $refresh Refresh the count rather than returning the cached count
   * @return int           Record count
   */
  
  public function count(bool $refresh=false): int {
    if($this->count === null || $refresh) {
      $this->loadCount();
    }
    
    return $this->count;
  }
  
  /**
   * Obtain the current position of the iteration.
   *
   * @since  COmanage Registry v3.3.0
   * @return int The current position
   */
  
  public function key(): int {
    return $this->position;
  }
  
  /**
   * Obtain the count of records.
   *
   * @since  COmanage Registry v3.3.0
   */
  
  protected function loadCount(): void {
    $query = $this->table->find();
    
    if($this->conditions) {
      $query = $query->where($this->conditions);
    }
    
    $this->count = $query->count();
  }
  
  /**
   * Obtain the next page of results (releasing the current page).
   *
   * @since  COmanage Registry v3.3.0
   */
  
  protected function loadPage(): void {
    unset($this->results);
    $this->results = null;
    
    $this->position = 0;
    
    $query = $this->table->find('all', options: $this->options)
                         ->where([$this->keyField . ' >' => $this->maxid]);
    
    if($this->conditions) {
      $query = $query->where($this->conditions);
    }
    
    $query = $query->orderBy([$this->keyField => 'ASC'])
                   ->limit($this->pageSize)
                   // We always request exactly one page, starting from $this->maxid.
                   // We don't use Cake's pagination because the resultset could
                   // change between calls, and the keyset technique ensures we
                   // always get the full set (since newer records will have
                   // higher IDs).
                   ->page(1);
    
    $resultSet = $query->all();
    
    // Use the ResultSet to determine the maximum ID. Since we ordered by
    // id we know the highest value is in the last result.
    $max = $resultSet->last();
    
    if($max) {
      $this->maxid = $max->id;
    }
    // else no remaining rows. valid() will return false.

    if($this->filter) {
      // Build our result cache by manually applying the callback to each result
      // and converting that result to an array.

      foreach($resultSet as $k => $entity) {
        // We expect a simple boolean true/false from $filter
        $filter = $this->filter;

        if($filter($entity)) {
          $this->results[] = $entity;
        }
      }

      if(empty($this->results) && $max) {
        // If we didn't get at least one non-filtered result and we're not at the
        // end of the table then current() won't return correctly. Proactively call
        // loadPage() again.

        $this->loadPage();
      }
    } else {
      // Convert the result set to an array for our own iterator use
      // (Note this is an array of entities, not an array of arrays)
      $this->results = $resultSet->toArray();
    }    
  }
  
  /**
   * Move to the next record in the iteration.
   *
   * @since  COmanage Registry v3.3.0
   */
  
  public function next(): void {
    $this->position++;
    
    if($this->position >= count($this->results)) {
      // We've reached the end of the current page, retrieve the next page of results
      $this->loadPage();
    }
  }
  
  /**
   * Rewind to the first record in the iteration. (This is called by PHP on initialization.)
   *
   * @since  COmanage Registry v3.3.0
   */
  
  public function rewind(): void {
    $this->maxid = 0;
    
    $this->loadPage();
  }
  
  /**
   * Determine if the current position is valid.
   *
   * @since  COmanage Registry v3.3.0
   * @return boolean True if the current position is valid, false otherwise
   */
  
  public function valid(): bool {
    return !empty($this->results[$this->position]);
  }
}
