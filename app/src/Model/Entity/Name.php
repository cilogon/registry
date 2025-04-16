<?php
/**
 * COmanage Registry Name Entity
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

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Name extends Entity {
  use \App\Lib\Traits\EntityMetaTrait;
  use \App\Lib\Traits\ReadOnlyEntityTrait;
  use \App\Lib\Traits\MVETrait;
  
  protected $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false, 
  ];
  
  // Make full name available to the API v2 JSON response
  protected $_virtual = [
    'full_name'
  ];

  // Enable Transliteration? This could be refactored into a trait if other entities support it
  protected $transliterate = false;

  /**
   * Generate a full (common) name.
   *
   * @since  COmanage Registry v5.0.0
   * @param  bool   $showHonorific If true, return honorific as part of name
   * @return string                Formatted name
   */
  
  protected function _getFullName($showHonorific = false) {
    // AR-Name-2 If there is a display name set, use it as the full name.
    if(!empty($this->display_name)) {
      return $this->display_name;
    }
    
    // AR-Name-3 Name order is a bit tricky. We'll use the language encoding as
    // our hint, although it isn't perfect. This could be replaced with a more
    // sophisticatedtest as requirements evolve.
    
    $cn = "";

    if(empty($this->language)
       || !in_array($this->language, ['hu', 'ja', 'ko', 'za-Hans', 'za-Hant'])) {
      // Western order. Do not show honorific by default.

      if($showHonorific && !empty($this->honorific)) {
        $cn .= ($cn != "" ? ' ' : '') . $this->honorific;
      }

      if(!empty($this->given)) {
        $cn .= ($cn != "" ? ' ' : '') . $this->given;
      }

      if(!empty($this->middle)) {
        $cn .= ($cn != "" ? ' ' : '') . $this->middle;
      }

      if(!empty($this->family)) {
        $cn .= ($cn != "" ? ' ' : '') . $this->family;
      }

      if(!empty($this->suffix)) {
        $cn .= ($cn != "" ? ' ' : '') . $this->suffix;
      }
    } else {
      // Switch to Eastern order. It's not clear what to do with some components.

      if(!empty($this->family)) {
        $cn .= ($cn != "" ? ' ' : '') . $this->family;
      }

      if(!empty($this->given)) {
        $cn .= ($cn != "" ? ' ' : '') . $this->given;
      }
    }

    return $cn;
  }

  /**
   * Accessor method to obtain possibly transliterated family name.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string  $given  Family name
   */

  protected function _getFamily($family) {
    return $this->maybeTransliterate($family);
  }
  
  /**
   * Accessor method to obtain possibly transliterated given name.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string  $given  Given name
   */

  protected function _getGiven($given) {
    return $this->maybeTransliterate($given);
  }

  /**
   * Accessor method to obtain possibly transliterated middle name.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string  $given  Middle name
   */

  protected function _getMiddle($middle) {
    return $this->maybeTransliterate($middle);
  }

  /**
   * Determine if this entity record can be deleted.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool True if the record can be deleted, false otherwise
   */
  
  public function canDelete(): bool {
    return $this->notPrimary();
  }

  /**
   * Set (or disable) transliteration when returning fields from this Entity.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  bool   $enable   If true, enable transliteration (default is false)
   */

  public function enableTransliteration(bool $enable) {
    $this->transliterate = $enable;
  }

  /**
   * Maybe transliterate the requested string (if enabled).
   * 
   * @since  COmanage Registry v5.1.0
   * @param  string   $s    String to transliterate
   * @return string         Possibly transliterated string
   */

  protected function maybeTransliterate(?string $s): ?string {
    if(!$s) {
      return null;
    }

    if($this->transliterate) {
      // The PHP transliteration library is basically a wrapper around unicode libraries.
      // The documentation is extremely technical and has a fairly steep learning curve.
      // A background in linguistics helps, but only somewhat.
      //
      //   https://unicode-org.github.io/icu/userguide/transforms/general/
      //   http://www.unicode.org/reports/tr15/#Norm_Forms
      //
      // Any-Latin will convert any script to a Latin representation, which might still
      // have composed characters, such as é. We shouldn't actually use "Any", though, since
      // by default Japanese Kanji (which are Chinese derived characters) will be
      // transliterated using Chinese guidance. Unfortunately there isn't a better
      // option available, and the transliterator library basically gives up and doesn't
      // try to address Japanese.
      //
      // NFKD will decompose and separate, so (eg) the "ﬁ" ligature becomes "f" and "i",
      // and å becomes just a. For identifier assignment, this is preferable... in the
      // unlikely event someone pastes in "ﬁ" we really want "fi".
      //
      // We could perform other transformations here, such as converting to lowercase,
      // but for the sake of functional compartmentalization we don't.
      //
      // Note this approach isn't without problems. For exmaple, Kanji in Japanese can
      // translate to multiple words each with different pronunciations, and therefore
      // different transliterations. Or, different European speakers might prefer
      // different transliterations, eg å to a or aa. As such, this feature is
      // experimental pending real world feedback.

      $txid = "Any-Latin; NFKD; [:Nonspacing Mark:] Remove; NFKC";

      return \Transliterator::create($txid)->transliterate($s);
    } else {
      return $s;
    }
  }
  
  /**
   * Determine if this is not a Primary Name.
   *
   * @since  COmanage Registry v5.0.0
   * @return bool true if this is not a Primary Name, false otherwise.
   */
  
  public function notPrimary(): bool {
    return !$this->primary_name;
  }
  
  /**
   * Generate a suitable label for rendering if this is a Primary Name.
   *
   * @since  COmanage Registry v5.0.0
   * @return string Display label
   */
  
  public function primaryLabel(): string {
    return ($this->primary_name ? __d('field', 'primary_name') : "");
  }
}