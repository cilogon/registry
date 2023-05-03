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
   * @param  array   $config      Custom FormHelper configuration options
   * @param  string  $ctrlCode    Control code passed in from wrapper functions
   * @param  string  $cssClass    Start li css class passed in from wrapper functions
   * @return string  HTML for control
   */
  
  public function control(string $fieldName,
                          array  $options=[],
                          string $labelText=null,
                          array  $config=[],
                          string $ctrlCode=null,
                          string $cssClass=''): string {
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
    $fieldType = $fieldMap[$fieldName];
    
    // Collect any supplemental markup and/or JavaScript to pass along for field construction.
    // Suppliment is an array: supplement['beforeField' => 'string', 'afterField' => 'string'].
    $fieldSupplement = !empty($config['supplement']) ? $config['supplement'] : [];
  
    // For special fields that should not include <label> markup, allow fields to make the label text only
    $labelIsTextOnly = !empty($config['labelIsTextOnly']) ? $config['labelIsTextOnly'] : false;

    // Remove prefix from field value
    if(isset($config['prefix'], $this->getView()->get('vv_obj')->$fieldName)) {
      $vv_obj = $this->getView()->get('vv_obj');
      $fieldValue = $vv_obj->$fieldName;
      $fieldValueTemp = str_replace($config['prefix'], '', $fieldValue);
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
                                      'controller'  => 'reports',
                                      'action'      => 'configure',
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
           . ( !empty($config['prefix']) ?
                 $this->formInfoWithPrefixDiv($controlCode, $config['prefix'], $fieldSupplement) :
                 $this->formInfoDiv($controlCode, $fieldSupplement) )
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
    // A datetime field will be rendered as a plain text input with adjacent date and time pickers
    // that will interact with the field value. Allowing direct access to the input field is for
    // accessibility purposes.
    
    $pickerType = $dateType;
    // Special-case the very common "valid_from" and "valid_through" fields so we won't need
    // to specify their types in fields.inc.
    if($fieldName == 'valid_from') {
      $pickerType = DateTypeEnum::FromTime;
    }
    if($fieldName == 'valid_through') {
      $pickerType = DateTypeEnum::ThroughTime;
    }
    
    // Append the timezone to the label -- TODO: see that the timezone gets output to the display
    $label = __d('field', $fieldName.".tz", [$this->_View->get('vv_tz')]);
    
    // Create the options array for the (text input) form control
    $coptions = [];
    $coptions['class'] = 'form-control datepicker';
    
    if($pickerType == DateTypeEnum::DateOnly) {
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
      'fieldName'   => $fieldName,
      'pickerDate'  => $pickerDate,
      'pickerType'  => $pickerType,
      'pickerFloor' => $pickerFloor
    ];
    
    // Create a text field to hold our value and call the datePicker
    $controlCode = $this->Form->text($fieldName, $coptions)
      . $this->getView()->element('datePicker', $date_picker_args);
  
    // Specify a class on the <li> form control wrapper
    $liClass = "fields-datepicker";
    
    // Pass everything to the generic control() function
    return $this->control($fieldName, $coptions, '', [], $controlCode, $liClass);
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
    return "</li>\n";
  }
  
  /**
   * Generate a form info (control, value) box.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $content    Content HTML
   * @param  string  $supplement Supplemental markup to place before and/or after the field control 
   * @return string              Form Info HTML
   */
  
  protected function formInfoDiv(string $content, array $supplement): string {
    $div  = '<div class="field-info">' . PHP_EOL;
    if(!empty($supplement['beforeField'])) {
      $div .= $supplement['beforeField'] . PHP_EOL;
    }
    $div .= $content . PHP_EOL;
    if(!empty($supplement['afterField'])) {
      $div .= $supplement['afterField'] . PHP_EOL;  
    }
    $div .= '</div>' . PHP_EOL;
    
    return $div;
  }

  /**
   * Generate a form info (control, value) box with a non editable prefix.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $content    Content HTML
   * @param  string  $prefix     Prefix value
   * @param  string  $supplement Supplemental markup to place before and/or after the field control
   * @return string              Form Info HTML
   */

  protected function formInfoWithPrefixDiv(string $context, string $prefix, array $supplement): string {
    $div  = '<div class="field-info">' . PHP_EOL;
    if(!empty($supplement['beforeField'])) {
      $div .= $supplement['beforeField'] . PHP_EOL;
    }
    $div .= '<div class="input-group mb-3">' . PHP_EOL;
    $div .= '<div class="input-group-prepend">' . PHP_EOL;
    $div .= '<span class="input-group-text" id="basic-addon3">' . $prefix . '</span>';
    $div .= '</div>' . PHP_EOL;
    $div .= $context;
    $div .= '</div>';
    if(!empty($supplement['afterField'])) {
      $div .= $supplement['afterField'] . PHP_EOL;
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
    
    if(!$label) {
      // We autogenerate field labels and descriptions from the field name.
      // Fields of the form foo_id map to the singular form of registry.ct.foos.
      // All others map first to registry.fd.Model.foo, then to registry.fd.foo
      // if no Model specific key is found.
      
      $label = __d('field', $mn.".".$fn);
      
      if($label == $mn.".".$fn) {
        // Model specific label not found, try again
        
        $f = null;
        
        if(preg_match('/^(.*?)_id$/', $fn, $f)) {
          // Map foreign keys (foo_id) to the controller label
          $label = __d('controller', Inflector::camelize(Inflector::pluralize($f[1])), [1]);
        } else {
          // Just look up the key
          $label = __d('field', $fn);
        }
      }
    }
    
    // We try to automagically determine if a description for the field exists by
    // looking for the corresponding .desc language translation.
    
    $desc = __d('field', $mn.".".$fn.".desc");
    
    if($desc == $mn.".".$fn.".desc") {
      $desc = __d('field', $fn.".desc");
    }
    
    // If the description is the literal key we just generated, there is no description
    if($desc == $fn.".desc") {
      $desc = null;
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
      ' . ($desc ? '<span class="field-desc">' . $desc . '</span>' : "") .'
    </div>';
  }
  
  /**
   * Generate a status control (a read only status with an optional link button).
   * 
   * @since  Registry Registry v5.0.0
   * @param  string  $fieldName Form field
   * @param  string  $status    Status text
   * @param  array   $link      Link information, including 'url', 'label', 'class', 'confirm'
   * @param  string  $labelText Label text (fieldName language key used by default)
   * @param  array   $config    Custom FormHelper configuration options
   * @return string
   */
  
  public function statusControl(string $fieldName, 
                                string $status, 
                                array $link=[], 
                                string $labelText=null,
                                array $config=[]): string {
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
  
    // For special fields that should not include <label> markup, allow fields to make the label text only
    $labelIsTextOnly = !empty($config['labelIsTextOnly']) ? $config['labelIsTextOnly'] : false;
     
    return $this->startLine()
           . $this->formNameDiv($fieldName, $labelText, 'string', $labelIsTextOnly)
           . $linkHtml
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
                                  $entity=null): string {
    $this->editable = $editable;
    $this->modelName = $modelName;
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
    
    $ret .= '>';
    
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
      <div class="field-name">
        <span class="required">* ' . __d('field', 'required') . '</span>
      </div>
      <div class="field-info">
        ' . $this->Form->submit($label) . '
      </div>
    </li>';
  }
}