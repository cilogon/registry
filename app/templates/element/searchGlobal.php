<?php
/**
* COmanage Registry Global Search Element
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

$options = array(
  // XXX Re-enable and adjust the following when we are ready to implement the search feature  
  /*'type' => 'get',
  'url' => array(
    'plugin' => null,
    'action' => 'search'
  )*/
  // XXX For now, note that this feature is unimplemented 
  'type' => 'get',
  'url' => array(
    'plugin' => null
  ),
  'onsubmit' => 'alert("unimplemented"); return false;'
);

print $this->Form->create(null, $options);
print $this->Form->label('q', __d('field','search.placeholder'), ['class' => 'visually-hidden']);
print $this->Form->input('q',['id' => 'q','placeholder' => __d('field','search.placeholder')]);
print $this->Form->button(
  __d('operation','search'), 
  ['type' => 'submit', 'escapeTitle' => false, 'class' => 'btn btn-primary']
); 
print $this->Form->end();
  