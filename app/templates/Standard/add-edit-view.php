<?php
/**
 * COmanage Registry Generic View Template For Add/Edit/View Actions
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

//$action = $this->template;
// $this->name = Models
$modelsName = $this->name;
// $tablename = models
// XXX backport to match?
$tableName = \Cake\Utility\Inflector::tableize(\Cake\Utility\Inflector::singularize($this->name));

?>
<div class="titleNavContainer">
  <div class="pageTitle">
    <h1><?= $vv_title; ?></h1>
  </div>
</div>
<?php
// XXX this doesn't work yet because we don't include fields.inc until later
//     either create a second file to include earlier, or use a function to emit
//     the fields (which would be more consistent with how Views render...)
if(!empty($banners)) {
  foreach($banners as $b): ?>  
<div class="co-info-topbox">
  <em class="material-icons">info</em>
  <?php print $b; ?>
</div>
<?php endforeach; // $banners
}
?>
<?php
// XXX CO-647
// XXX move delete to some form of buttons.inc?
// XXX duplicates index.ctp though strangely this is working whereas index delete throws csrf error
// This is a bit overlap with Elements/pageTitleAndButtons
if(!empty($vv_obj->id) && $vv_permissions['delete']) {
  print '<ul id="topLinks">';
  print "<li>" . $this->Form->postLink(
    __('registry.op.delete'),
    ['action' => 'delete', $vv_obj->id],
// XXX should be configurable which field we put in, maybe displayField?
    ['confirm' => __('registry.op.delete.confirm', [$vv_obj->id]),
     'class'   => 'deletebutton']
  ) . "</li>";
  print "</ul>\n";
}

// By default, the form will POST to the current controller
// Note we need to open the form for view so Cake will autopopulate values
print $this->Form->create($vv_obj);

$linkId = null;

if(!empty($vv_primary_link)) {
  if(!empty($this->request->getQuery($vv_primary_link))) {
    $linkId = $this->request->getQuery($vv_primary_link);
  } elseif(!empty($this->request->getData($vv_primary_link))) {
    $linkId = $this->request->getData($vv_primary_link);
  } elseif(!empty($vv_obj->$vv_primary_link)) {
    $linkId = $vv_obj->$vv_primary_link;
  }
}

print $this->Field->startControlSet($this->name, 
                                    $vv_action,
                                    ($vv_action == 'add' || $vv_action == 'edit'),
                                    $vv_required_fields);

// We allow the fields.inc file to be specified for Controllers that have more
// complicated/non-default actions.
$fieldsFile = "fields.inc";

if(!empty($vv_fields_inc)) {
  $fieldsFile = $vv_fields_inc;
}

include(ROOT . DS . "templates" . DS . $modelsName . DS . $fieldsFile);

if(!empty($hidden)) {
  // Inject any hidden variables set by the include file
  foreach($hidden as $attr => $v) {
    print $this->Form->hidden($attr, ['value' => $v]);
  }
}

if($vv_action == 'add' || $vv_action == 'edit') {
  // We don't want/need to output these for view actions
  
  if(!empty($linkId)) {
    // Hidden values used to link to parent objects (eg: matchgrid_id)
    print $this->Form->hidden($vv_primary_link, ['value' => $linkId]);
  }
  
  print $this->Field->submit(__('registry.op.save'));
}

print $this->Form->end();

print $this->Field->endControlSet();

// XXX insert changelog metadata (+nav? or maybe we should have a dedicate index view that shows all records in revision order?)
