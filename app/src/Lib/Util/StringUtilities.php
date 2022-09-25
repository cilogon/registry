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

use \Cake\Utility\Inflector;

class StringUtilities {
  /**
   * Construct the Column human-readable key
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $modelsName           The name of the Model
   * @param  string  $c                    The name of the column
   * @param  string  $tz                   The timezone
   * @param  boolean $useCustomClMdlLabel  Whether to use a custom `Model.column` field entry or rely on the default
   * @return string  Column friendly name
   */

  public static function columnKey($modelsName, $c, $tz=null, $useCustomClMdlLabel=false): string {
    if(strpos($c, "_id", strlen($c)-3)) {
      // Key is of the form field_id, use .ct label instead
      $k = Inflector::camelize(Inflector::pluralize(substr($c, 0, strlen($c)-3)));

      return __d('controller', $k, [1]);
    }

    // Look for a model specific key first
    $label = __d('field', $modelsName.'.'.$c);

    if($label != $modelsName.'.'.$c && !$useCustomClMdlLabel) {
      return $label;
    }

    if($tz) {
      // If there is a timezone aware label, use that
      $label = __d('field', $c.'.tz', [$tz]);

      if($label != $c.'.tz') {
        return $label;
      }
    }

    // XXX for the case of eduPersonAffiliation names we could
    //     consider the Inflector solution. First underscore and then
    //     Humanize

    // Otherwise look for the general key
    $cfield = __d('field', $c);
    return ($cfield !== $c) ? $cfield : \Cake\Utility\Inflector::humanize($c);
  }

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
