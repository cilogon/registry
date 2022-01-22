<?php
/**
 * COmanage Registry Standard Enum
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

namespace App\Lib\Enum;

use ReflectionClass;

class StandardEnum {
  /**
   * Get the localized text strings for the constants in the Enumeration.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of enumeration keys and their localizations
   */
  
  public static function getLocalizedConsts() : array {
    $ret = array();
    
    // Get the keys for this enum
    $reflect = new ReflectionClass(get_called_class());
    
    $consts = $reflect->getConstants();
    
    $className = substr(strrchr(get_called_class(), '\\'), 1);
    
    foreach(array_values($consts) as $key) {
      $ret[$key] = __d('enumeration', $className.'.'.$key);
    }
    
    return $ret;
  }
  
  /**
   * Get the values for the constants in the Enumeration.
   *
   * @since  COmanage Registry v5.0.0
   * @return array Array of enumeration values
   */
  
  public static function getConstValues() : array {
    // Get the keys for this enum
    $reflect = new ReflectionClass(get_called_class());
    
    $consts = $reflect->getConstants();
    
    return array_values($consts);
  }
}