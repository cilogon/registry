<?php
/**
 * COmanage Validation Trait
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

namespace App\Lib\Traits;

use Cake\Core\Configure;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

trait ValidationTrait {
  /**
   * Register validation rules for the primary link key(s) associated with this table.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator   Cake Validator
   * @param  array     $primaryKeys Array of primary link key(s) for this table
   * @return Validator              Cake Validator
   */
  
  public function registerPrimaryKeyValidation(Validator $validator, array $primaryKeys): Validator {
    foreach($primaryKeys as $pk) {
      $validator->add($pk, [
        'content' => ['rule' => 'isInteger']
      ]);
      $validator->notEmptyString($pk, null, function($context) use ($pk, $primaryKeys) {
        // This primary key must be populated (and this closure returns true)
        // if all other primary keys are empty
        $othersEmpty = true;
        
        foreach(array_diff($primaryKeys, [$pk]) as $opk) {
          $othersEmpty &= empty($context['data'][$opk]);
        }
        
        return $othersEmpty;
      });
    }
    
    return $validator;
  }
  
  /**
   * Register validation rules for the provided field, as a string.
   *
   * @since  COmanage Registry v5.0.0
   * @param  Validator            $validator      Cake Validator
   * @param  TableSchemaInterface $schema         Cake Schema
   * @param  string               $field          Field name
   * @param  bool                 $required       Whether this field is required
   * @param  string               $prefix         Require the value to start with $prefix
   * @param  bool                 $validateInput  Whether to apply the validateInput rule
   * @return Validator            Cake Validator
   */
  
  public function registerStringValidation(
    Validator             $validator,
    TableSchemaInterface  $schema,
    string                $field,
    bool                  $required,
    string                $prefix = '',
    bool                  $validateInput = true
  ): Validator {
    $rules = [
      'size'    => ['rule'     => ['validateMaxLength', ['column' => $schema->getColumn($field)]],
                    'provider' => 'table']
    ];

    if($validateInput) {
      $rules['filter'] = ['rule'     => ['validateInput'],
                          'provider' => 'table'];
    }

    if(!empty($prefix)) {
      $rules['prefix'] = [
        'rule'     => ['validatePrefix'],
        'pass'     => [$prefix],
        'provider' => 'table'
      ];
    }

    $validator->add($field, $rules);
    
    if($required) {
      $validator->notEmptyString($field);
    } else {
      $validator->allowEmptyString($field);
    }
    
    return $validator;
  }
  
  /**
   * Verify that $value is a valid record in the current CO
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $value   Value to validate
   * @param  array  $context Validation context
   * @return mixed  True if $value validates, or an error string otherwise
   */

  public function validateCO(string $value, array $context) {
    // Verify that $value is a valid record in the current CO
    
    // We read the CO ID as set in Configure via AppController. Alternately, we
    // could create an event listener (like ChangelogEventListener) that listens
    // for buildValidator and injects the CO ID into the Validator object, but
    // then every Table's validationDefault() would need to parse the CO ID and
    // inject it into the validation configuration, which seems like a lot of
    // extra work.
    
    // The CO of the record we are validating
    $thisCoId = null;
    
    // The CO of the record we are pointing to, via the field being validated
    $targetCoId = null;
    
    if(!empty($context['data']['co_id'])) {
      // Accept the co_id in the request data
      $thisCoId = $context['data']['co_id'];
    } elseif(!empty($context['data']['id'])) {
      // We can use findCoForRecord to get the CO in context
      $thisCoId = $this->findCoForRecord($context['data']['id']);
    } elseif(method_exists($this, 'getPrimaryLink')
             && $this->getPrimaryLink() != null
             && !empty($context['data'][$this->getPrimaryLink()])) {
      // This is probably a new record being added (which we could verify via
      // $context['newRecord']). We can't directly use findCoForRecord, but
      // we can use the primary link to get the CO.
      
      $LinkTable = $this->getPrimaryLinkTable();
      
      $thisCoId = $LinkTable->findCoForRecord((int)$context['data'][$this->getPrimaryLink()]);
    } else {
      return __d('error', 'coid');
    }
    
    if(!$thisCoId) {
      return __d('error', 'coid');
    }
    
    // Calculate the table name for the requested field
    if(preg_match('/^(.*?)_id$/', $context['field'], $f)) {
      $tableName = \Cake\Utility\Inflector::camelize(\Cake\Utility\Inflector::pluralize($f[1]));
      
      $Table = TableRegistry::getTableLocator()->get($tableName);
      
      $targetCoId = $Table->findCoForRecord((int)$value);
    }
    
    if(!$targetCoId) {
      return __d('error', 'coid');
    }

    if($thisCoId != $targetCoId) {
      return __d('error', 'coid.mismatch', [$this->name, $value]);
    }
    
    return true;
  }
  
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
   * @since  COmanage Registry v5.0.0
   * @param  string $value   Value to validate
   * @param  array  $context Validation context
   * @return mixed  True if $value validates, or an error string otherwise
   */
  
  public function validateConditionalRequire(string $value, array $context) {
    if(!empty($value)
       && in_array($value, $context['providers']['conditionalRequire']['inArray'])
       && empty($context['data'][ $context['providers']['conditionalRequire']['require'] ])) {
      return __d('error', 'input.condreq', [$context['providers']['conditionalRequire']['label']]);
    }
    
    return true;
  }


  /**
   * Validate that a numerical value is a multiple of a given step.
   *
   * This validation rule ensures that the $value is evenly divisible by the specified $step.
   * It can be used, for example, to validate values in increments like 5, 10, etc.
   *
   * @since  COmanage Registry v5.2.0
   * @param string $value The numerical value to validate.
   * @param int    $step The step value the $value should be divisible by.
   * @param array  $context Validation context.
   * @return bool           True if $value validates as a multiple of $step, false otherwise.
   */
  public function validateIncreaseStep(string $value, int $step, array $context) {
    return (int)$value%$step == 0;
  }
  
  /**
   * Determine if a string submitted from a form is valid input.
   *
   * @param string $value   Value to validate
   * @param array  $options
   * @param array  $context Optional validation context; accepts 'type' of 'html'
   * @return mixed  True if $value validates, or an error string otherwise
   *@since  COmanage Registry v5.0.0
   */
  
  public function validateInput(string $value, array $options = [], array $context = []): bool|string {
    // By default, we'll accept anything except < and >. Arguably, we should accept
    // anything at all for input (and filter only on output), but this was agreed to
    // as an extra "line of defense" against unsanitized HTML output. Where user supplied
    // HTML input is needed, we will pass the input through the Symfony HTML Sanitizer instead.
    
// XXX we previously supported 'flags' and 'invalidchars' as arguments, do we still need to?
// CFM-152 review the logic here

    if(!empty($options['type'])) {
      switch($options['type']) {
        case 'html':
          // We are accepting HTML input. We will mostly pass it all through and ensure
          // properly sanitized output. However, we can do some very rudimentary checking for script tags.
          // (An informational note should be placed below these fields as well.)
          $lowercaseVal = strtolower($value);
          if(str_contains($lowercaseVal, '<script')) {
            // Disallowed HTML is in the input, so warn the user.
            return __d('error', 'input.invalid.html');
          }
          return true;
        default:
          // We use h() (htmlspecialchars) for consistency with the views.
          // If we get here, simply check that we end up with the same string we started with
          // once we pass the string through htmlspecialchars.
          if($value != h($value)) {
            // Mismatch, implying bad input
            return __d('error', 'input.invalid');
          }
      }
    } else {
      // Perform a basic string search
      
      $invalid = "<>";
      
      if(strlen($value) != strcspn($value, $invalid)) {
        // Mismatch, implying bad input
        return __d('error', 'input.invalid.brackets');
      }
      
      // We require at least one non-whitespace character (CO-1551)
      $notBlankValidation = $this->validateNotBlank($value, $options);
      if ($notBlankValidation !== true) {
        return $notBlankValidation;
      }
    }
    
    return true;
  }

  /**
   * Validate the maximum length of a field.
   *
   * @param string  $value    Value to validate
   * @param array   $options
   * @param array   $context  Validation context, which must include the schema definition
   *
   * @return bool|string True if $value validates, or an error string otherwise
   * @since  COmanage Registry v5.0.0
   */
  
  public function validateMaxLength(string $value, array $options = [], array $context = []): bool|string {
    // We use our own so we can introspect the field's max length from the
    // provided table schema object, and use our own error message (without
    // having to copy it to every table definition).
    
    // Text has no limit.
    if ($options['column']['type'] === 'text') {
      return true;
    }

    $maxLength = $options['column']['length'];
    
    if(!empty($value) && mb_strlen($value) > $maxLength) {
      return __d('error', 'input.length', [$maxLength]);
    }
    
    return true;
  }


  /**
   * Validate that the given value is not blank.
   *
   * @since  COmanage Registry v5.2.0
   * @param mixed $value Value to validate
   * @param array $context Validation context
   * @return mixed          True if $value validates, or an error string otherwise
   */
  public function validateNotBlank(mixed $value, array $context): mixed
  {
    $regex = '/\S+/m';
    if (is_scalar($value) && preg_match($regex, $value)) {
      return true;
    }
    return __d('error', 'input.blank');
  }

  /**
   * Determine if a string submitted from a form is valid SQL identifier.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $value   Value to validate
   * @param  array  $context Validation context
   * @return mixed  True if $value validates, or an error string otherwise
   */
  
  public function validateSqlIdentifier(string $value, array $context) {
    // Valid (portable) SQL identifiers begin with a letter or underscore, and
    // subsequent characters can also include digits. We'll be a little stricter
    // than we need to be for now by only accepting A-Z, when in fact certain
    // additional characters (like á) are also acceptable. We also accept dots
    // to allow for schema.table notation.
    
    if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\.]*$/', $value)) {
      return __d('error', 'input.invalid');
    }
    
    return true;
  }
  
  /**
   * Determine if a string submitted from a form is a valid timezone.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $value   Value to validate
   * @param  array  $context Validation context
   * @return mixed  True if $value validates, or an error string otherwise
   */
  
  public function validateTimeZone(string $value, array $context) {
    if(!in_array($value, array_values(timezone_identifiers_list()))) {
      return __d('error', 'input.invalid');
    }
    
    return true;
  }

  /**
   * Determine if a string submitted from a form has a valid prefix.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $value   Value to validate
   * @param  string $prefix  Prefix value
   * @param  array  $context Validation context
   * @return mixed  True if $value validates, or an error string otherwise
   */

  public function validatePrefix(string $value, string $prefix, array $context) {
    $coid   = $context['data']['co_id'] ?? '';
    if($prefix === "co_id") {
      $prefix = !empty($coid) ? "co_" . $coid . "." : "";
    }

    if (!preg_match('/^' . $prefix . '(?:.*)/m', $value)) {
      return __d('error', 'input.invalid.prefix', [$prefix]);
    }

    return true;
  }
}
