/**
 * COmanage Registry JavaScript Component Helpers
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

/**
 * Construct human-readable string from language abbreviation code
 * BC-47 language tags (https://en.wikipedia.org/wiki/IETF_language_tag)
 * @param abbreviation   {string} Language Abbreviation
 */
const constructLanguageString = (abbreviation) => {
  const regionNameEngish = new Intl.DisplayNames(
    ['en'], {type: 'language'}
  );
  const regionNameLocale = new Intl.DisplayNames(
    [abbreviation], {type: 'language'}
  );

  if(regionNameEngish.of(abbreviation) === regionNameLocale.of(abbreviation)) {
    return regionNameEngish.of(abbreviation);
  }

  return `${regionNameEngish.of(abbreviation)} (${regionNameLocale.of(abbreviation)})`;
}

// Snake case to Camel case
const camelize = (word) => {
  return word.split("_").map(word => (word[0].toUpperCase() + word.slice(1))).join('')
}

export {
  constructLanguageString,
  camelize
}