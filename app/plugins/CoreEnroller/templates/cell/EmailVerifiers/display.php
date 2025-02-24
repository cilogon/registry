<?php
/*
 * COmanage Registry Email Verifiers Cell Display
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
 *
 * This generic modal dialog stub is used for confirmations, e.g. when deleting a record.
 * The text of the box is overridden with JavaScript, and the confirm button is intended to
 * click a CakePHP postLink or postButton in the DOM. Use jsConfirmGeneric() to call it.
 */

declare(strict_types=1);

use App\Lib\Enum\VerificationMethodEnum;

if (empty($vv_pv)) {
  print $this->element('emptyPetitionFlowStep', [], [
    'cache' => '_html_elements',
  ]);
  return;
}

?>


<ul>
  <?php foreach($vv_pv as $pv): ?>
    <li><?= $pv->mail ?>:
      <?php if(!empty($pv->verification) && $pv->verification->isVerified()): ?>
        <?= __d('result', 'Verifications.status', [
          VerificationMethodEnum::getLocalization($pv->verification->method),
          $this->Time->nice($pv->verification->verification_time, $viewVars["vv_tz"])
        ]) ?>
      <?php else: ?>
        <span class="mr-1 badge bg-warning unverified"><?= __d('field','unverified') ?></span>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>