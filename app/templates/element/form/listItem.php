<?php
/**
 * COmanage Registry Unordered List Element
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

// Make the element configuration available downstream
// XXX Unfortunately CAKEPHP doe not create a viewvar space for the element
//     parameters. As a result we do not know which one is which unless we:
//     - add a prefix and create a namespace
//     - wrap them in an array.
//     We choose the latter.
$this->set('fieldName', $arguments['fieldName']);
$fieldName = $arguments['fieldName'];
$this->set('vv_field_arguments', $arguments);

// If an attribute is frozen, inject a special link to unfreeze it, since
// the attribute is read-only and the admin can't simply uncheck the setting
if($fieldName == 'frozen' && $this->Field->getEntity()->frozen) {
  $url = [
    'label' => __d('operation', 'unfreeze'),
    'url' => [
      'plugin'      => null,
      'controller'  => \App\Lib\Util\StringUtilities::entityToClassname($this->Field->getEntity()),
      'action'      => 'unfreeze',
      $this->Field->getEntity()->id
    ]
  ];
  $arguments = [
    ...$arguments,
    'status' => __d('field', 'frozen'),
    'link' => $url,
  ];
  $this->set('vv_field_arguments', $arguments);
}

// If an attribute is a plugin, return the link to its configuration
if($fieldName == 'plugin' && $vv_action == 'edit') {
  $url = [
    'label' => __d('operation', 'configure.plugin'),
    'url' => [
      'plugin'      => null,
      'controller'  => \App\Lib\Util\StringUtilities::entityToClassname($this->Field->getEntity()),
      'action'      => 'configure',
      $this->Field->getEntity()->id
    ]
  ];
  $arguments = [
    ...$arguments,
    'status' => $this->Field->getEntity()->$fieldName,
    'link' => $url,
  ];
  $this->set('vv_field_arguments', $arguments);
}

?>

<li class="<?= trim($this->Field->calculateLiClasses()) ?>">
  <?= $this->element('form/fieldDiv')?>
</li>
