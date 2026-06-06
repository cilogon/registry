<?php
/**
 * COmanage Registry Terms and Conditions Review View
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

use App\Lib\Enum\TAndCStatusEnum;
use App\Lib\Util\StringUtilities;
?>

<div class="page-title-container">
  <div class="page-title">
    <h1><?= $vv_title; ?></h1>
  </div>
</div>

<?= $this->element('flash') // Flash messages ?>

<!-- Our view is similar to index.php and status.php -->
<div class="table-container">
  <table id="termsandconditionsstatus-table" class="index-table list-mode with-actions">
    <thead>
      <tr>
        <th class="with-field-actions"><?= __d('controller', 'TermsAndConditions', [1]); ?></th>
        <th><?= __d('field', 'status'); ?></th>
        <th><?= __d('field', 'changelog.actor_identifier'); ?></th>
        <th><?= __d('field', 'timestamp.tz', [$vv_tz->getName()]); ?></th>
      </tr>
    </thead>

    </tbody>
      <?php foreach($vv_tandc_statuses as $t): ?>
      <tr>
        <td class="with-field-actions">
          <div class="field-actions-container">
            <div class="field-actions">
          <?php
            // Build the row actions
            $action_args = array();
            $action_args['vv_attr_id'] =  $t['tandc']->id;
            $action_args['vv_actions'] = [];

            if($t['status'] != TAndCStatusEnum::Agreed) {
              // T&C that are not current can be agreed to

              $action_args['vv_actions'][] = [
                'order' => $this->Menu->getMenuOrder('Default'),
                'icon' => 'signature',
                'url' => [
                  'controller' => 'terms_and_conditions',
                  'action' => 'agree',
                  $t['tandc']->id
                ],
                'label' => __d('operation', 'agree'),
                'confirm' => [
                  'dg_body_txt' => __d('operation', 'TermsAndConditions.agree.confirm'),
                  'dg_confirm_btn' => __d('operation', 'confirm')
                ]
              ];
            }

            if(!empty($action_args['vv_actions'])) {
              print $this->element('menuAction', $action_args);
            }
          ?>
          </div>
            <?= $t['tandc']->description; ?>
          </div>
        </td>
        <td><?= __d('enumeration', 'TAndCStatusEnum.'.$t['status']); ?></td>
        <td><?= $t['agreement']->identifier ?? "" ?></td>
        <td><?= 
          !empty($t['agreement']->created) 
          ? $this->Time->nice($t['agreement']->created, $vv_tz)
          : ""; 
        ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
