<?php
// Get parameters
$fieldName = $fieldName ?? "";
$pickerDate = $pickerDate ?? "";

// Create a date/time picker. The yyyy-MM-dd format is set above in $pickerDate.
$pickerId     = 'datepicker-' . $fieldName;
$pickerTarget = $fieldName;
$pickerTimed  = $pickerTimed ?? true; // TODO: set false if date-only
$pickerAmPm   = $pickerAmPm ?? false; // TODO: allow change between AM/PM and 24-hour mode

?>

<script type="module">
  import CmDateTimePicker from "<?= $this->Url->script('comanage/components/datepicker/cm-datetimepicker.js') ?>";

  const app = Vue.createApp({
    data() {
      return {
        id: "<?= $pickerId ?>",
        target: "<?= $pickerTarget ?>",
        date: "<?= $pickerDate ?>",
        timed: <?= ($pickerTimed ? 'true' : 'false') ?>,
        ampm: <?= ($pickerAmPm ? 'true' : 'false') ?>,
        txt: {
          hour: "<?= __d('field', 'datepicker.hour') ?>",
          minute: "<?= __d('field', 'datepicker.minute') ?>",
          am: "<?= __d('field', 'datepicker.am') ?>",
          pm: "<?= __d('field', 'datepicker.pm') ?>"
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
      el.clickOutEvent = function (event) {
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
<div id="<?= $pickerId ?>-container">
  <cm-date-time-picker
    :id="id"
    :target="target"
    :date="date"
    :timed="timed"
    :ampm="ampm"
    :txt="txt">
  </cm-date-time-picker>
</div>
