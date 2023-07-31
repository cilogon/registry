<?php
/**
 * COmanage Registry Permitted Characters Enum
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
 * @package       registry-plugins
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace CoreAssigner\Lib\Enum;

use App\Lib\Enum\StandardEnum;

class PermittedCharactersEnum extends StandardEnum {
  const AlphaNumeric       = 'AN';
  const AlphaNumDotDashUS  = 'AD';
  const AlphaNumDDUSQuote  = 'AQ';
  const Any                = 'AL';

  /**
   * Get the actual set of characters represented by an enum.
   * 
   * @since  COmanage Registry 5.0.0
   * @param  PermittedCharactersEnum  $enum   Value to look up
   * @param  bool                     $invert If true, return the inverse array (NOT permitted)
   * @return string                           Permitted characters, as a regular expression
   */

  public static function getPermittedCharacters(string $enum, bool $invert=false): string {
    switch($enum) {
      case 'AN':  // AlphaNumeric
        return $invert ? '[^A-Za-z0-9]' : '[A-Za-z0-9]';
        break;
      case 'AD':  // AlphaNumericDotDashUS
        return $invert ? '[^A-Za-z0-9\.\-_]' : '[A-Za-z0-9\.\-_]';
        break;
      case 'AQ':  // AlphaNumericDDUSQuote
        return $invert ? '[^A-Za-z0-9\.\-_\']' : '[A-Za-z0-9\.\-_\']';
        break;
      case 'AL':  // Any
        return $invert ? '' : '.*';
        break;
    }

    throw new \InvalidArgumentException("NOT IMPLEMENTED PermittedCharactersEnum::getPermittedCharacters()");
  }
}
