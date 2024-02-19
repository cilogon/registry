<?php
/**
 * COmanage Registry Vue Helper
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

namespace App\View\Helper;

use Cake\I18n\FrozenTime;
use Cake\Utility\Inflector;
use Cake\View\Helper;
use Cake\I18n\I18n;

class VueHelper extends Helper {
  private array $locales_list = [
    'enumeration' => [
      'SuspendableStatusEnum.S'
    ],
    'field' => [
      'login',
      'primary',
      'datepicker.hour',
      'unverified'
    ],
    'information' => [
      'global.value.none',
      'datepicker.hour'
    ],
    'operation' => [
      'close'
    ]
  ];

  /**
   * Helper which will produce an array of configured locales
   *
   * @param   string  $lang  The language of the locale
   *
   * @return array []
   * @since  COmanage Registry v5.0.0
   */

  public function locales(string $lang = 'en_US'): array {

    I18n::setLocale($lang);

    $locales = [];
    foreach ($this->locales_list as $domain => $key_list) {
      foreach ($key_list as $key) {
        $locales[$key] = __d($domain, $key);
      }
    }

    return $locales;
  }

}