<?php
/**
 * COmanage Registry Notifications Controller
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

namespace App\Controller;

// XXX not doing anything with Log yet
use Cake\Log\Log;

class NotificationsController extends StandardController {
  public $paginate = [
    'order' => [
      'Notifications.modified' => 'desc'
    ]
  ];

  /**
   * Acknowledge a Notification.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id Notification ID
   */

  public function acknowledge(string $id) {
    try {
      $this->Notifications->acknowledge((int)$id, $this->RegistryAuth->getPersonID($this->getCOID()));
      $this->Flash->success(__d('result', 'Notifications.acknowledged'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->generateRedirect(null);
  }

  /**
   * Cancel a Notification.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id Notification ID
   */

  public function cancel(string $id) {
    try {
      $this->Notifications->cancel((int)$id, $this->RegistryAuth->getPersonID($this->getCOID()));
      $this->Flash->success(__d('result', 'Notifications.canceled'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->generateRedirect(null);
  }

  /**
   * Resend (deliver) a Notification.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string $id Notification ID
   */

  public function resend(string $id) {
    try {
      $this->Notifications->deliver($this->Notifications->get((int)$id), $this->RegistryAuth->getPersonID($this->getCOID()));
      $this->Flash->success(__d('result', 'Notifications.resent'));
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    return $this->generateRedirect(null);
  }
}