<?php
/**
 * COmanage Registry Traffic Detour Trait
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Lib\Traits;

trait TrafficDetourTrait {
  // Array of supported Traffic Detours
  private $supportedDetours = [
    'prelogin' => false,
    'login' => false,
    'postlogin' => false,
    'logout' => false
  ];

  /**
   * Determine if this plugin supports the specified Traffic Detour context.
   *
   * @since  COmanage Registry v5.1.0
   * @param  string $context  Traffic Detour context
   * @return bool             true if the specified context is supported, false otherwise
   */
  
  public function supportsDetourContext(string $context): bool {
    return $this->supportedDetours[$context];
  }
    
  /**
   * Assert that the plugin supports the specified Traffic Detour context.
   *
   * @since  COmanage Registry v5.1.0
   * @param  string $context  Traffic Detour context
   */
  
  public function setSupportedDetourContext(string $context) {
    if(!isset($this->supportedDetours[$context])) {
      throw new \InvalidArgumentException(__d('error', 'unknown', [$context]));
    }

    $this->supportedDetours[$context] = true;
  }
}
