<?php
/**
 * COmanage Random Random String Service
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
 * @package       match
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Lib\Random;

class RandomString {
  /**
   * Generate a string suitable for use as an application key.
   *
   * @since  COmanage Registry v5.0.0
   * @return string App Key
   */
  
  public static function generateAppKey() {
    // The chars we'll use to generate out key. Note we use lower case letters
    // and skip l (L). For readability, we generate groups of letters and
    // numbers separately.
    
    $numbers = '0123456789';
    $letters = 'abcdefghijkmnopqrstuvwxyz';
    
    $key = "";
    
    for($g = 0;$g < 2;$g++) {
      for($i = 0;$i < 4;$i++) {
        $key .= $letters[random_int(0, strlen($letters)-1)];
      }
      
      $key .= "-";
      
      for($i = 0;$i < 4;$i++) {
        $key .= $numbers[random_int(0, strlen($numbers)-1)];
      }
      
      if($g == 0) {
        $key .= "-";
      }
    }
    
    return $key;
  }
}