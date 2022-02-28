<?php
/**
 * COmanage Registry API User Entity
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

use Cake\Auth\DefaultPasswordHasher;
use Cake\ORM\Entity;

class ApiUser extends Entity {
  use \App\Lib\Traits\ReadOnlyEntityTrait;
  
  protected $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false,
    // By default, we don't want to allow api_key to be set directly over the API
    // (or via the UI, but we control that by not exposing a field). Only generate()
    // can set api_key. (AR-ApiUser-4)
    'api_key' => false
  ];
  
  /**
   * Hash (bcrypt) an API Key on save.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $apiKey Unhashed API Key
   * @return string         Hashed API Key
   */
  
  protected function _setApiKey($apiKey) {
    // Note setters are disabled by ChangelogBehavior in order to prevent (eg)
    // rehashing the hash on archive.
    
    if(!empty($apiKey)) {
      return (new DefaultPasswordHasher)->hash($apiKey);
    }
    
    return false;
  }
}