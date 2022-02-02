<?php
  /*
   * COmanage Registry Modal Dialog Box
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
   * This generic modal dialog stub is used for confirmations, e.g. when deleting a record.
   * The text of the box is overridden with JavaScript, and the confirm button is intended to
   * click a CakePHP postLink or postButton in the DOM. Use js_confirm_generic() to call it.
   */
?>

<div class="modal fade co-dialog" id="dialog" tabindex="-1" aria-labelledby="dialog-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="dialog-title"><?= __d('operation', 'confirm'); ?></h2>
        <button type="button" class="btn-close nospin" data-bs-dismiss="modal" aria-label="<?= __d('operation', 'close'); ?>"></button>
      </div>
      <div id="dialog-text" class="modal-body">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn nospin"
                id="dialog-cancel-button" data-bs-dismiss="modal"><?= __d('operation', 'cancel'); ?></button>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal"
                id="dialog-confirm-button"><?= __d('operation', 'confirm'); ?></button>
      </div>
    </div>
  </div>
</div>
