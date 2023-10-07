<?php
/**
 * COmanage Registry Read Only Entity Trait
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

trait ReadOnlyEntityTrait {
  /**
   * Determine if this entity is Read Only.
   *
   * @since  COmanage Registry v5.0.0
   * @return boolean  True if the entity is read only, false otherwise
   */
  
  public function isReadOnly(): bool {
    // If we're incorporated into an MVEA entity, isMVEReadOnly will check for
    // pipelined attributes (which are read only).
    
    if(method_exists($this, 'isMVEReadOnly') && $this->isMVEReadOnly()) {
      return true;
    }
    
    // Frozen attributes are treated as Read Only
    if($this->frozen) {
      return true;
    }
    
    // Records flagged as deleted or with a parent foreign key are read only
    
    // The class name is something like `\App\Model\Entity\PersonRole', but we just
    // want person_role (lowercased).
    $entityName = \Cake\Utility\Inflector::underscore(substr(strrchr(get_class($this), '\\'),1));
    $parentfk = $entityName . "_id";
    
    return (isset($entity->deleted) && $entity->deleted)
            || !empty($entity->$parentfk);
  }
}
