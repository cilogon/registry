<?php
/**
 * COmanage (Application) Rules Trait
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

use Cake\ORM\RulesChecker;

trait RulesTrait {
  /**
   * Define default business rules. For tables that include this trait, this is
   * the implementation of buildRules called by Cake. In order to supplement the
   * defaults, this buildRules() will also call buildTableRules() with the same
   * signature, if the table defines it.
   *
   * This could also be done via an Event Listener that's registered on
   * Model.buildRules, but that's more complicated and a bit less obvious.
   *
   * @since  COmanage Registry v5.0.0
   * @param  RulesChecker $rules RulesChecker object
   * @return RulesChecker
   */
  
  public function buildRules(RulesChecker $rules): RulesChecker {
    if(method_exists($this, "getPrimaryLink")) {
      // Primary Link keys can not in general be changed. This is mostly to prevent
      // the API from being used to move objects across tenants, since such changes
      // are not ordinarily possible by the user interface. This only needs to be
      // checked on update(), since on add() there is no original value to compare to.
      
      $rules->addUpdate(
        [$this, 'ruleFreezePrimaryLink'],
        'freezePrimaryLink',
        ['errorField' => $this->getPrimaryLink()]
      );
    }
    
    // Add table specific rules
    
    if(method_exists($this, "buildTableRules")) {
      $this->buildTableRules($rules);
    }
    
    return $rules;
  }
  
  // Only Application Rules that apply to multiple Tables should be defined
  // here, so as not to create noise in this file or add unnecessary functions.
  
  /**
   * Application Rule to reject changes to the primary link. This is more of a
   * Security Rule than an Application Rule, but for now we don't distinguish
   * between the two types.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Entity  $entity  Entity to be validated
   * @param  array   $options Application rule options
   * @return boolean          true if the Rule check passes, false otherwise
   */
  
  public function ruleFreezePrimaryLink($entity, $options) {
    $want = $entity->get($this->getPrimaryLink());
    $have = $entity->getOriginal($this->getPrimaryLink());
    
    // If the two values differ throw an error. Note this should only be called
    // on update(), so we shouldn't need to check the original for null (as it
    // might be on add).
    
    if($want !== $have) {
      return __d('error', 'fields.primary_link', [$this->getPrimaryLink()]);
    }
    
    return true;
  }
}