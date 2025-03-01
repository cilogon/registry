<?php
/**
 * COmanage Registry Basic Attribute Collectors Cell Display
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

if($vv_obj->id === null) {
  return __d('error', 'notfound', 'Petition Attributes');
}

?>
<ul>
  <li class="petition-attr-name">
    <h4 class="petition-attr-label">Full Name</h4>
    <ul class="petition-attrs-subset">
      <li>
        <div class="petition-attrs-subset-label">honorific</div>
        <div class="petition-attr-subset-value"><?= $vv_petition_basic_attribute_set['honorific'] ?? "" ?></div>
      </li>
      <li>
        <div class="petition-attrs-subset-label">given</div>
        <div class="petition-attr-subset-value"><?= $vv_petition_basic_attribute_set['given'] ?? "" ?></div>
      </li>
      <li>
        <div class="petition-attrs-subset-label">middle</div>
        <div class="petition-attr-subset-value"><?= $vv_petition_basic_attribute_set['middle'] ?? "" ?></div>
      </li>
      <li>
        <div class="petition-attrs-subset-label">family</div>
        <div class="petition-attr-subset-value"><?= $vv_petition_basic_attribute_set['family'] ?? "" ?></div>
      </li>
      <li>
        <div class="petition-attrs-subset-label">suffix</div>
        <div class="petition-attr-subset-value"><?= $vv_petition_basic_attribute_set['suffix'] ?? "" ?></div>
      </li>
    </ul>
  </li>
  <li class="petition-key-value petition-attr-email">
    <h4 class="petition-attr-label">Email Address</h4>
    <div class="petition-attr-value"><?= $vv_petition_basic_attribute_set['mail'] ?? "" ?></div>
  </li>
</ul>
