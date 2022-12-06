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

$options = [
  'type' => 'post',
  'url' => [
    'plugin'      => null,
    'controller'  => 'dashboards',
    'action'      => 'search'
  ],
  'id' => 'global-search-form'
];

?>

<button id="global-search-toggle" class="dropdown-toggle top-menu-button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
  <em class="material-icons">search</em>
  <span class="visually-hidden"><?= __d('operation','search') ?></span>
</button>

<div id="global-search" class="dropdown-menu" aria-labelledby="global-search-toggle">
  <?php
    print $this->Form->create(null, $options);
    print $this->Form->hidden('co_id', ['default' => $vv_cur_co->id]);
    print $this->Form->label('global-search-q', __d('field','search.placeholder'), ['class' => 'visually-hidden']);
    print $this->Form->input('global-search-q',['id' => 'global-search-q', 'placeholder' => __d('field','search.placeholder')]);
    print $this->Form->button(
      '<em class="material-icons">search</em>',
      ['type' => 'submit', 'escapeTitle' => false, 'id' => 'global-search-button', 'class' => 'btn btn-link btn-sm']
    );
    print $this->Form->end();
  ?>
</div>

  