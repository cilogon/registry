<?php
  /**
   * COmanage Registry Entity Metadata Element
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
  
  declare(strict_types = 1);
?>

<?php if(isset($vv_obj?->id)): ?>
  <div id="entity-metadata">
    <div id="cm-entity-id">
      <?= __d('field', 'id.value', $vv_obj->id) ?>
    </div>
    <div id="cm-entity-created">
      <?= __d('field', 'created.value', $this->Time->nice($vv_obj->created, $vv_tz)) ?>
    </div>
    <?php if($vv_obj->created != $vv_obj->modified): ?>
      <div id="cm-entity-modified">
        <?= __d('field', 'modified.value', $this->Time->nice($vv_obj->modified, $vv_tz)) ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>