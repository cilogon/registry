<?php
/**
 * COmanage Registry CO Setting Entity
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

// This class should probably be called "CoSettings" since it reflects a
// collection of settings for a given CO, but it's easier not to fight
// Cake's inflection.
class CoSetting extends Entity {
  use \App\Lib\Traits\ReadOnlyEntityTrait;
  
  protected array $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false, 
  ];
  
  /**
   * Obtain the set of fields required for addresses, as an array.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of required addresses fields
   */
  
  public function address_required_fields_array(): array {
    return explode(",", $this->required_fields_address);
  }
    
  /**
   * Obtain the set of fields permitted for names, as an array.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of permitted name fields
   */
  
  public function name_permitted_fields_array(): array {
    return explode(",", $this->permitted_fields_name);
  }
  
  /**
   * Obtain the set of fields required for names, as an array.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of required name fields
   */
  
  public function name_required_fields_array(): array {
    return explode(",", $this->required_fields_name);
  }
  
  /**
   * Obtain the set of fields permitted for telephone numbers, as an array.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of permitted telephone number fields
   */
  
  public function telephone_number_permitted_fields_array(): array {
    return explode(",", $this->permitted_fields_telephone_number);
  }
}