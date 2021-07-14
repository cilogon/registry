<?php
/**
 * COmanage Registry AutoViewVars Trait
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

trait AutoViewVarsTrait {
  // Array (and configuration) of view variables to automatically populate
  private $autoViewVars = null;
  
  /**
   * Obtain the set of auto view variables.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of auto view variables
   */
  
  public function getAutoViewVars() {
    return $this->autoViewVars;
  }
  
  /**
   * Set the auto view variables.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $vars Array of auto view variables
   */
  
  public function setAutoViewVars($vars) {
    $this->autoViewVars = $vars;
  }
}
