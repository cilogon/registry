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

use Cake\I18n\FrozenTime;
use Cake\Utility\Inflector;
use Cake\View\Helper;
use App\Lib\Enum\DateTypeEnum;
use App\Lib\Util\StringUtilities;

class FieldHelper extends Helper {
  public $helpers = ['Form', 'Html'];

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
   */
  public function initialize(array $config): void
  {
    parent::initialize($config);

    $this->reqFields = $this->getView()->get('vv_required_fields');
    $this->modelName = $this->getView()->getName();
    $this->action = $this->getView()->get('vv_action');
    $this->editable = \in_array($this->action, ['add', 'edit']);
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
   */
  public function calculateLabelAndDescription(string $fieldName): array
  {
    $desc = null;
    $label = null;

    // First, try to autogenerate the field label (if we weren't given one).
    $pluginDomain = (!empty($this->getPluginName())
      ? Inflector::underscore($this->getPluginName())
      : null);

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
   */
  public function calculateLiClasses(): string
  {
    $fieldName = $this->getView()->get('fieldName');
    $vv_field_arguments = $this->getView()->get('vv_field_arguments');

    // Class calculation by field Type
    $classes = match ($this->getFieldType($fieldName)) {
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

    return $classes;
  }

  /**
   * Emit a date/time form control.
   * This is a wrapper function for $this->control()
   *
   * @param   string       $fieldName  Form field
   * @param   string       $dateType   Standard, DateOnly, FromTime, ThroughTime
   * @param   string|null  $label
   *
   * @return string HTML element
   * @since  COmanage Registry v5.0.0
   */

  public function dateField(string $fieldName,
                            string $dateType=DateTypeEnum::Standard,
                            string $label=null): string
  {
    // Initialize
    $dateFormat = $dateType === DateTypeEnum::DateOnly ? 'yyyy-MM-dd' : 'yyyy-MM-dd HH:mm:ss';
    $dateTitle = $dateType === DateTypeEnum::DateOnly ? 'datepicker.enterDate' : 'datepicker.enterDateTime';
    $datePattern = $dateType === DateTypeEnum::DateOnly ? '\d{4}-\d{2}-\d{2}' : '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}';
    $queryParams = $this->getView()->getRequest()->getQueryParams();
    $date_object = !empty($queryParams[$fieldName])
                   ? FrozenTime::parse($queryParams[$fieldName])
                   : $this->getEntity()?->$fieldName;

    // Create the options array for the (text input) form control
    $coptions = [];

    // A datetime field will be rendered as a plain text input with adjacent date and time pickers
    // that will interact with the field value. Allowing direct access to the input field is for
    // accessibility purposes.

    // ACTION VIEW
    if($this->action == 'view') {
      // return the date as plaintext
      $element = $this->getView()->element('form/notSetDiv', [], [
        'cache' => '_html_elements',
      ]);
      if ($date_object !== null) {
        // Adjust the time back to the user's timezone
        $element = '<time>' . $date_object->i18nFormat($dateFormat) . '</time>';
      }

      // Return this to the generic control() function
      return $element;
    }

    // Special-case the very common "valid_from" and "valid_through" fields, so we won't need
    // to specify their types in fields.inc.
    $pickerType = match ($fieldName) {
      'valid_from' => DateTypeEnum::FromTime,
      'valid_through' => DateTypeEnum::ThroughTime,
      default => $dateType
    };

    // Append the timezone to the label
    $coptions['class'] = 'form-control datepicker';
    $coptions['placeholder'] = $dateFormat;
    if(!empty($label)) {
      $coptions['label'] = $label;
    }
    $coptions['pattern'] = $datePattern;
    $coptions['title'] = __d('field', $dateTitle);

    $coptions['id'] = str_replace('_', '-', $fieldName);


    // Default the picker date to today
    $now = FrozenTime::now();
    $pickerDate = $now->i18nFormat($dateFormat);

    // Get the existing values, if present
    if($date_object !== null) {
      // Adjust the time back to the user's timezone
      $coptions['value'] = $date_object->i18nFormat($dateFormat);
      $pickerDate = $date_object->i18nFormat($dateFormat);
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

    // Create a text field to hold our value and call the datePicker
    return $this->Form->text($fieldName, $coptions) . $this->getView()->element('datePicker', $date_picker_args);
  }

  /**
   * Create the actual Form element
   *
   * @param   string       $fieldName     Form field
   * @param   array|null   $fieldOptions  The second parameter of the Form->control helper. List of element options
   * @param   string|null  $fieldLabel    Custom label thext
   * @param   string       $fieldPrefix   If the field has a specil prefix provide the value
   * @param   string|null  $fieldType     Field type to override the one calculated from the schema
   *
   * @return string  HTML element
   * @since  COmanage Registry v5.0.0
   */
  public function formField(string $fieldName,
                            array  $fieldOptions = null,
                            string $fieldLabel = null,
                            string $fieldPrefix = '',
                            string $fieldType = null): string
  {
    $fieldArgs = $fieldOptions ?? [];
    $fieldArgs['label'] = $fieldOptions['label'] ?? false;
    $fieldArgs['readonly'] = !$this->editable
                             || (isset($fieldOptions['readonly']) && $fieldOptions['readonly'])
                             || ($fieldName == 'plugin' && $this->action == 'edit');

    // Selects, Checkboxes, and Radio Buttons use "disabled"
    $fieldArgs['disabled'] = $fieldArgs['readonly'];
    $fieldArgs['required'] = $this->isReqField($fieldName)
                             || (isset($fieldOptions['required']) && $fieldOptions['required']);

    // Cause any select (except status) to render with a blank option, even
    // if the field is required. This makes it clear when a value needs to be set.
    // Note this will be ignored for non-select controls.
    $fieldArgs['empty'] = !\in_array($fieldName, ['status', 'sync_status_on_delete'], true)
                          || (isset($fieldOptions['empty']) && $fieldOptions['empty']);

    // Manipulate the vv_object for the hasPrefix use case
    $this->handlePrefix($fieldPrefix, $fieldName);

    // Get the field type from the map of fields (e.g. 'boolean', 'string', 'timestamp')
    $fieldType = $fieldType ?? $this->getFieldType($fieldName);
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
      'date'      => $this->dateField($fieldName, DateTypeEnum::DateOnly),
      'datetime',
      'timestamp' => $this->dateField($fieldName),
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

}