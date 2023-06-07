<?php
/*
 * COmanage Registry Breadcrumbs
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

if(isset($vv_bc_skip) && $vv_bc_skip) {
  return;
}

$this->Breadcrumbs->setTemplates([
  'wrapper' => '{{content}}',
  'item' => '<a href="{{url}}"{{innerAttrs}}>{{title}}</a>{{separator}}',
  'itemWithoutLink' => '<span{{innerAttrs}}>{{title}}</span>{{separator}}',
  'separator' => '<span{{innerAttrs}}>{{separator}}</span>'
]);

// Start with the top level link
$this->Breadcrumbs->prepend(
  __('registry.meta.registry'),
  ['plugin'       => null,
    'controller'   => 'cos',
    'action'       => 'select']
);

// Insert a CO level link, if available
if(!empty($vv_cur_co)) {
  $this->Breadcrumbs->add(
    !empty($vv_cur_co->name) ? $vv_cur_co->name : __('product.comanage'),
    ['plugin'       => null,
     'controller'   => 'dashboards',
     'action'       => 'dashboard',
     '?'            => ['co_id' => !empty($vv_cur_co) ? $vv_cur_co->id : 1]]
  );
}

// Insert a configuration breadcrumb if set
if(!$vv_bc_skip_config && $vv_bc_configuration_link) {
  $this->Breadcrumbs->add(
    __d('menu', 'co.configuration'),
    [
      'plugin'       => null,
      'controller'   => 'dashboards',
      'action'       => 'configuration',
      '?'            => ['co_id' => !empty($vv_cur_co) ? $vv_cur_co->id : 1]]
  );
}

// Insert any parent breadcrumbs
if(!empty($vv_bc_parents)) {
  foreach($vv_bc_parents as $pbc) {
    $this->Breadcrumbs->add(
      $pbc['label'],
      $pbc['target']
    );
  }
}

// Insert the page title
if(!empty($vv_title)) {
  $this->Breadcrumbs->add(
    $vv_title
  );
}

print $this->Breadcrumbs->render(
  [],
  ['separator' => ' &gt; ']
);