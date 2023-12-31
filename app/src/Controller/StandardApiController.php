<?php
/**
 * COmanage Registry Standard API Controller
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

namespace App\Controller;

use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;

class StandardApiController extends AppController {
  /**
   * Perform Cake Controller initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
    
  public function initialize(): void {
    parent::initialize();
    
    // We need to manually set the Current CO ID for models with dynamic
    // validation rules. This is ordinarily done by AppController, but
    // AppController doesn't know how to figure out what CO an API request
    // maps to. Plugins can implement calculateRequestedCOID() to tell
    // AppController what the current CO is, we then pass that information
    // manually here.

    // It's not ideal to have a hardcoded list of models, but we don't have
    // a better solution at the moment.

    foreach(['Addresses', 'Names', 'TelephoneNumbers'] as $m) {
      $Table = TableRegistry::getTableLocator()->get($m);

      $Table->setCurCoId($this->getCOID());
    }
    
    // We want API auth, not Web Auth
    $this->RegistryAuth->setConfig('apiUser', true);
  }
}