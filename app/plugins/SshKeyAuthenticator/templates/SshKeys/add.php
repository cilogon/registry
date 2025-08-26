<?php
/**
 * COmanage Registry SSH Keys Add File
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

// Because we use file upload, we have to create our own add view, since we need to change the
// form encoding type to multipart/form-data. We can't do this in fields.inc because it's loaded
// after the form is opened, and we can't do this in fields-nav.inc because it's not loaded on add.
?>
<div class="page-title-container">
  <div class="page-title">
    <h1><?= $vv_title ?></h1>
  </div>
</div>
<?php  
  print $this->element('flash');

  print $this->Form->create($vv_obj, ['type' => 'file']);

  // List of records to collect, this will be pulled from fields.inc
  print $this->element('form/unorderedList');

  // Close the Form
  print $this->Form->end();
