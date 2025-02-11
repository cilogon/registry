<?php
/*
 * COmanage Registry Invitation Accepters Cell Display
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

?>

<ul>
  <li>
    <?php
    if(!empty($vv_pa)) {
      if($vv_pa['accepted']) {
        print __d('core_enroller', 'result.InvitationAccepters.accepted', [$vv_pa['modified']]);
      } else {
        print __d('core_enroller', 'result.InvitationAccepters.declined', [$vv_pa['modified']]);
      }
    } else {
      print __d('core_enroller', 'result.InvitationAccepters.none');
    }
    ?>
  </li>
</ul>