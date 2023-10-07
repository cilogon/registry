<?php
/**
 * COmanage Registry Multi-Value Entity Utilities Trait
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

trait MVETrait {
  /**
   * Determine if this entity is Read Only.
   *
   * @since  COmanage Registry v5.0.0
   * @return boolean  True if the entity is read only, false otherwise
   */
  
  public function isMVEReadOnly(): bool {
    // Records pipelined from an EIS are read only
    
    // The class name is something like `\App\Model\Entity\Name', but we just
    // want name (lowercased).
    $entityName = \Cake\Utility\Inflector::underscore(substr(strrchr(get_class($this), '\\'),1));
    $sourcefk = "source_" . $entityName . "_id";
    
    if(isset($entity->$sourcefk)) {
      return !empty($entity->$sourcefk);
    }
    
    return false;
  }
  
  /**
   * Generate a where clause suitable for the current entity.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array suitable for a query's where clause
   */
  
  public function whereClause(): array {
    if(!empty($this->person_id)) {
      return [$this->getSource().'.person_id' => $this->person_id];
    } elseif(!empty($this->external_identity_id)) {
      return [$this->getSource().'.external_identity_id' => $this->external_identity_id];
    } else {
      throw new \InvalidArgumentException(__d('error', 'notfound.person'));
    }
  }
}
