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
  
  // Number of results to pull at one time, for now this is not configurable
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

  public function __construct($table, $conditions=null, $options=[]) {
    $this->table = $table;
    $this->conditions = $conditions;
    $this->options = $options;
    
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
    
    return $this->initialCount;
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
    
    $this->initialCount = $query->count();
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
    
    $query = $this->table->find('all', $this->options)
                         ->where([$this->keyField . ' >' => $this->maxid]);
    
    if($this->conditions) {
      $query = $query->where($this->conditions);
    }
    
    $query = $query->order([$this->keyField => 'ASC'])
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
    
    // Convert the result set to an array for our own iterator use
    $this->results = $resultSet->toArray();
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
