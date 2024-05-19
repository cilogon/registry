<?php
/**
 * COmanage Registry Form Check Element
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

// We need to uncheck the control key before passing the form parameters to the helper
unset($vv_field_arguments['check']);

?>

<div class="field-info">
  <div class="field-suppliment">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" value="" id="format-toggle">
      <label class="form-check-label" for="format-toggle"><?= __d('field','format') ?></label>
    </div>
  </div>
  <?= $this->Field->formField(...$vv_field_arguments) ?>
  <script>
    parametersValue = $("#<?= $fieldName ?>").val();
    parametersJSON = JSON.parse(parametersValue);
    $("#format-toggle").click(function() {
      if($(this).is(":checked")) {
        $("#<?= $fieldName ?>").val(JSON.stringify(parametersJSON, null, 4));
        paramsHeight = Object.keys(parametersJSON).length + 6 + "em";
        $("#<?= $fieldName ?>").css("height",paramsHeight);
      } else {
        $("#<?= $fieldName ?>").val(parametersValue);
        $("#<?= $fieldName ?>").css("height","4em");
      }
    });
  </script>
</div>