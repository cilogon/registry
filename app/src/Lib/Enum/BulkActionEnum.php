<?php
/**
 * COmanage Registry Bulk Action Enum
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

class BulkActionEnum extends StandardEnum {
  public const Delete           = 'delete';
  public const PromoteToOwner   = 'owner';

  /**
   * Return action to request http method
   *
   * @param   string  $action
   *
   * @return string
   * @since  COmanage Registry v5.0.0
   */

  public static function actionToMethod(string $action) : string
  {
    return match($action) {
      'delete' => 'delete',
      'owner' => 'post',
      default => 'get'
    };
  }

  /**
   * Return actions to request http methods
   *
   * @param   array  $actions
   *
   * @return array
   * @since  COmanage Registry v5.0.0
   */

  public static function actionsToMethods() : array
  {
    $ret = [];
    foreach (self::getConstValues() as $act) {
      $ret[$act] = match($act) {
        'delete' => 'delete',
        'owner' => 'post',
        default => 'get'
      };
    }

    return $ret;
  }
}