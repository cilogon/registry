<?php
/**
 * COmanage Registry Plugin Entity
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

use \Cake\ORM\Entity;
use \App\Lib\Enum\SuspendableStatusEnum;

class Plugin extends Entity {
  protected array $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false, 
  ];

  /**
   * Determine if this Plugin can be activated.
   * 
   * @since  COmanage Registry v5.0.0
   * @return bool   True if this plugin can be activated, false otherwise
   */

  public function canActivate(): bool {
    // Any Suspended plugin can be activated

    return $this->status == SuspendableStatusEnum::Suspended;
  }

  /**
   * Determine if this Plugin can be deactivated.
   * 
   * @since  COmanage Registry v5.0.0
   * @return bool   True if this plugin can be deactivated, false otherwise
   */

  public function canDeactivate(): bool {
    // Only non-core Active plugins can be deactivated

    return ($this->status == SuspendableStatusEnum::Active && !$this->isReadOnly());
  }
  
  /**
   * Determine if this entity is Read Only.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool   True if the entity is read only, false otherwise
   */
  
  public function isReadOnly(): bool {
    // Local plugins are read only
    
    return $this->location == 'core';
  }
}