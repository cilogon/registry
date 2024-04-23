<?php
/**
 * COmanage Registry Function Utilities
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

namespace App\Lib\Util;

use Cake\ORM\TableRegistry;
use \Cake\Utility\Inflector;

class FunctionUtilities {
  /**
   * Create a chained method call
   *
   *    Example:
   *    $this->getRequest()->getQuery(name: 'group_id)
   *    $rootObject: $this,
   *    $chainedDescriptionExample => [
   *      // Chain of methods
   *      'getRequest',
   *      'getQuery' => [
   *        // parameter name => parameter value, We are taking advantage of the named parameters feature
   *        'name' =>'group_id'
   *      ],
   *    ]
   *
   * @param   mixed  $rootObj              Object of the intance method we are calling
   * @param   array  $chainedDescription   Description from root to final method call.
   *
   * @return mixed                         Return the intermediate objects or the final value
   * @since  COmanage Registry v5.0.0
   */

  public static function dynamicChainedFunction(mixed $rootObj, array $chainedDescription): mixed {
    if(!empty($chainedDescription)) {
      $key = key($chainedDescription);
      // This is the case where we pass a function with no parameters
      $funcName = null;
      $params = null;
      // We pass a function with an array of parameters
      if( \is_int($key)) {
        $funcName = array_shift($chainedDescription);
      } elseif (\is_string($key)) {
        $funcName = $key;
        $params = array_shift($chainedDescription);
      }

      if(!empty($params)) {
        $funcCall = $rootObj->$funcName(...$params);
      } else {
        $funcCall = $rootObj->$funcName();
      }
      $value = self::dynamicChainedFunction($funcCall, $chainedDescription);
    }

    return !\is_object($rootObj) ? $rootObj : $value;
  }

}