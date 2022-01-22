<?php
/**
 * COmanage Registry Language Enum
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

// As a moderately arbitrary decision, the languages listed here those with at least
// 100m speakers per Ethnologue (by way of wikipedia)
//  https://en.wikipedia.org/wiki/List_of_languages_by_total_number_of_speakers
// as well as any official languages of REFEDS participants not in the above list
//  https://refeds.org/federations
// The key is the ISO 639-2 two letter tag
//  http://www.loc.gov/standards/iso639-2/ISO-639-2_utf-8.txt
// See also http://people.w3.org/rishida/names/languages.html
// and http://www.iana.org/assignments/language-subtag-registry/language-subtag-registry

class LanguageEnum extends StandardEnum {
  const Afrikaans           = 'af';
  const Arabic              = 'ar';
  const Bengali             = 'bn';
  const ChineseSimplified   = 'zh-Hans';
  const ChineseTraditional  = 'zh-Hant';
  const Croatian            = 'hr';
  const Czech               = 'cs';
  const Danish              = 'da';
  const Dutch               = 'nl';
  const English             = 'en';
  const Estonian            = 'et';
  const Finnish             = 'fi';
  const French              = 'fr';
  const German              = 'de';
  const Greek               = 'el';
  const Hebrew              = 'he';
  const Hindi               = 'hi';
  const Hungarian           = 'hu';
  const Indonesian          = 'id';
  const Italian             = 'it';
  const Japanese            = 'ja';
  const Korean              = 'ko';
  const Latvian             = 'lv';
  const Lithuanian          = 'lt';
  const Malaysian           = 'ms';
  const Norwegian           = 'no';
  const Polish              = 'pl';
  const Portuguese          = 'pt';
  const Romanian            = 'ro';
  const Russian             = 'ru';
  const Serbian             = 'sr';
  const Slovene             = 'sl';
  const Spanish             = 'es';
  const Swedish             = 'sv';
  const Turkish             = 'tr';
  const Urdu                = 'ur';
}