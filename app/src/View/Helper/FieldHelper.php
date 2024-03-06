<?php
/**
 * COmanage Registry Field Helper
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

// XXX can we merge this with Match so we only maintain one file?
declare(strict_types = 1);

namespace App\View\Helper;

use Cake\I18n\FrozenTime;
use Cake\Utility\Inflector;
use Cake\View\Helper;
use App\Lib\Enum\DateTypeEnum;
use App\Lib\Util\StringUtilities;

class FieldHelper extends Helper {
  public $helpers = ['Form', 'Html', 'Url', 'Alert'];
  
  // Is this read-only or read-write?
  protected $editable = true;
  
  // Our current model name
  protected $modelName = null;
  
  // The plugin we are rendering within, if set
  protected $pluginName = null;
  
  // The list of required fields
  protected $reqFields = [];
  
  // The current entity, if edit or view
  protected $entity = null;

  // The current action
  protected $action = null;

  /**
   * Emit an informational banner.
   *
   * @since  COmanage Registry v6.0.0
   * @param  string $info Information string
   * @return string       HTML for banner
   */
  
  public function banner(string $info): string {
    return '<li class="alert-banner">' .
      $this->Alert->alert($info, 'warning')
    . '</li>';
  }
  
  /**
   * Emit a form control.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $fieldName   Form field
   * @param  array   $options     FormHelper control options
   * @param  string  $labelText   Label text (fieldName language key used by default)
   * @param  string  $ctrlCode    Control code passed in from wrapper functions
   * @param  string  $cssClass    Start li css class passed in from wrapper functions
   * @param  string  $beforeField Markup to be placed before/above the field
   * @param  string  $afterField  Markup to be placed after/below the field
   * @param  string  $prefix      Field prefix - used for API Usernames
   * @param  bool    $labelIsTextOnly For fields that should not include <label> markup
   * @return string  HTML for control
   */
  
  public function control(string $fieldName,
                          array  $options = [],
                          string $labelText = null,
                          string $ctrlCode = null,
                          string $cssClass = '',
                          string $beforeField = '',
                          string $afterField = '',
                          string $prefix = '',
                          bool   $labelIsTextOnly = false,
                          string $controlType = null): string {
    $coptions = $options;
    $coptions['label'] = false;
    $coptions['readonly'] = 
      !$this->editable 
      || (isset($options['readonly']) && $options['readonly'])
      // Plugins can't be changed after the parent object is instantiated
      || ($fieldName == 'plugin' && $this->action == 'edit');
    // Selects, Checkboxes, and Radio Buttons use "disabled"
    $coptions['disabled'] = $coptions['readonly'];
    
    // Specify a class on the <li> form control wrapper
    $liClass = $cssClass;
  
    // Get the field type from the map of fields (e.g. 'boolean', 'string', 'timestamp')
    $fieldMap = $this->getView()->get('vv_field_types');
    $fieldType = $controlType ?: $fieldMap[$fieldName];

    // Remove prefix from field value
    if(!empty($prefix) && !empty($this->getView()->get('vv_obj')->$fieldName)) {
      $vv_obj = $this->getView()->get('vv_obj');
      $fieldValue = $vv_obj->$fieldName;
      $fieldValueTemp = str_replace($prefix, '', $fieldValue);
      $vv_obj->$fieldName = $fieldValueTemp;
      $this->getView()->set('vv_obj', $vv_obj);
    }
    
    if($fieldName != 'status' 
       && !isset($options['empty'])
       && (!isset($options['suppressBlank']) || !$options['suppressBlank'])) {
      // Cause any select (except status) to render with a blank option, even
      // if the field is required. This makes it clear when a value need to be set.
      // Note this will be ignored for non-select controls.
      $coptions['empty'] = true;
    }
  
    // A boolean field is a checkbox. Set the label and class to improve rendering
    // and accessibility.
    if($fieldType == 'boolean') {
      $coptions['label'] = $labelText;
      $coptions['class'] = 'form-check-input';
    }
    
    // Generate the form control or pass along the markup generated in a wrapper function
    $controlCode = empty($ctrlCode) ? $this->Form->control($fieldName, $coptions) : $ctrlCode;
    
    $vv_obj = $this->getView()->get('vv_obj');

    if($fieldName == 'plugin' && $this->action == 'edit') {
      return $this->statusControl($fieldName, 
                                  $vv_obj->$fieldName, 
                                  [
                                    'label' => __d('operation', 'configure.plugin'),
                                    'url' => [
                                      'plugin'      => null,
                                      'controller'  => StringUtilities::entityToClassname($vv_obj),
                                      'action'      => 'configure',
                                      $vv_obj->id
                                    ]
                                  ], 
                                  $labelText);
    }
    
    // If an attribute is frozen, inject a special link to unfreeze it, since
    // the attribute is read only and the admin can't simply uncheck the setting
    if($fieldName == 'frozen' && $vv_obj->frozen) {
      return $this->statusControl($fieldName, 
                                  __d('field', 'frozen'), 
                                  [
                                    'label' => __d('operation', 'unfreeze'),
                                    'url' => [
                                      'plugin'      => null,
                                      'controller'  => StringUtilities::entityToClassname($vv_obj),
                                      'action'      => 'unfreeze',
                                      $vv_obj->id
                                    ]
                                  ], 
                                  $labelText);
    }

    // Required fields are usually determined by the model validator, but for
    // related models the view (currently) has to pass the field as required in
    // $options. For fields of the form model.0.field, if $options['required']
    // is true we'll update the set of required fields so the * renders correctly.
    
    if(isset($options['required'])
       && $options['required']
       && preg_match('/(\w+).(\d+).(\w+)/', $fieldName, $matches)) {
      if(!in_array($matches[3], $this->reqFields)) {
        $this->reqFields[] = $matches[3];
      }
    }
    
    return $this->startLine($liClass)
           . $this->formNameDiv($fieldName, $labelText, $fieldType, $labelIsTextOnly)
           . ( !empty($prefix) ?
                 $this->formInfoWithPrefixDiv($controlCode, $prefix, $beforeField, $afterField) :
                 $this->formInfoDiv($controlCode, $beforeField, $afterField) )
           . $this->endLine();
  }
  
  /**
   * Emit a date/time form control.
   * This is a wrapper function for $this->control()
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $fieldName Form field
   * @param  string $dateType Standard, DateOnly, FromTime, ThroughTime
   * 
   * @return string  HTML for control
   */
  
  public function dateControl(string $fieldName, string $dateType=DateTypeEnum::Standard): string {
    if($this->action == 'view') {
      // return the date as plaintext
      $coptions = [];
      $entity = $this->getView()->get('vv_obj');
      if (!empty($entity->$fieldName)) {
        // Adjust the time back to the user's timezone
        if ($dateType == DateTypeEnum::DateOnly) {
          $controlCode = '<time>' . $entity->$fieldName->i18nFormat("yyyy-MM-dd", $this->getView()->get('vv_tz')) . '</time>';
        } else {
          $controlCode = '<time>' . $entity->$fieldName->i18nFormat("yyyy-MM-dd HH:mm:ss", $this->getView()->get('vv_tz')) . '</time>';
        }
      } else {
        $controlCode = '<div class="not-set">' . __d('information', 'notset') . '</div>';
      }
      // Return this to the generic control() function
      return $this->control($fieldName, $coptions, ctrlCode: $controlCode, labelIsTextOnly: true);
      
    } else {
      // A datetime field will be rendered as a plain text input with adjacent date and time pickers
      // that will interact with the field value. Allowing direct access to the input field is for
      // accessibility purposes.
  
      $pickerType = $dateType;
      // Special-case the very common "valid_from" and "valid_through" fields so we won't need
      // to specify their types in fields.inc.
      if ($fieldName == 'valid_from') {
        $pickerType = DateTypeEnum::FromTime;
      }
      if ($fieldName == 'valid_through') {
        $pickerType = DateTypeEnum::ThroughTime;
      }
  
      // Append the timezone to the label -- TODO: see that the timezone gets output to the display
      $label = __d('field', $fieldName . ".tz", [$this->_View->get('vv_tz')]);
  
      // Create the options array for the (text input) form control
      $coptions = [];
      $coptions['class'] = 'form-control datepicker';
  
      if ($pickerType == DateTypeEnum::DateOnly) {
        $coptions['placeholder'] = 'YYYY-MM-DD';
        $coptions['pattern'] = '\d{4}-\d{2}-\d{2}';
        $coptions['title'] = __d('field', 'datepicker.enterDate');
      } else {
        $coptions['placeholder'] = 'YYYY-MM-DD HH:MM:SS';
        $coptions['pattern'] = '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}';
        $coptions['title'] = __d('field', 'datepicker.enterDateTime');
      }
      $coptions['id'] = str_replace("_", "-", $fieldName);
  
      $entity = $this->getView()->get('vv_obj');
  
      // Default the picker date to today
      $now = FrozenTime::now();
      $pickerDate = $now->i18nFormat('yyyy-MM-dd');
  
      // Get the existing values, if present
      if(!empty($entity->$fieldName)) {
        // Adjust the time back to the user's timezone
        if($pickerType == DateTypeEnum::DateOnly) {
          $coptions['value'] = $entity->$fieldName->i18nFormat("yyyy-MM-dd", $this->getView()->get('vv_tz'));
        } else {
          $coptions['value'] = $entity->$fieldName->i18nFormat("yyyy-MM-dd HH:mm:ss", $this->getView()->get('vv_tz'));
        }
        $pickerDate = $entity->$fieldName->i18nFormat("yyyy-MM-dd", $this->getView()->get('vv_tz'));
      }
  
      // Set the date picker floor year value (-100 years)
      $pickerDateFT = new FrozenTime($pickerDate);
      $pickerDateFT = $pickerDateFT->subYears(100);
      $pickerFloor = $pickerDateFT->i18nFormat("yyyy-MM-dd");
  
      $date_picker_args = [
        'fieldName' => $fieldName,
        'pickerDate' => $pickerDate,
        'pickerType' => $pickerType,
        'pickerFloor' => $pickerFloor
      ];
  
      // Create a text field to hold our value and call the datePicker
      $controlCode = $this->Form->text($fieldName, $coptions)
        . $this->getView()->element('datePicker', $date_picker_args);
  
      // Specify a class on the <li> form control wrapper
      $liClass = "fields-datepicker";
      
      // Pass everything to the generic control() function
      return $this->control($fieldName, $coptions, ctrlCode: $controlCode, cssClass: $liClass);
    }
  }
  
  /**
   * End a set of form controls.
   *
   * @since  COmanage Registry v5.0.0
   * @return string Control Set end HTML
   */
  
  public function endControlSet(): string {
    $this->modelName = null;
    
    return "</ul>\n";
  }
  
  /**
   * End a form line.
   *
   * @since  COmanage Registry v5.0.0
   * @return string Line end HTML
   */
  
  protected function endLine(): string {
    return "</div></li>\n";
  }
  
  /**
   * Generate a form info (control, value) box.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $content    Content HTML
   * @param  string  $beforeField Markup to be placed before/above the field
   * @param  string  $afterField  Markup to be placed after/below the field
   * @return string              Form Info HTML
   */
  
  protected function formInfoDiv(string $content, 
                                 string $beforeField = '', 
                                 string $afterField = ''): string {
    $div  = '<div class="field-info">' . PHP_EOL;
    if(!empty($beforeField)) {
      $div .= $beforeField . PHP_EOL;
    }
    $div .= $content . PHP_EOL;
    if(!empty($afterField)) {
      $div .= $afterField . PHP_EOL;  
    }
    $div .= '</div>' . PHP_EOL;
    
    return $div;
  }

  /**
   * Generate a form info (control, value) box with a non editable prefix.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $content     Content HTML
   * @param  string  $prefix      Prefix value
   * @param  string  $beforeField Markup to be placed before/above the field
   * @param  string  $afterField  Markup to be placed after/below the field
   * @return string               Form Info HTML
   */

  protected function formInfoWithPrefixDiv(string $context, 
                                           string $prefix, 
                                           string $beforeField = '', 
                                           string $afterField = ''): string {
    $div  = '<div class="field-info">' . PHP_EOL;
    if(!empty($beforeField)) {
      $div .= $beforeField . PHP_EOL;
    }
    $div .= '<div class="input-group mb-3">' . PHP_EOL;
    $div .= '<div class="input-group-prepend">' . PHP_EOL;
    $div .= '<span class="input-group-text" id="basic-addon3">' . $prefix . '</span>';
    $div .= '</div>' . PHP_EOL;
    $div .= $context;
    $div .= '</div>';
    if(!empty($afterField)) {
      $div .= $afterField . PHP_EOL;
    }
    $div .= '</div>';

    return $div;
  }
  
  /**
   * Generate a form name (label, description) box.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $fieldName Form field
   * @param  string  $labelText Label text (fieldName language key used by default)
   * @param  string  $fieldType Type of field (string, boolean, timestamp, etc)
   * @param  boolean $labelIsTextOnly True if label should be text only. Otherwise false.
   * @return string             Form Name HTML
   */
  
  protected function formNameDiv(string $fieldName, string $labelText=null, string $fieldType, bool $labelIsTextOnly=false): string {
    $label = $labelText;
    $desc = null;
    
    // We'll accept a fieldName of the form other_models.0.foo for forms that
    // request associated data. Note, however, that model names for language
    // keys are OtherModel, so we'll need to inflect. 
    
    $mn = $this->modelName;
    $fn = $fieldName;
    
    if(strpos($fieldName, '.') !== false) {
      // othermodels.0.field
      
      $bits = explode('.', $fieldName, 3);
      $mn = Inflector::classify($bits[0]);
      $fn = $bits[2];
    }
    
    // First try to autogenerate the field label (if we weren't given one).
    
    $pluginDomain = (!empty($this->pluginName)
                      ? Inflector::underscore($this->pluginName)
                      : null);
    
    if(!$label) {
      // We autogenerate field labels and descriptions from the field name.
      
      // We loop over the field generation logic twice, first for a plugin
      // context (if set) and then generally (if no plugin localization was found).

      // We use $core as the variable for this loop, so the rest of the code
      // is easier to read (!$core = plugin)
      for($core = 0;$core < 2;$core++) {
        if(!$core && empty($this->pluginName)) {
          // No plugin set, just go to the core field checks
          continue;
        }

        // Is there a model specific key? For plugins, this will be in field.Model.Field

        $key = (!$core ? "field." : "") . "$mn.$fn";
        $label = __d(($core ? 'field' : $pluginDomain), $key);

        if($label == $key) {
          // Model specific label not found, try again for a general label

          $f = null;

          if(preg_match('/^(.*?)_id$/', $fn, $f)) {
            // Map foreign keys (foo_id) to the controller label
            $key = (!$core ? "controller." : "") . Inflector::camelize(Inflector::pluralize($f[1]));
            $label = __d(($core ? 'controller' : $pluginDomain), $key, [1]);

            if($key != $label) {
              break;
            }
          }
          
          // Just look up the key
          $key = (!$core ? "field." : "") . $fn;
          $label = __d(($core ? 'field' : $pluginDomain), $key);

          if($key != $label) {
            break;
          }
        } else {
          // If we found a key, break the loop
          break;
        }
      }
    }
    
    // We try to automagically determine if a description for the field exists by
    // looking for the corresponding .desc language translation.
    
    for($core = 0;$core < 2;$core++) {
      if(!$core && empty($this->pluginName)) {
        // No plugin set, just go to the core field checks
        continue;
      }

      $key = (!$core ? "field." : "") . "$mn.$fn.desc";
      $desc = __d(($core ? 'field' : $pluginDomain), $key);

      // If the description is the literal key we just generated, there is no description
      if($desc == $key) {
        $desc = null;
      } else {
        break;
      }
    }
    
    return '<div class="field-name">
      <div class="field-title">'
      . (!($labelIsTextOnly) && ($fieldType != 'boolean')
         ? $this->Form->label($fn, $label)
         : $label) 
      . ($this->editable
           && in_array($fn, $this->reqFields)
         ? ' <span class="required" aria-hidden="true">*</span>'
         . '<span class="visually-hidden">' . __d('field','required') . '</span>' 
         : '') . '
      </div>
      ' . ($desc ? '<div class="field-desc">' . $desc . '</div>' : "") .'
    </div>';
  }

  /**
   * Emit a source control for an MVEA that has a source_foo_id field pointing
   * to an External Identity attribute.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Entity   $entity   Entity to emit control for
   * @return string             Source HTML
   */
  
  public function sourceControl($entity): string {
    // eg: Identifiers
    $modelName = StringUtilities::entityToClassName($entity);
    // eg: source_identifier_id, or source_external_identity_role_id
    $sourceFK = $this->getView()->get('vv_source_fk');
    // eg: source_identifier - we need to construct this from the $sourceFK
    $sourceEntityName = substr($sourceFK, 0, strlen($sourceFK)-3);
    // In most cases $sourceModelName = $modelName, but not for PersonRoles
    $sourceModelName = substr(StringUtilities::foreignKeyToClassName($sourceFK), 6);

    $linkHtml = "";

    if(!empty($entity->$sourceFK)) {
      $linkHtml = $this->Html->Link(
        title: __d('controller', $sourceModelName, [1]),
        url: [
          'controller'  => $sourceModelName,
          'action'      => 'view',
          $entity->$sourceFK
        ]
      ) . ", " . 
      $this->Html->Link(
        title: __d('controller', 'ExternalIdentities', [1]),
        url: [
          'controller'  => 'external_identities',
          'action'      => 'view',
          $entity->$sourceEntityName->external_identity_id
        ]
      );
    }

    return $this->startLine()
           . $this->formNameDiv(
                fieldName: $sourceFK,
                labelText: __d('field', 'source'),
                fieldType: 'string'
             )
           . $linkHtml
           . $this->endLine();
  }

  /**
   * Generate a status control (a read only status with an optional link button).
   * 
   * @since  Registry Registry v5.0.0
   * @param  string  $fieldName Form field
   * @param  string  $status    Status text
   * @param  array   $link      Link information, including 'url', 'label', 'class', 'confirm'
   * @param  string  $labelText Label text (fieldName language key used by default)
   * @param  boolean $labelIsTextOnly true if <label> wrapper should not be included in the markup
   * @return string
   */
  
  public function statusControl(string $fieldName, 
                                string $status, 
                                array  $link=[], 
                                string $labelText = null,
                                bool   $labelIsTextOnly = false): string {
    $linkHtml = $status;
    
    if($link) {
      // Construct HTML for the requested link
      
      if(!empty($link['label'])) {
        // Create a separate link after $status
        
        $linkHtml .= " " . $this->Html->link(
          $link['label'],
          $link['url'],
          $link
        );
      } else {
        // Make $status the link
        
        $linkHtml = $this->Html->link(
          $status,
          $link['url'],
          // Just pass whatever other args are specified
          $link
        );
      }
    }
     
    return $this->startLine()
           . $this->formNameDiv($fieldName, $labelText, 'string', $labelIsTextOnly)
           . $this->formInfoDiv($linkHtml)
           . $this->endLine();
  }
  
  /**
   * Start a set of form controls.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $modelName Model name for form
   * @param  string  $action    Current action
   * @param  boolean $editable  True if controls are read/write, false for read only
   * @param  array   $reqFields Array of required fields
   * @param  object  $entity    Entity object (if set, null on add)
   * @return string
   */
  
  public function startControlSet(string $modelName, 
                                  string $action, 
                                  bool $editable, 
                                  array $reqFields,
                                  $entity=null,
                                  ?string $pluginName=null): string {
    $this->editable = $editable;
    $this->modelName = $modelName;
    $this->pluginName = $pluginName;
    $this->reqFields = $reqFields;
    $this->entity = $entity;
    $this->action = $action;

    return '<ul id="' . $action . '_' . $modelName . '" class="fields form-list">' . "\n";
  }
  
  /**
   * Start a form line.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $class Optional class to apply to the line
   * @return string
   */
  
  protected function startLine(string $class=null): string {
    $ret = '<li';
    
    if($class) {
      $ret .= ' class="' . $class . '"';
    }
    
    $ret .= '><div class="field">';
    
    return $ret;
  }
  
  /**
   * Emit a submit control.
   *
   * @since  Registry Registry v6.0.0
   * @param  string  $label Text for submit button
   * @return string
   */
  
  public function submit(string $label): string {
    return '<li class="fields-submit">
      <div class="field">
        <div class="field-name">
          <span class="required">* ' . __d('field', 'required') . '</span>
        </div>
        <div class="field-info">
          ' . $this->Form->submit($label) . '
        </div>
      </div>  
    </li>';
  }
}