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
  protected $_accessible = [
    '*' => true,
    'id' => false,
    'slug' => false, 
  ];
  
  /**
   * Generate a common (full) name.
   *
   * @since  COmanage Registry v5.0.0
   * @param  bool   $showHonorific If true, return honorific as part of name
   * @return string                Formatted name
   */
  
  protected function _getCommonName($showHonorific = false) {
    // Name order is a bit tricky. We'll use the language encoding as our hint,
    // although it isn't perfect. This could be replaced with a more sophisticated
    // test as requirements evolve.

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
}