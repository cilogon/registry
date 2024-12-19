<?php
/**
 * COmanage Registry Email Verifiers Petition Fields
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

use CoreEnroller\Lib\Enum\VerificationModeEnum;
use App\Lib\Util\StringUtilities;

print $this->element('flash', []);

// This view is intended to work with dispatch
if($vv_action == 'dispatch') {
  if($vv_op == 'index') {
    // Render the list of known email addresses and their verification statuses.
    // The configuration drives how many email addresses are required to complete this step.

    print '<p>';

    if($vv_all_done) {
      print __d('core_enroller', 'information.EmailVerifiers.done');
    } else {
      switch($vv_config->mode) {
        case VerificationModeEnum::All:
          print __d('core_enroller', 'information.EmailVerifiers.A');
          break;
        case VerificationModeEnum::None:
          print __d('core_enroller', 'information.EmailVerifiers.0');
          break;
        case VerificationModeEnum::One:
          if($vv_minimum_met) {
            print __d('core_enroller', 'information.EmailVerifiers.1.met');
          } else {
            print __d('core_enroller', 'information.EmailVerifiers.1.none');
          }
          break;
      }
    }

    print '</p>';

    print '
    <table id="verifications-table" class="index-table list-mode">
      <thead>
        <tr>
          <th>' . __d('controller', 'EmailAddresses', [1]) . '</th>
          <th>' . __d('field', 'status') . '</th>
        </tr>
      </thead>
      </tbody>
    ';

    foreach(array_keys($vv_email_addresses) as $addr) {
      $verified = isset($vv_verified_addresses[$addr]) && $vv_verified_addresses[$addr];

      $button = "";

      if(!$verified) {
        // We're already in a form here, so we need to use a GET URL to not mess things up.
        // This also means we need to manually insert the token and petition ID, which is
        // a bit duplicative with templates/Standard/dispatch.php

        $url = [
          'plugin'      => 'CoreEnroller',
          'controller'  => 'email_verifiers',
          'action'      => 'dispatch',
          $vv_config->id,
          '?' => [
            'op'          => 'verify',
            'petition_id' => $vv_petition->id,
            // We base64 encode the address partly to not have bare email addresses in URLs
            // and partly to avoid special characters (like dots) messing up the URL
            'm'           => StringUtilities::urlbase64encode($addr)
          ]
        ];

        if(isset($vv_token_ok) && $vv_token_ok && !empty($vv_petition->token)) {
          $url['?']['token'] = $vv_petition->token;
        }

        $button = $this->Html->link(__d('operation', 'verify'), $url);
      }

      print '
        <tr>
          <td>' . $addr . '</td>
          <td>' . __d('result', ($verified ? 'verified' : 'verified.not')) . $button . '</td>
        </tr>
      ';
    }

    print '
      </tbody>
    </table>
    ';

    if($vv_minimum_met) {
      $this->Field->enableFormEditMode();

      print $this->Form->hidden('op', ['default' => 'finish']);
    }
  } elseif($vv_op == 'verify') {
    if(!empty($vv_verify_address)) {
      // Render a form prompting for the code that was sent to the Enrollee

      print __d('core_enroller', 'information.EmailVerifiers.code_sent', [$vv_verify_address]);

      $this->Field->enableFormEditMode();

      print $this->Form->hidden('op', ['default' => 'verify']);
      print $this->Form->hidden('co_id', ['default' => $vv_cur_co->id]);
      print $this->Form->hidden('m', ['default' => StringUtilities::urlbase64encode($vv_verify_address)]);

      print $this->element('form/listItem', [
        'arguments' => [
          'fieldName' => 'code',
          'fieldLabel' => "Code", //__d('field', 'mail')
          'fieldOptions' => [
            'required' => true
          ]
      ]]);
    }
  }
}