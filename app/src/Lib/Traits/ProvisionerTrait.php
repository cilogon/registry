<?php
/**
 * COmanage Registry Provisioner Trait
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

trait ProvisionerTrait {
  // Array of models supported by this Provisioner
  private $provisionableModels = [];
  
  /**
   * Obtain the set of supported Provisionable Models.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of supported Provisionable Models
   */
  
  public function getProvisionableModels(): array {
    return $this->provisonableModels;
  }
  
  /**
   * Determine if the requested Model is supported by this Provisioner.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string   $model  Model to check
   * @return bool             True if $model is supported, false otherwise
   */

  public function isProvisionableModel(string $model): bool {
    return in_array($model, $this->provisionableModels);
  }
  
  /**
   * Set the supported Provisionable Models.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array $models Array of supported Provisionable Models
   */
  
  public function setProvisionableModels(array $models) {
    $this->provisionableModels = $models;
  }
}
