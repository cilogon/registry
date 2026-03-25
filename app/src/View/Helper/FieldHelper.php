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

declare(strict_types = 1);

namespace App\View\Helper;

use App\Lib\Enum\DateTypeEnum;
use App\Lib\Util\StringUtilities;
use Cake\I18n\FrozenTime;
use Cake\Utility\Inflector;
use Cake\View\Helper;
use DOMDocument;

class FieldHelper extends Helper {
 public array $helpers = ['Form', 'Html'];

  /**
   * List of predefined editable form actions
   */
  public const EDITABLE_ACTIONS = [
    'add', 'edit', 'manage' // CRUD actions
  ];

  // Is this read-only or read-write?
  protected bool $editable = true;
  
  // Our current model name
  protected ?string $modelName = null;
  
  // The plugin we are rendering within, if set
  protected ?string $pluginName = null;

  // The list of required fields
  protected array $reqFields = [];

  // The field types
  protected array $fieldTypes = [];

  // The current entity, if edit or view
  protected ?object $entity = null;

  // The current action
  protected ?string $action = null;


  /**
   * Constructor hook method.
   *
   * @param   array  $config The configuration settings provided to this helper.
   *
   * @return void
   * @since  COmanage Registry v5.0.0
   */
  public function initialize(array $config): void
  {
    parent::initialize($config);

    $this->reqFields = $this->getView()->get('vv_required_fields');
    $this->modelName = $this->getView()->getName();
    $this->action = $this->getView()->get('vv_action');
    $vv_is_editable = filter_var($this->getView()->get('vv_is_editable'),  FILTER_VALIDATE_BOOLEAN);
    $this->editable = \in_array($this->action, self::EDITABLE_ACTIONS, true) || $vv_is_editable;
    $this->pluginName = $this->getView()->getPlugin();
    $this->entity = $this->getView()->get('vv_obj');
    $this->fieldTypes = $this->getView()->get('vv_field_types');
  }

  /**
   * We autogenerate field labels and descriptions from the field name.
   *
   * @param   string  $fieldName
   *
   * @return array
   * @since  COmanage Registry v5.0.0
   */
  public function calculateLabelAndDescription(string $fieldName): array
  {
    $desc = null;
    $label = null;

    // First, try to autogenerate the field label (if we weren't given one).
    $pluginDomain = StringUtilities::pluginToTextDomain($this->getPluginName());

    $modelName = $this->getModelName();
    // We try to automagically determine if a description for the field exists by
    // looking for the corresponding .desc language translation.
    // We autogenerate field labels and descriptions from the field name.

    // We loop over the field generation logic twice, first for a plugin
    // context (if set) and then generally (if no plugin localization was found).

    // We use $core as the variable for this loop, so the rest of the code
    // is easier to read (!$core = plugin)
    for($core = 0;$core < 2;$core++) {
      if(!$core && empty($this->pluginName)) {
        // No plugin set, go to the core field checks
        continue;
      }

      // Is there a model specific key? For plugins, this will be in field.Model.Field

      $key = (!$core ? 'field.' : '') . "$modelName.$fieldName";
      $label = __d(($core ? 'field' : $pluginDomain), $key);

      if($label === $key) {
        // Model-specific label isn't found, try again for a general label

        $f = null;

        if(preg_match('/^(.*?)_id$/', $fieldName, $f)) {
          // Map foreign keys (foo_id) to the controller label
          $key = (!$core ? 'controller.' : '') . Inflector::camelize(Inflector::pluralize($f[1]));
          $label = __d(($core ? 'controller' : $pluginDomain), $key, [1]);

          if($key !== $label) {
            break;
          }
        }

        // Look up the key
        $key = (!$core ? 'field.' : '') . $fieldName;
        $label = __d(($core ? 'field' : $pluginDomain), $key);

        if($key !== $label) {
          break;
        }
      } else {
        // If we found a key, break the loop
        break;
      }
    }
    // We try to automagically determine if a description for the field exists by
    // looking for the corresponding .desc language translation.

    for($core = 0;$core < 2;$core++) {
      if(!$core && empty($this->pluginName)) {
        // No plugin set, just go to the core field checks
        continue;
      }

      $key = (!$core ? 'field.' : '') . "$modelName.$fieldName.desc";
      $desc = __d(($core ? 'field' : $pluginDomain), $key);

      if($desc === $key) {
        $key = (!$core ? 'field.' : '') . "$fieldName.desc";
        $desc = __d(($core ? 'field' : $pluginDomain), $key);

        if($key !== $desc) {
          // If we found a description, break the loop
          break;
        }
      }

      // If the description is the literal key we just generated, there is no description
      if($desc === $key) {
        $desc = null;
      } else {
        break;
      }
    }

    return [$label, $desc];
  }

  /**
   * Calculate the list of classes for the li element
   *
   * @return string
   * @since  COmanage Registry v5.0.0
   */
  public function calculateLiClasses(): string
  {
    $fieldName = $this->getView()->get('fieldName');
    $vv_field_arguments = $this->getView()->get('vv_field_arguments');

    // Get the fieldtype directly from the configuration or calculate it
    // The latter will always work for simple model forms. The first one is used
    // for more complex use cases
    $fieldType = $vv_field_arguments['fieldType'] ?? $this->getFieldType($fieldName);

    // Class calculation by field Type
    $classes = match ($fieldType) {
      'date',
      'datetime',
      'timestamp'     => 'fields-datepicker ',
      default         => ''
    };

    // Class calculation by field name
    $classes .= match ($fieldName) {
      'source_record' => 'source-record ',
      'retry_interval',
      'login'         => 'subfield ',
      default         => ''
    };

    // Class calculation by type of Info Div
    if(isset($vv_field_arguments['autocomplete'])) {
      $classes .= 'fields-people-autocomplete ';
    }

    // Each field should have a class like `fields-<name of the field>`
    $field = $vv_field_arguments['fieldNameAlias'] ?? $fieldName ?? 'unknown';
    $classes .= " fields-$field";

    return $classes;
  }

  /**
   * Construct the SPA field element
   *
   * @param   string  $element         HTML element created with the CAKEPHP HTML Helper
   * @param   string  $vueElementName  The name of the JavaScript module
   *
   * @return string
   * @since  COmanage Registry v5.0.0
   */
  public function constructSPAField(string $element, string $vueElementName): string {
    // Parse the ID attribute
    $regexId = '/id="(.*?)"/m';
    preg_match_all($regexId, $element, $matchesId, PREG_SET_ORDER, 0);

    // Parse the Name attribute
    $regexName = '/name="(.*?)"/m';
    preg_match_all($regexName, $element, $matchesName, PREG_SET_ORDER, 0);

    // Parse the Class attribute
    $regexClass = '/class="(.*?)"/m';
    preg_match_all($regexClass, $element, $matchesClass, PREG_SET_ORDER, 0);

    // Parse the Value attribute
    // XXX This will not work properly if the input element is a select element
    if (!empty($matchesClass[0][1])
      && !str_contains($matchesClass[0][1], 'select')
    ) {
      $regexClass = '/value="(.*?)"/m';
      preg_match_all($regexClass, $element, $matchesValue, PREG_SET_ORDER, 0);
    }

    if(!empty($matchesId[0][1]) && !empty($matchesName[0][1])) {
      $vueElementProperties = [
        'htmlId' => $matchesId[0][1] . '-picker',
        'fieldName' => $matchesName[0][1],
        'containerClasses' => $matchesClass[0][1],
        'type' => 'field',
        // we want the label to be an empty string to hide the default label introduced by the module.
        'label' => ''
      ];

      if (isset($matchesValue[0][1])) {
        $vueElementProperties['inputValue'] = $matchesValue[0][1];
      }

      return $this->getView()->element($vueElementName, $vueElementProperties);
    }

    // Fallback to an error element
    return $this->getView()->element('elementFallback');
  }

  /**
   * Emit a date/time form control.
   * This is a wrapper function for $this->control()
   *
   * @param   string       $fieldName  Form field
   * @param   string       $dateType   Standard, DateOnly, FromTime, ThroughTime
   * @param   array|null   $fieldArgs
   *
   * @return string HTML element
   * @since  COmanage Registry v5.0.0
   */

  public function dateField(string $fieldName,
                            string $dateType = DateTypeEnum::Standard,
                            array  $fieldArgs = null): string
  {
    // Initialize
    $dateFormat = $dateType === DateTypeEnum::DateOnly ? 'yyyy-MM-dd' : 'yyyy-MM-dd HH:mm:ss';
    $dateTitle = $dateType === DateTypeEnum::DateOnly ? 'datepicker.enterDate' : 'datepicker.enterDateTime';
    $datePattern = $dateType === DateTypeEnum::DateOnly ? 
      '\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])' : 
      '\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01]) (0\d|1\d|2[0-3]):(0\d|[1-5]\d):(0\d|[1-5]\d)';
    $queryParams = $this->getView()->getRequest()->getQueryParams();

    $date_object = match(true) {
      // filtering block
      !empty($queryParams[$fieldName])                                   => FrozenTime::parse($queryParams[$fieldName]),
      // Petition View/ Value saved as string
      isset($fieldArgs['default']) && is_string($fieldArgs['default'])   => FrozenTime::parse($fieldArgs['default']),
      // Petition View/ Value saved a FronzenTime
      isset($fieldArgs['default'])
      && is_a($fieldArgs['default'], 'Cake\I18n\FrozenTime')             => $fieldArgs['default'],
      // Table record/ Retrieve it from the Entity object
      default                                                            => $this->getEntity()?->$fieldName,
    };
    // Create the options array for the (text input) form control
    $coptions = [];

    // A datetime field will be rendered as a plain text input with adjacent date and time pickers
    // that will interact with the field value. Allowing direct access to the input field is for
    // accessibility purposes.

    // Special-case the very common "valid_from" and "valid_through" fields, so we won't need
    // to specify their types in fields.inc.
    $pickerTypeName = $fieldArgs['fieldNameAlias'] ?? $fieldName;
    $pickerType = match ($pickerTypeName) {
      'valid_from' => DateTypeEnum::FromTime,
      'valid_through' => DateTypeEnum::ThroughTime,
      default => $dateType
    };

    // Set the attributes for the field
    $coptions['class'] = 'form-control datepicker ' . $pickerType;
    $coptions['placeholder'] = $dateFormat;
    $coptions['pattern'] = $datePattern;
    $coptions['title'] = __d('field', $dateTitle);

    $coptions['id'] = str_replace('_', '-', $fieldName);


    // Default the picker date to today
    $now = FrozenTime::now();
    $pickerDate = $now->i18nFormat($dateFormat);

    // Get the existing values, if present
    if($date_object !== null) {
      // Adjust the time back to the user's timezone
      $tz = $this->getView()->get('vv_tz');
      if($tz) {
        $date_object = $date_object->setTimezone($tz);
      }
      $coptions['value'] = $date_object->i18nFormat($dateFormat);
      $pickerDate = $date_object->i18nFormat($dateFormat);
    }

    // ACTION VIEW or Readonly Field
    // The latter applies for the attribute collection view
    // For the Attribute Collection View we also need a hidden field
    if($this->action == 'view'
      ||
      (isset($fieldArgs['readonly']) && $fieldArgs['readonly'])
    ) {
      // return the date as plaintext
      // Add a hidden field
      $element = $this->getView()->element('form/notSetDiv', [], [
        'cache' => '_html_elements',
      ]);
      if ($date_object !== null) {
        // Adjust the time back to the user's timezone
        $element = $this->Form->hidden($fieldName, $coptions)
        . '<time>' . $date_object->i18nFormat($dateFormat) . '</time>';
      }

      // Return this to the generic control() function
      return $element;
    }

    // Set the date picker floor year value (-100 years)()
    $pickerDateFT = new FrozenTime($pickerDate);
    $pickerDateFT = $pickerDateFT->subYears(100);
    $pickerFloor = $pickerDateFT->i18nFormat($dateFormat);

    $date_picker_args = [
      'fieldName' => $fieldName,
      'pickerDate' => $pickerDate,
      'pickerType' => $pickerType,
      'pickerFloor' => $pickerFloor,
    ];

    $fieldLabel = '';
    if(!empty($fieldArgs['label'])) {
      $fieldLabel = $this->Form->label($fieldName, $fieldArgs['label']);
    }
    // Create a text field to hold our value and call the datePicker
    return $fieldLabel                                              // label
      . $this->Form->text($fieldName, $coptions)                    // hidden input
      . $this->getView()->element('datePicker', $date_picker_args); // datepicker field
  }

  /**
   * Create the actual Form element
   *
   * @param   string       $fieldName             Form field
   * @param   array|null   $fieldOptions          The second parameter of the Form->control helper. List of element options
   * @param   string|null  $fieldLabel            Custom label text. Applicable to checkboxes ONLY
   * @param   string       $fieldPrefix           If the field has a special prefix provide the value
   * @param   string|null  $fieldType             Field type to override the one calculated from the schema
   * @param   array|null   $fieldSelectOptions    Options array to override the one calculated options from the AutoPopulate property
   *                                              fieldType has to be 'select'
   * @param   string|null  $fieldNameAlias        Used for the Petition Attribute Collection form. The form uses generic field name.
   *                                              The variable is used to map the generic field name to the actual enrollment attribute name
   * @oaran   bool|null    $labelIsTextOnly       A parameter to force rending a field name/label as plain text with no <label> tag.
   *
   * @return string  HTML element
   * @since  COmanage Registry v5.0.0
   */
  public function formField(string $fieldName,
                            array  $fieldOptions = null,
                            string $fieldLabel = null,
                            string $fieldPrefix = '',
                            string $fieldType = null,
                            array  $fieldSelectOptions = null,
                            string $fieldNameAlias = null,
                              bool $labelIsTextOnly = null): string
  {
    $fieldArgs = $fieldOptions ?? [];
    $fieldArgs['label'] = $fieldOptions['label'] ?? false;
    $fieldArgs['readonly'] = !$this->editable
                             || (isset($fieldOptions['readonly']) && $fieldOptions['readonly'])
                             || ($fieldName == 'plugin' && $this->action == 'edit');

    // Selects, Checkboxes, and Radio Buttons use "disabled"
    // XXX For this use case we need to add a hidden input field. If we do not we will not be able
    //     to post the value
    $fieldArgs['disabled'] = $fieldArgs['readonly'];

    // required can be overridden by the fields.inc, but start with the default expectation
    $fieldArgs['required'] = $this->isReqField($fieldName);

    if(isset($fieldOptions['required'])) {
      // This could be either true or false
      $fieldArgs['required'] = $fieldOptions['required'];
    }

    // Cause any select (except status) to render with a blank option, even
    // if the field is required. This makes it clear when a value needs to be set.
    // Note this will be ignored for non-select controls.
    $fieldArgs['empty'] = !\in_array($fieldName, ['status', 'sync_status_on_delete'], true)
                          || (isset($fieldOptions['empty']) && !empty($fieldOptions['empty']));

    // Check if the empty option comes with a value
    if($fieldArgs['empty']
       && isset($fieldOptions['empty'])
       && \is_bool($fieldOptions['empty'])) {
      $fieldArgs['empty'] = $fieldOptions['empty'];
    }

    if(!empty($fieldOptions['all'])) {
      $optionName = lcfirst(StringUtilities::foreignKeyToClassName($fieldName));
      $optionValues = $this->getView()->get($optionName);
      $optionValues = [
        '-1' => $fieldOptions['all'],
        ...$optionValues
      ];
      $this->getView()->set($optionName, $optionValues);
    }

    // Is this multiple select?
    $fieldArgs['multiple'] = !empty($fieldOptions['multiple']);

    // Manipulate the vv_object for the hasPrefix use case
    $this->handlePrefix($fieldPrefix, $fieldName);

    // Get the field type from the map of fields (e.g. 'boolean', 'string', 'timestamp')
    $fieldType = $fieldType ?? $this->getFieldType($fieldName);
    // $fieldType=select requires the $fieldSelectOptions. If the options are empty, we will
    // force the usage of the default option
    if(empty($fieldSelectOptions) && $fieldType === 'select') {
      $fieldType = '';
    }
    
    // Checkbox labels need special handling
    if($fieldType == 'boolean') {
      [$cbLabel] = $this->calculateLabelAndDescription($fieldName);
      $fieldLabel = $fieldLabel ?? $cbLabel;
    }
    
    // Generate the form control or pass along the markup generated in a wrapper function
    return match($fieldType) {
      // A boolean field is a checkbox. Set the label and class to improve rendering
      // and accessibility.
      'boolean'   => $this->Form->control($fieldName, [
                                            // First import and then overwrite
                                            ...$fieldArgs,
                                            'label' => $fieldLabel,
                                            'class' => 'form-check-input',
                                          ]),
      'select'    => $this->Form->select($fieldName, $fieldSelectOptions, $fieldArgs),
      'text'      => $this->Form->textarea($fieldName,  [
                                            ...$fieldArgs,
                                            'id' => Inflector::dasherize($fieldName) // Cake 4 does not automatically assign this ID...
                                          ]),
      'date'      => $this->dateField(fieldName: $fieldName, dateType: DateTypeEnum::DateOnly, fieldArgs: $fieldArgs),
      'datetime',
      'timestamp' => $this->dateField(fieldName: $fieldName, fieldArgs: $fieldArgs),
      default     => $this->Form->control($fieldName, $fieldArgs)
    };
  }

  /**
   * @return object|null
   */
  public function getEntity(): ?object
  {
    return $this->entity;
  }

  /**
   * @param   string  $field
   *
   * @return string|null
   */
  public function getFieldType(string $field): ?string
  {
    return $this->fieldTypes[$field] ?? null;
  }

  /**
   * @return array
   */
  public function getFieldTypes(): array
  {
    return $this->fieldTypes;
  }

  /**
   * @return string|null
   */
  public function getModelName(): ?string
  {
    return $this->modelName;
  }

  /**
   * @return string|null
   */
  public function getPluginName(): ?string
  {
    return $this->pluginName;
  }

  /**
   * @return array
   */
  public function getReqFields(): array
  {
    return $this->reqFields;
  }

  /**
   * For the records that have a value like co_x.value, and we want to handle
   * the value separate from the field
   *
   * @param   string  $fieldPrefix   e.g. co_2.
   * @param   string  $fieldName     e.g. username
   *
   * @return void
   */
  protected function handlePrefix(string $fieldPrefix, string $fieldName): void
  {
    // Remove prefix from field value
    if(!empty($fieldPrefix) && !empty($this->getEntity()->$fieldName)) {
      $fieldValue = $this->getEntity()->$fieldName;
      $this->getEntity()->$fieldName = str_replace($fieldPrefix, '', $fieldValue);
      $this->getView()->set('vv_obj', $this->getEntity());
    }
  }

  /**
   * @return bool
   */
  public function isEditable(): bool
  {
    return $this->editable;
  }

  /**
   * Enable Form Edit mode. This will allow fields to be editable
   * and the submit button will be rendered
   *
   * @return void
   */
  public function enableFormEditMode(): void
  {
    $this->editable = true;
  }

  /**
   * Disable Form's edit mode. Fields will be become readonly/disabled
   * and the submit button will be removed from the DOM
   *
   * @return void
   */
  public function disableFormEditMode(): void
  {
    $this->editable = false;
  }

  /**
   * @param   string  $field
   *
   * @return bool
   */
  public function isReqField(string $field): bool
  {
    return \in_array($field, $this->reqFields, true);
  }

  /**
   * Emit a source control for an MVEA that has a source_foo_id field pointing
   * to an External Identity attribute.
   *
   * @param   Entity  $entity  Entity to emit control for
   *
   * @return string             Source Link
   * @since  COmanage Registry v5.0.0
   */
  public function sourceLink($entity): string
  {
    // eg: Identifiers
    // eg: source_identifier_id, or source_external_identity_role_id
    $sourceFK = $this->getView()->get('vv_source_fk');
    // e.g.: source_identifier - we need to construct this from the $sourceFK
    $sourceEntityName = substr($sourceFK, 0, strlen($sourceFK) - 3);
    // In most cases, $sourceModelName = $modelName, but not for PersonRoles
    $sourceModelName = substr(StringUtilities::foreignKeyToClassName($sourceFK), 6);

    $link = '';
    if (!empty($entity->$sourceFK)) {
      $link .= $this->Html->Link(
        title: __d('controller', $sourceModelName, [1]),
        url:   [
                 'controller' => $sourceModelName,
                 'action'     => 'view',
                 $entity->$sourceFK
               ]
      );
    }

    if (!empty($entity->$sourceEntityName)) {
      $link .= ', ' . $this->Html->Link(
          title: __d('controller', 'ExternalIdentities', [1]),
          url:   [
                   'controller' => 'external_identities',
                   'action'     => 'view',
                   $entity->$sourceEntityName->external_identity_id
                 ]
        );
    }
    return $link;
  }

  /**
   * Iterate over form arguments, parse the generated HTML element using XMLReader,
   * and inject a hidden input field if the element (e.g., select, checkbox, radio) is disabled.
   * Finally, outputs the original HTML element.
   *
   * @param string $element
   * @param array $formArguments An array containing options/attributes for the HTML form element.
   * @return void
   * @since  COmanage Registry v5.1.0
   */
  public function getElementsForDisabledInput(string $element, array $formArguments): void
  {
    $orginalElement = $this->getView()->element($element, ['arguments' => $formArguments]);
    if ($orginalElement) {
      $htmlObj = new DOMDocument();
      $htmlObj->loadHTML($orginalElement, LIBXML_NOERROR);
      if($htmlObj->getElementsByTagName('select')->length > 0) {
        // Check if it is disabled. If it is then print a hidden element
        foreach($htmlObj->getElementsByTagName('select')->item(0)->attributes as $attr) {
          if($attr->name == 'disabled' && $attr->value == 'disabled') {
            print $this->getView()->Form->hidden($formArguments['fieldName'], ['value' => $formArguments["fieldOptions"]["default"]]);
          }
        }
      } elseif ($htmlObj->getElementsByTagName('radio')->length) {
        // Check if it is disabled. If it is then print a hidden element
        foreach($htmlObj->getElementsByTagName('radio')->item(0)->attributes as $attr) {
          if($attr->name == 'disabled' && $attr->value == 'disabled') {
            print $this->getView()->Form->hidden($formArguments['fieldName'], ['value' => $formArguments["fieldOptions"]["default"]]);
          }
        }
      } elseif ($htmlObj->getElementsByTagName('checkbox')->length) {
        // Check if it is disabled. If it is then print a hidden element
        foreach($htmlObj->getElementsByTagName('checkbox')->item(0)->attributes as $attr) {
          if($attr->name == 'disabled' && $attr->value == 'disabled') {
            print $this->getView()->Form->hidden($formArguments['fieldName'], ['value' => $formArguments["fieldOptions"]["default"]]);
          }
        }
      }
    }

    // Print the original element
    print $orginalElement;
  }

  /**
   * Determine if we have a file field to render. If we do, the form must be created 
   * with multipart/form-data encoding. This is used by the add-edit-view.php file
   * when generating the form.
   *
   * @param array $fields The array of form fields.
   * @return bool
   * @since  COmanage Registry v5.2.0
   */
  public function includesFileField(array $array): bool {
    foreach ($array as $subarray) {
      if (is_array($subarray)
        && isset($subarray['type'])
        && $subarray['type'] === 'file') {
        return true;
      }
    }
    return false;
  }
  
  /**
   * Reconstruct the array of field arguments with specific top-level items becoming 
   * part of the fieldOptions subarray that is passed to CakePHP. We do this
   * to keep the top-level array flat and abstracted away from the CakePHP
   * specifics.
   * 
   * This function will look for these top-level fields:
   * 'empty', 'required', 'default', 'readonly', 'value', 'type', 'options', 'placeholder', 
   * 'class', 'id', 'style', 'checked', and 'label'
   * 
   * These fields will be restructured into the subarray named "fieldOptions" that
   * make up the CakePHP options array when the field is processed. The rest of the $fields[]
   * array is returned as-is.
   * 
   * @param array $fieldArgs The array of field arguments.
   * @return array The reconstructed array of field arguments.
   * @since  COmanage Registry v5.2.0
   */
  public function restructureFieldArguments(array $fieldArgs) {
    $fieldOptions = [];
    
    // The options to restructure
    $topLevelOptions = [
      'empty',
      'required',
      'default',
      'readonly',
      'value',
      'type',
      'options',
      'placeholder',
      'class',
      'id',
      'style',
      'checked',
      'label'
    ];
    
    // Remove the top-level field options that are intended for Cake, and
    // insert them into the $fieldOptions array.
    foreach($topLevelOptions as $option) {
      if(array_key_exists($option, $fieldArgs)) {
        $fieldOptions[$option] = $fieldArgs[$option];
        unset($fieldArgs[$option]);
      }
    }

    // Insert the fieldOptions into the arguments array.
    if(!empty($fieldOptions)) {
      $fieldArgs['fieldOptions'] = $fieldOptions;
    }
    
    // Return the restructured array
    return $fieldArgs;
  }
}