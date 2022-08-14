<?php
/**
 * COmanage Registry String Utilities
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
 * @since         COmanage Registry v3.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Lib\Util;

class StringUtilities {
  // The following two utilities provide base64 encoding and decoding for
  // strings that might contain special characters that could interfere with
  // URLs. base64 can generate reserved characters, so we handle those specially
  // according to common (but not standardized) conventions. See CO-1667 and
  // https://stackoverflow.com/questions/1374753/passing-base64-encoded-strings-in-url
  // The mapping we use is the same as the YUI library. RFC 4648 base64url is
  // another option, but strangely doesn't map the padding character (=).
  
  /**
   * base64 decode a string.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $s String to decode
   * @return string    Decoded string
   */
  
  public static function urlbase64decode(string $s): string {
    return !empty($s)
           ? base64_decode(str_replace(array(".", "_", "-"),
                                       array("+", "/", "="),
                                       $s))
           : "";
  } 
  
  /**
   * base64 encode a string.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $s String to encode
   * @return string    Encoded string
   */
  
  public static function urlbase64encode(string $s): string {
    return !empty($s)
           ? str_replace(array("+", "/", "="),
                         array(".", "_", "-"),
                         base64_encode($s))
           : "";
  }
}
