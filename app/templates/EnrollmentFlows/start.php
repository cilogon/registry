<?php
/**
 * COmanage Registry Enrollment Flows Start View
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
  
<div class="page-title-container">
  <div class="page-title">
    <h1><?= $vv_title ?></h1>
  </div>
</div>

<?php
// Enrollment Flow Start has its own file of fields
$this->set('vv_fields_inc', 'start.inc');
// Set form edit ability
$this->set('vv_is_editable', true);

// Create the Form
print $this->Form->create();
// Form body
print $this->element('form/unorderedList');
// Close the Form
print $this->Form->end();

