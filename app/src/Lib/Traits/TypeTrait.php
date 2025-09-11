<?php
/**
 * COmanage Registry Type Trait
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

use Cake\Utility\Inflector;
use \App\Lib\Enum\SuspendableStatusEnum;

trait TypeTrait {
  use \Cake\ORM\Locator\LocatorAwareTrait;
  
  /**
   * Obtain the available types for this model/attribute, within the requested CO
   *
   * @since  COmanage Registry v5.0.0
   * @param  int    $coId      CO ID
   * @param  string $attribute Attribute to obtain available types for
   * @return array             Array of available types
   */
  
  public function availableTypes(int $coId, string $attribute) {
    $Types = $this->getTableLocator()->get("Types");
    
    $query = $Types->find('list',
                            keyField:  'value',
                            valueField: 'display_name',
                          )
                   ->where(['co_id'     => $coId,
                            'attribute' => $attribute,
                            'status'    => SuspendableStatusEnum::Active])
                   ->orderBy(['Types.display_name' => 'ASC']);
    
    return $query->toArray();
  }
  
  /**
   * Obtain the default (out of the box) types for this model.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $attribute Attribute to obtain default types for
   * @return array             Array of default types and their default strings
   * @throws InvalidArgumentException
   */
  
  public function defaultTypes(string $attribute) {
    $ret = [];
    
    if(!isset($this->defaultTypes[$attribute])) {
      throw new \InvalidArgumentException(__d('error', 'invalid', [$attribute]));
    }
    
    // eg: "Name"
    foreach($this->defaultTypes[$attribute] as $t) {
      // Map to localized text string
      $ret[$t] = __d('defaultType', $this->getAlias().'.'.$attribute.'.'.$t);
    }
    
    return $ret;
  }
}
