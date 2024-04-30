<?php
/**
 * COmanage Registry Field Div Element
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
?>

<div class="field">
  <?php
  // Name Div
  print $this->element('form/nameDiv');

  // Info Div
  if(isset($vv_field_arguments['fieldPrefix'])) {
    print $this->element('form/infoDiv/withPrefix');
  } elseif(isset($vv_field_arguments['status'])) {
    print $this->element('form/infoDiv/status');
  } elseif(isset($vv_field_arguments['groupedControls'])) {
    print $this->element('form/infoDiv/grouped');
  } elseif(isset($vv_field_arguments['entity'])) {
    print $this->element('form/infoDiv/source');
  } elseif(isset($vv_field_arguments['check']) && $vv_field_arguments['check']) {
    print $this->element('form/infoDiv/check');
  } elseif(isset($vv_field_arguments['groupmember'])) {
    print $this->element('form/infoDiv/groupMember');
  } elseif(isset($vv_field_arguments['autocomplete'])) {
    print $this->element('form/infoDiv/autocomplete');
  } else {
    print $this->element('form/infoDiv/default');
  }
  ?>
</div>