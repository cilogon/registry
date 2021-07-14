<?php
/**
 * COmanage Validation Trait, shared between Match and Registry
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
 * @package       common
 * @since         COmanage Common v1.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

/**
 * THIS FILE IS MASTERED IN THE COMMON REPOSITORY.
 */

declare(strict_types = 1);

namespace App\Lib\Traits;

trait ValidationTrait {
  /**
   * Perform a conditional validation check, where if a select value matches an
   * array of values, then another field must not be empty. In theory we should be
   * able to do this with Cake native conditional validation, but if a field is
   * not required and null, then Cake doesn't run validation on the value.
   *
   * To configure this validation rule, use setProvider():
   * 
   *  $validator->setProvider('conditionalRequire', [
   *                          'inArray' => [valuesThatRequireOtherField],
   *                          'require' => 'field_to_require',
   *                          'label'   => 'field label, for use in error message']);
   *
   * Note this validation rule is applied on the value that must always be set,
   * which will result in the validation error being unintuitively placed on the
   * "wrong" attribute.
   *
   * @since  COmanage Common v1.0.0
   * @param  string $value   Value to validate
   * @param  array  $context Validation context
   * @return mixed  True if $value validates, or an error string otherwise
   */
  
  public function validateConditionalRequire($value, array $context) {
    // What component are we?
    $COmponent = __('product.code');
    
    if(!empty($value)
       && in_array($value, $context['providers']['conditionalRequire']['inArray'])
       && empty($context['data'][ $context['providers']['conditionalRequire']['require'] ])) {
      return __($COmponent.'.er.input.condreq', [$context['providers']['conditionalRequire']['label']]);
    }
    
    return true;
  }
  
  /**
   * Determine if a string submitted from a form is valid input.
   *
   * @since  COmanage Common v1.0.0
   * @param  string $value   Value to validate
   * @param  array  $context Validation context
   * @return mixed  True if $value validates, or an error string otherwise
   */
  
  public function validateInput($value, array $context) {
    // By default, we'll accept anything except < and >. Arguably, we should accept
    // anything at all for input (and filter only on output), but this was agreed to
    // as an extra "line of defense" against unsanitized HTML output, since there are
    // currently no known cases where user-entered input should permit angle brackets.
    
// XXX we previously supported 'filter'. 'flags', and 'invalidchars' as arguments, do we still need to?
    
    // What component are we?
    $COmponent = __('product.code');
    
    // Perform a basic string search.
    
    $invalid = "<>";
    
    if(strlen($value) != strcspn($value, $invalid)) {
      // Mismatch, implying bad input
      return __($COmponent.'.er.input.invalid');
    }
    
    // We require at least one non-whitespace character (CO-1551)
    if(!preg_match('/\S/', $value)) {
      return __($COmponent.'.er.input.blank');
    }

    return true;
  }
  
  /**
   * Determine if a string submitted from a form is a valid language.
   *
   * @since  COmanage Common v1.0.0
   * @param  string $value   Value to validate
   * @param  array  $context Validation context
   * @return mixed  True if $value validates, or an error string otherwise
   */
  
  public function validateLanguage($value, array $context) {
// XXX this was previously done by examining $cm_texts[$cm_lang]['en.language']
//     we need a new way to enumerate permitted language codes
    /*
    if(!in_array($value, array_values(timezone_identifiers_list()))) {
      return __($COmponent.'.er.input.invalid');
    }*/
    
    return true;
  }
  
  /**
   * Determine if a string submitted from a form is valid SQL identifier.
   *
   * @since  COmanage Common v1.0.0
   * @param  string $value   Value to validate
   * @param  array  $context Validation context
   * @return mixed  True if $value validates, or an error string otherwise
   */
  
  public function validateSqlIdentifier($value, array $context) {
    // What component are we?
    $COmponent = __('product.code');
    
    // Valid (portable) SQL identifiers begin with a letter or underscore, and
    // subsequent characters can also include digits. We'll be a little stricter
    // than we need to be for now by only accepting A-Z, when in fact certain
    // additional characters (like á) are also acceptable.
    
    if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $value)) {
      return __($COmponent.'.er.input.invalid');
    }
    
    return true;
  }
  
  /**
   * Determine if a string submitted from a form is a valid timezone.
   *
   * @since  COmanage Common v1.0.0
   * @param  string $value   Value to validate
   * @param  array  $context Validation context
   * @return mixed  True if $value validates, or an error string otherwise
   */
  
  public function validateTimeZone($value, array $context) {
    if(!in_array($value, array_values(timezone_identifiers_list()))) {
      return __($COmponent.'.er.input.invalid');
    }
    
    return true;
  }
}