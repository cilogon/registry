<?php
/**
 * COmanage Registry Terms and Conditions Controller
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

namespace App\Controller;

// XXX not doing anything with Log yet
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use \App\Lib\Enum\ProvisioningContextEnum;

class TermsAndConditionsController extends StandardController {
  public array $paginate = [
    'order' => [
      'TermsAndConditions.ordr' => 'asc'
    ]
  ];

  /**
   * Proxy an Agreement on behalf of a Person.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string  $id Terms and Conditions ID
   */

  public function proxy(string $id) {
    $personId = $this->request->getQuery('person_id');

    try {
      $this->TermsAndConditions->TAndCAgreements->record(
        termsAndConditionsId: (int)$id,
        personId: (int)$personId,
        actorPersonId: $this->RegistryAuth->getPersonID($this->getCOID()),
        identifier: $this->getRequest()->getSession()->read('Auth.external.user')
      );

      // Request provisioning on success

      $this->llog('trace', "Requesting provisioning after T&C Agreement for Person " . $personId);

      $People = TableRegistry::getTableLocator()->get('People');

      $People->requestProvisioning(
        id: (int)$personId,
        context: ProvisioningContextEnum::Automatic
      );
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
      return $this->generateRedirect(null);
    }

    $this->Flash->success(__d('result', 'TermsAndConditions.recorded'));
    return $this->redirect(['action' => 'status', '?' => ['person_id' => $personId]]);
  }

  /**
   * Revoke a T&C Agreement.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string  $id Terms and Conditions ID
   */

  public function revoke(string $id) {
    // While this could be implemented in TAndCAgreement controller, we'll
    // keep it here for consistency with other functions. Note that ID is
    // the T&C ID, not the Agreement ID. Because there could be multiple
    // Agreements (eg: expired) we'll revoke (delete) ALL TAndCAgreements
    // for $id for the specified Person.

    $personId = $this->request->getQuery('person_id');

    try {
      $count = $this->TermsAndConditions->TAndCAgreements->revoke(
        termsAndConditionsId: (int)$id,
        personId: (int)$personId,
        actorPersonId: $this->RegistryAuth->getPersonID($this->getCOID())
      );

      if($count > 0) {
        // Request provisioning on success

        $this->llog('trace', "Requesting provisioning after T&C Agreement revoked for Person " . $personId);

        $People = TableRegistry::getTableLocator()->get('People');

        $People->requestProvisioning(
          id: (int)$personId,
          context: ProvisioningContextEnum::Automatic
        );

        $this->Flash->success(__d('result', 'TermsAndConditions.revoked'));
      } else {
        // This is effectively an error since the revoke action shouldn't have
        // been available if there are no T&C Agreements to revoke

        $this->Flash->error(__d('error', 'TermsAndConditions.revoke.none'));
      }

      return $this->redirect(['action' => 'status', '?' => ['person_id' => $personId]]);
    }
    catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
      return $this->generateRedirect(null);
    }
  }

  /**
   * Generate a status index.
   *
   * @since  COmanage Registry v5.2.0
   */

  public function status() {
    $personId = $this->request->getQuery('person_id');

    if(empty($personId)) {
      // We shouldn't actually get here without person_id set since getPrimaryLink()
      // will (eventually) require it.
      throw new \InvalidArgumentException(__d('error', 'notprov', 'person_id'));
    }

    $this->set('vv_tandc_statuses', $this->TermsAndConditions->status((int)$personId));
    $this->set('vv_person_id', (int)$personId);
    
    $this->set('vv_title', __d('controller', 'TermsAndConditions'));
  }
}