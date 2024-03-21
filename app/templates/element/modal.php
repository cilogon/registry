<?php
  /*
   * COmanage Registry Generic Modal Box
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
   *
   * This generic modal stub is used for exposing lightboxes with content, e.g. when adding 
   * or editing group members. The text of the box is overridden with JavaScript.
   */
?>

<div class="modal fade cm-modal" id="cm-modal" aria-labelledby="cm-modal-title" data-reload-on-close="true" tabIndex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="cm-modal-title"><?= __d('default','registry.meta.registry') ?></h2>
        <button type="button" class="btn-close nospin" data-bs-dismiss="modal"
                aria-label="<?= __d('operation','close') ?>"></button>
      </div>
      <div id="cm-modal-text" class="modal-body">
      </div>
    </div>
  </div>
</div>