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

use \Cake\Utility\Inflector;
use Cake\View\Helper;

class FieldHelper extends Helper {
  public $helpers = ['Form', 'Html', 'Url'];
  
  // Is this read-only or read-write?
  protected $editable = true;
  
  // Our current model name
  protected $modelName = null;
  
  // The list of required fields
  protected $reqFields = [];
  
  // The current entity, if edit or view
  protected $entity = null;
  
  /**
   * Emit an informational banner.
   *
   * @since  COmanage Registry v6.0.0
   * @param  string $info Information string
   * @return string       HTML for banner
   */
  
  public function banner(string $info) {
    return '<div class="co-info-topbox">
  <em class="material-icons">info</em>
  ' . $info . '
</div>';
  }
  
  /**
   * Emit a form control.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $fieldName Form field
   * @param  array   $options   FormHelper control options
   * @param  string  $labelText Label text (fieldName language key used by default)
   * @return string  HTML for control
   */
  
  public function control(string $fieldName,
                          array  $options=[],
                          string $labelText=null) {
    $coptions = $options;
    $coptions['label'] = false;
    $coptions['readonly'] = !$this->editable || (isset($options['readonly']) && $options['readonly']);
    // Selects, Checkboxes, and Radio Buttons use "disabled"
    $coptions['disabled'] = $coptions['readonly'];
    
    // Generate HTML for the control itself
    $liClass = "";
    
    // Handle datetime controls specially
    if($fieldName == 'valid_from' || $fieldName == 'valid_through') {
      // Append the timezone to the label
      $label = __d('field', $fieldName.".tz", [$this->_View->get('vv_tz')]);
      
      // A datetime field will be rendered as plain text input with adjacent date and time pickers
      // that will interact with the field value. Allowing direct access to the input field is for
      // accessibility purposes.
      $coptions['class'] = 'form-control datepicker';
      $coptions['placeholder'] = 'YYYY-MM-DD HH:MM:SS'; // TODO: test for date-only inputs and send only the date
      $coptions['id'] = $fieldName;
      
      $entity = $this->_View->get('vv_obj');
      
      $pickerDate = '';
      if(!empty($entity->$fieldName)) {
        // Adjust the time back to the user's timezone
        $coptions['value'] = $entity->$fieldName->i18nFormat("yyyy-MM-dd HH:mm:ss", $this->_View->get('vv_tz'));
        $pickerDate = $entity->$fieldName->i18nFormat("yyyy-MM-dd", $this->_View->get('vv_tz'));
      }
      
      // Create a text field to hold our value.
      $controlCode = $this->Form->text($fieldName, $coptions);
      
      // Create a date/time picker. The yyyy-MM-dd format is set above in $pickerDate.
      $pickerId = 'datepicker-' . $fieldName;
      $pickerTarget = $fieldName;
      $pickerTimed = true; // TODO: set false if date-only
      $pickerAmPm = false; // TODO: allow change between AM/PM and 24-hour mode
      
      $controlCode .= '  
        <script type="module">
          import CmDateTimePicker from "' . $this->Url->script('comanage/components/datepicker/cm-datetimepicker.js') . '";
          const app = Vue.createApp({
            data() {
              return {
                id: "' . $pickerId . '",
                target: "' . $pickerTarget . '",
                date: "' . $pickerDate . '",
                timed: ' . ($pickerTimed ? 'true' : 'false') . ',
                ampm: ' . ($pickerAmPm ? 'true' : 'false') . ',
                txt: {
                  hour: "' . __d('field', 'datepicker.hour') . '",
                  minute: "' . __d('field', 'datepicker.minute') . '",
                  am: "' . __d('field', 'datepicker.am') . '",
                  pm: "' . __d('field', 'datepicker.pm') . '"
                }
              }
            },
            components: {
              CmDateTimePicker
            }
          });
          
          // Add custom global directives available to all child components.
          // "clickout" allows us to pass a function to a click outside behavior which
          // is registered and destroyed as the component is mounted and unmounted.
          app.directive("clickout", {
            mounted(el, binding, vnode) {
              el.clickOutEvent = function(event) {
                if (!(el === event.target || el.contains(event.target))) {
                  binding.value(event, el);
                }
              };
              document.body.addEventListener("click", el.clickOutEvent);
            },
            unmounted(el) {
              document.body.removeEventListener("click", el.clickOutEvent);
            }
          });
          
          app.mount("#' . $pickerId . '-container");
        </script>
        <div id="' . $pickerId . '-container">
          <cm-date-time-picker
            :id="id"
            :target="target"
            :date="date"
            :timed="timed" 
            :ampm="ampm"
            :txt="txt">
          </cm-date-time-picker>
        </div>';
      
      $liClass = "fields-datepicker";
    } else {
      if($fieldName != 'status' 
         && !isset($options['empty'])
         && (!isset($options['suppressBlank']) || !$options['suppressBlank'])) {
        // Cause any select (except status) to render with a blank option, even
        // if the field is required. This makes it clear when a value need to be set.
        // Note this will be ignored for non-select controls.
        $coptions['empty'] = true;
      }
      
      $controlCode = $this->Form->control($fieldName, $coptions);
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
           . $this->formNameDiv($fieldName, $labelText)
           . $this->formInfoDiv($controlCode)
           . $this->endLine();
  }
  
  /**
   * End a set of form controls.
   *
   * @since  COmanage Registry v5.0.0
   * @return string Control Set end HTML
   */
  
  public function endControlSet() {
    $this->modelName = null;
    
    return "</ul>\n";
  }
  
  /**
   * End a form line.
   *
   * @since  COmanage Registry v5.0.0
   * @return string Line end HTML
   */
  
  protected function endLine() {
    return "</li>\n";
  }
  
  /**
   * Generate a form info (control, value) box.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $content Content HTML
   * @return string           Form Info HTML
   */
  
  protected function formInfoDiv(string $content) {
    return '<div class="field-info">
      ' . $content . '
    </div>';
  }
  
  /**
   * Generate a form name (label, description) box.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $fieldName Form field
   * @param  string  $labelText Label text (fieldName language key used by default)
   * @return string             Form Name HTML
   */
  
  protected function formNameDiv(string $fieldName, string $labelText=null) {
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
      . ($this->editable 
         ? $this->Form->label($fn, $label)
         : $label) 
      . ($this->editable
           && in_array($fn, $this->reqFields)
         ? ' <span class="required">*</span>' 
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
   * @return string
   */
  
  public function statusControl(string $fieldName, string $status, array $link=[], string $labelText=null): string {
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
           . $this->formNameDiv($fieldName, $labelText)
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
  
  public function startControlSet(string $modelName, string $action, bool $editable, array $reqFields, $entity=null) {
    $this->editable = $editable;
    $this->modelName = $modelName;
    $this->reqFields = $reqFields;
    $this->entity = $entity;
    
    return '<ul id="' . $action . '_' . $modelName . '" class="fields form-list">' . "\n";
  }
  
  /**
   * Start a form line.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string  $class Optional class to apply to the line
   * @return string
   */
  
  protected function startLine(string $class=null) {
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
  
  public function submit(string $label) {
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