<?php
/**
 * COmanage Registry T&C Agreement Confirmation Dialog Element
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
 * 
 * This is the Terms and Conditions Agree dialog - a more specialized dialog intended to be
 * rendered for each T&C that uses a Mostly Static Page. 
 * 
 */

declare(strict_types = 1);

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

// Mostly Static Pages allow HTML input in the page body.
// Set up the Symfony HTML Sanitizer to disallow dom elements like <script> and <style>,
// and run the field through the sanitizer before display.  
$htmlSanitizer = new HtmlSanitizer(
  (new HtmlSanitizerConfig())->allowStaticElements()
);

?>

<?php if(!empty($vv_tc)): ?>
<div class="modal fade co-dialog tc-agree-dialog" 
  id="tc<?= $vv_tc->id ?>-agree-dialog" 
  tabindex="-1" 
  aria-labelledby="tc<?= $vv_tc->id ?>-dialog-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="tc<?= $vv_tc->id ?>-dialog-title">
          <?= __d('terms_agreer','information.AgreementCollectors.review.tc') ?>
        </h2>
        <button type="button" class="btn-close nospin" data-bs-dismiss="modal" aria-label="<?= __d('operation', 'close'); ?>"></button>
      </div>
      <div class="modal-body">
        <h1 class="tc-agree-dialog-title"><?= $vv_tc->mostly_static_page->title ?></h1>
        <div class="tc-content">
          <?= $htmlSanitizer->sanitize($vv_tc->mostly_static_page->body) ?>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn nospin" data-bs-dismiss="modal">
          <?= __d('operation', 'cancel'); ?>
        </button>
        <button data-tc-id="tc<?= $vv_tc['id'] ?>" type="button" class="btn btn-primary tc-agree-button" data-bs-dismiss="modal">
          <?= __d('operation', 'agree'); ?>
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>