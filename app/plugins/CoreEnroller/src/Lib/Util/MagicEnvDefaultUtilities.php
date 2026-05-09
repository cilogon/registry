<?php
/**
 * COmanage Registry Magic Env Default Utilities
 *
 * Utilities for deriving default values from environment variables where
 * attribute values are composed from multiple components (eg: Name).
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace CoreEnroller\Lib\Util;

use CoreEnroller\Lib\Enum\MagicEnvNameFieldsEnum;

class MagicEnvDefaultUtilities {
  /**
   * Return the default value for a Name component derived from a base env var name.
   *
   * No fallback values are invented: missing/empty env vars return null.
   *
   * @param string $baseEnvName Base env var name (eg: ENV_OIS_NAME)
   * @param string $component One of: honorific|given|middle|family|suffix
   * @return string|null
   */
  public static function nameComponentFromEnv(string $baseEnvName, string $component): ?string {
    $component = strtolower($component);

    $suffix = match($component) {
      'honorific' => MagicEnvNameFieldsEnum::HonorificSuffix,
      'given'     => MagicEnvNameFieldsEnum::GivenSuffix,
      'middle'    => MagicEnvNameFieldsEnum::MiddleSuffix,
      'family'    => MagicEnvNameFieldsEnum::FamilySuffix,
      'suffix'    => MagicEnvNameFieldsEnum::SuffixSuffix,
      default     => null
    };

    if($suffix === null || $baseEnvName === '') {
      return null;
    }

    $v = getenv($baseEnvName . $suffix);

    if($v === false || $v === '') {
      return null;
    }

    return (string)$v;
  }
}
