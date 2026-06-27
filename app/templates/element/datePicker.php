<?php
/**
 * COmanage Registry Date and Time Picker Element
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

// Get parameters
$fieldName   = $fieldName ?? "";
$pickerDate  = $pickerDate ?? "";
$pickerType  = $pickerType ?? \App\Lib\Enum\DateTypeEnum::Standard;
$pickerFloor = $pickerFloor ?? "";

// Create a date/time picker. The yyyy-MM-dd format is set above in $pickerDate.

// CAKEPHP transforms snake case variables to kebab when the name has the format of a foreign key.
// As a result searching for the initial key will fail. This is used for use cases
// like the models that use the Tree behavior and have the column parent_id
$pickerId     = 'datepicker-' . str_replace("_", "-", $fieldName);
$pickerTarget = str_replace("_", "-", $fieldName);
$pickerAmPm   = $pickerAmPm ?? false; // TODO: allow change between AM/PM and 24-hour mode

// Set the min and max dates to allow for a wide range of year selections in the datepicker.
$pickerDateMin = $pickerFloor; // We are passing in -100 years from FieldHelper where the values are constructed.
$pickerDateMax = ''; // If empty, the date picker will default to +10 years.

?>

<script type="module" nonce="<?= $vv_js_nonce ?>">
  import CmDateTimePicker from "<?= $this->Url->script('comanage/components/datepicker/cm-datetimepicker.js') ?>";

  const app = Vue.createApp({
    data() {
      return {
        id: "<?= $pickerId ?>",
        target: "<?= $pickerTarget ?>",
        date: "<?= $pickerDate ?>",
        datemin: "<?= $pickerDateMin ?>",
        datemax: "<?= $pickerDateMax ?>",
        type: "<?= $pickerType ?>",
        ampm: <?= ($pickerAmPm ? 'true' : 'false') ?>,
        txt: {
          hour: "<?= __d('field', 'datepicker.hour') ?>",
          minute: "<?= __d('field', 'datepicker.minute') ?>",
          am: "<?= __d('field', 'datepicker.am') ?>",
          pm: "<?= __d('field', 'datepicker.pm') ?>",
          choosetime: "<?= __d('field', 'datepicker.chooseTime') ?>"
        }
      };
    },
    components: {
      CmDateTimePicker
    },
    template: `
      <cm-date-time-picker
        :id="id"
        :target="target"
        :date="date"
        :datemin="datemin"
        :datemax="datemax"
        :type="type"
        :ampm="ampm"
        :txt="txt">
      </cm-date-time-picker>
    `
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

  app.mount("#<?= $pickerId ?>-container");
</script>
<div id="<?= $pickerId ?>-container" class="datepicker-container"></div>

<?php if($pickerType != "dateonly"): ?>
  <div class="cm-tz"><?= $vv_tz->getName() ?></div>
<?php endif; ?>

<?php
  /** DATE-TIME KEYBOARD HANDLING
   * Registry's date and time pickers will automatically set the correct format for date and time.
   * The date fields also check their validity against a pattern attribute on form submission.
   * To improve UX when a user enters a date manually with the keyboard, the field can also
   * check validity against the pattern on blur and "autocorrect" the value when possible.
   * There are four types of date fields held in $pickerType described by DateTypeEnum (in parentheses):
   * date-time (standard), date only (dateonly), valid from (fromtime), and valid through (throughtime).
   * Valid from and standard date-time fields should behave the same way: time should default to 00:00:00 but
   * can be set explicitly. Valid through needs special treatment for its end-time which defaults to 23:59:59
   * but can also be set explicitly. The script below handles keyboard interaction independent of the
   * VueJS widgets because we are acting on a simple text input field that is not directly part of the
   * Vue components (for accessibility).
   */
?>
<div id="<?= $pickerTarget ?>-msg" class="datepicker-message invalid-feedback">
  <?= $pickerType == 'dateonly' ? __d('field','datepicker.enterDate') : __d('field','datepicker.enterDateTime') ?>
</div>
<script nonce="<?= $vv_js_nonce ?>">
  // Validate on blur for handling keyboard input.
  $('#<?= $pickerTarget ?>').blur(function() {
    const regExPattern = $(this).attr('pattern');
    $(this).val(validateDateFormat($(this).val(), '<?= $pickerType ?>', regExPattern, '<?= $pickerTarget ?>'));
  });
  // Hide warning messages on focus.
  $('#<?= $pickerTarget ?>').on('focus', function() {
    $('#<?= $pickerTarget ?>-msg').hide();
    $('#<?= $pickerTarget ?>').removeClass('invalid');
  });
</script>
