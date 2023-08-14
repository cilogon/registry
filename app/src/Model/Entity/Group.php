<?php
/**
 * COmanage Registry Group Entity
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

namespace App\Model\Entity;

use Cake\ORM\Entity;
use \App\Lib\Enum\GroupTypeEnum;

class Group extends Entity {
  protected $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false, 
  ];
  
  /**
   * Determine if this entity record can be deleted.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool True if the record can be deleted, false otherwise
   */

  public function canDelete(): bool {
    return !$this->isSystem();
  }
  
  /**
   * Determine if this is the All Members group.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool true if this is the All Members group, false otherwise.
   */
  
  public function isAllMembers(): bool {
    return $this->group_type == GroupTypeEnum::AllMembers;
  }

  /**
   * Determine if this is an automatic group.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool true if this is an automatic group, false otherwise.
   */
  
  public function isAutomatic(): bool {
    return in_array($this->group_type, [GroupTypeEnum::ActiveMembers, GroupTypeEnum::AllMembers]);
  }

  /**
   * Determine if this is an owners group.
   * 
   * @since  COmanage Registry v5.0.0
   * @return bool true if this is an owners group, false otherwise.
   */

  public function isOwners(): bool {
    return $this->group_type == GroupTypeEnum::Owners;
  }
  
  /**
   * Determine if this entity is a system group.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool true if this entity is automatically managed, false otherwise
   */
  
  public function isSystem(): bool {
    return in_array($this->group_type,
                    [
                      GroupTypeEnum::ActiveMembers,
                      GroupTypeEnum::Admins,
                      GroupTypeEnum::AllMembers,
                      GroupTypeEnum::Owners
                    ]);
  }
  
  /**
   * Determine if this entity is Read Only.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity Cake Entity
   * @return boolean         true if the entity is read only, false otherwise
   */
  
  public function isReadOnly(): bool {
    // Automatic groups are read-only
    
    return $this->isAutomatic();
  }

  /**
   * Determine if this is not an automatic group.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool true if this is not an automatic group, false otherwise.
   */
  
  public function notAutomatic(): bool {
    return !$this->isAutomatic();
  }
}