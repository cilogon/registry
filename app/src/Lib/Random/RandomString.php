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
 * @package       registry
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Lib\Random;

use App\Lib\Enum\PermittedCharactersEnum;
use Random\RandomException;

class RandomString {
  /**
   * Generate a string suitable for use as an application key.
   *
   * @since  COmanage Registry v5.0.0
   * @return string App Key
   */
  
  public static function generateAppKey(): string {
    // The chars we'll use to generate our key. Note we use lower case letters
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

  /**
   * Generate a string suitable for use as a confirmation code. Codes are intended to be
   * typed in or copied by a human, for confirmation codes that are embedded in URLs use
   * generateToken() instead.
   *
   * @return string Token
   * @throws RandomException
   * @since  COmanage REgistry v5.1.0
   */

  public static function generateCode(): string {
    // We only use numbers for codes in order to avoid problems with case.

    $numbers = '0123456789';

    $key = "";

    for($i = 0;$i < 8;$i++) {
      $key .= $numbers[random_int(0, strlen($numbers)-1)];
    }

    return $key;
  }

  /**
   * Generate a string suitable for use as a token. Unlike an application key,
   * a token is not expected to be directly visible or handled by a human, but
   * will (eg) be injected into a URL or a message.
   *
   * @param int $length Length of token
   * @param string|null $allowedCharset Allowed characters
   * @param string|null $regex Regex to match characters
   *
   * @return string Token
   * @throws RandomException
   * @since  COmanage Registry v5.0.0
   */

  public static function generateToken(
    int $length = 16,
    ?string $allowedCharset = null,
    ?string $regex = null, // Permitted
  ): string {
    // Unlike App Keys, tokens don't have restrictions on characters.

    $chars = $allowedCharset ?? 'abcdefghijklmnopqrstuvwxyz01234567890';

    $token = "";

    if ($regex !== null) {
      // We allow the characters that match the regex.
      while(strlen($token) < $length) {
        $ascii = random_int(32, 126); // 32 is space, 126 is '~'
        $randomChar = chr($ascii);
        if (preg_match('/'. $regex . '/', $randomChar) === 1) {
          $token .= $randomChar;

        }
      }
    } else {
      $allowedCharsetLength = strlen($chars) - 1;
      for($i = 0;$i < $length;$i++) {
        $token .= $chars[random_int(0, $allowedCharsetLength)];
      }
    }

    return $token;
  }
}