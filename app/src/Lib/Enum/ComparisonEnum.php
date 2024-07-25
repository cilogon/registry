<?php
/**
 * COmanage Registry Comparison Enum
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

class ComparisonEnum extends StandardEnum {
  const Contains               = 'CTS'; // Substr
  const ContainsInsensitive    = 'CTI';
  const Equals                 = 'EQS';
  const EqualsInsensitive      = 'EQI';
  const NotContains            = 'NCT';
  const NotContainsInsensitive = 'NCTI';
  const NotEquals              = 'NEQ';
  const NotEqualsInsensitive   = 'NEQI';
  const Regex                  = 'REGX';

  /**
   * Compare a value against a pattern based on a ComparisonEnum.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string   $value      Value to compare
   * @param  string   $comparison Type of comparison to perform (ComparisonEnum)
   * @param  string   $pattern    Pattern to compare $value against
   * @return bool                 true if $value matches $pattern, false otherwise
   */

  public static function compare(string $value, string $comparison, string $pattern): bool {
    switch($comparison) {
      case ComparisonEnum::Contains:
        return (strpos($value, $pattern) !== false);
        break;
      case ComparisonEnum::ContainsInsensitive:
        return (stripos($value, $pattern) !== false);
        break;
      case ComparisonEnum::Equals:
        return (strcmp($value, $pattern) === 0);
        break;
      case ComparisonEnum::EqualsInsensitive:
        return (strcasecmp($value, $pattern) === 0);
        break;
      case ComparisonEnum::NotContains:
        return (strpos($value, $pattern) === false);
        break;
      case ComparisonEnum::NotContainsInsensitive:
        return (stripos($value, $pattern) === false);
        break;
      case ComparisonEnum::NotEquals:
        return (strcmp($value, $pattern) !== 0);
        break;
      case ComparisonEnum::NotEqualsInsensitive:
        return (strcasecmp($value, $pattern) !== 0);
        break;
      case ComparisonEnum::Regex:
        return (preg_match($pattern, $value));
        break;
      default:
        // Ignore anything unexpected
        break;
    }

    return false;
  }
}