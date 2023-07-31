<?php
/**
 * COmanage Registry Provisionable Trait
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

namespace App\Lib\Traits;

use Cake\ORM\TableRegistry;
use \App\Lib\Util\StringUtilities;

trait ProvisionableTrait {
  // We use a trait and not a behavior so method_exists($table, "requestProvisioning").
  // works. The primary function is called "requestProvisioning" so as not to conflict
  // with provision() used by the plugins, as called via (eg) StandardController.
  // (ie: Standard controller needs to tell the difference between models that are
  // provisionable vs plugin models that actually implement provisioning.)

  /**
   * Request provisioning.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  int                      $id                   This table's entity ID to provision
   * @param  ProvisioningContextEnum  $context              Context in which provisioning is being requested
   * @param  int                      $provisioningTargetId If set, the Provisioning Target ID to request provisioning for (otherwise all)
   * @throws InvalidArgumentException
   */

  public function requestProvisioning(
    int     $id,
    string  $context,
    ?int    $provisioningTargetId=null) {
    if(method_exists($this, 'marshalProvisioningData')) {
      // The model specific marshalProvisioningData implementations are expected
      // to properly handle deleted records.
      $data = $this->marshalProvisioningData($id);

      // Invocation of the plugins is handled by the Pluggable table
      $ProvisioningTargets = TableRegistry::getTableLocator()->get('ProvisioningTargets');

      $ProvisioningTargets->provision(
        data: $data['data'], 
        eligibility: $data['eligibility'],
        context: $context,
        id: $provisioningTargetId
      );
    } else {
      // This is a secondary model, eg Names. We need to figure out the primary model
      // and then request provisioning on that one instead.

      // We need to explicitly look at archived records here. A deleted record
      // may point to a valid primary object.

      $primaryLink = $this->findPrimaryLink(id: $id, archived: true);
      
      $parentTableName = StringUtilities::foreignKeyToClassName($primaryLink->attr);

      $this->$parentTableName->requestProvisioning(
        id: $primaryLink->value,
        context: $context,
        provisioningTargetId: $provisioningTargetId
      );
    }
  }
}