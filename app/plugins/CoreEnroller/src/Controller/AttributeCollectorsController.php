<?php
/**
 * COmanage Registry Attribute Collectors Controller
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
 * @package       registry-plugins
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreEnroller\Controller;

use App\Controller\StandardEnrollerController;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\ORM\TableRegistry;

class AttributeCollectorsController extends StandardEnrollerController {
  protected array $paginate = [
    'order' => [
      'AttributeCollectors.id' => 'asc'
    ]
  ];

  /**
   * Dispatch an Enrollment Flow Step.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string  $id    Attribute Collector ID
   */

  public function dispatch(string $id) {
    $petition = $this->getPetition();

    if($this->request->is(['post', 'put'])) {
      try {
        // Determine before storing, since storing the collected COU changes the comparison
        $lockedCouId = $this->AttributeCollectors->lockedCouId((int)$petition->id);

        $this->AttributeCollectors->upsert(
          id:         (int)$id,
          petitionId: $petition->id,
          // Remove form metadata from the set of attributes we try to upsert
          attributes: array_diff_key($this->request->getData(), ['petition_id' => true, 'token' => true])
        );

        $this->persistPetitionCouIdFromCollectedAttributes((int)$petition->id, (int)$id, $lockedCouId);

        // On success, indicate the step is completed and generate a redirect
        // to the next step

        $link = $this->getPrimaryLink(true);

        return $this->finishStep(
          enrollmentFlowStepId: $link->value,
          petitionId:           $petition->id,
          comment:              __d('core_enroller', 'result.attr.saved')
        );
      }
      catch(\Exception $e) {
        $this->Flash->error($e->getMessage());
      }
    }

    // Fall through and let the form render

    // A COU attached to the Petition by another component (eg the COU Selector) takes
    // precedence over any COU collected here, so the form shows it read-only. A COU
    // collected by a previous Attribute Collector step is not locked: the field is
    // pre-populated with it and can be changed.
    $this->set('vv_petition_cou_id',
               $this->AttributeCollectors->lockedCouId((int)$petition->id));

    // Pull the configured set of attributes
    $this->set('vv_enrollment_attributes', 
               $this->AttributeCollectors
                    ->EnrollmentAttributes
                    ->find()
                    ->where(['attribute_collector_id' => $id])
                    ->all());
    
    // We support updating attributes until the Petition is finalized,
    // see if there happen to be any values already stored. Note this
    // will pull _all_ attributes associated with the Petition, not just
    // those associated with this Attribute Collector.
    $this->set('vv_petition_attributes',
               $this->AttributeCollectors
                    ->EnrollmentAttributes
                    ->PetitionAttributes
                    ->find()
                    ->where(['petition_id' => $petition->id])
                    ->contain(['EnrollmentAttributes'])
                    ->all());

    $this->render('/Standard/dispatch');
  }

  /**
   * If the Attribute Collector collected a COU for later context (eg approvals),
   * persist Petition.cou_id during dispatch (not during finalization).
   *
   * @since  COmanage Registry v5.3.0
   * @param  int      $petitionId
   * @param  int|null $attributeCollectorId Attribute Collector ID
   * @return void
   */
  protected function persistPetitionCouIdFromCollectedAttributes(int $petitionId, ?int $attributeCollectorId = null, ?int $lockedCouId = null): void {
    $petitionAttributesTable = $this->AttributeCollectors
      ->EnrollmentAttributes
      ->PetitionAttributes;

    // Fetch petition attributes + enrollment attribute data
    $query = $petitionAttributesTable
      ->find()
      ->where(['PetitionAttributes.petition_id' => $petitionId])
      ->contain(['EnrollmentAttributes']);

    if($attributeCollectorId !== null) {
      $query->matching('EnrollmentAttributes', function($q) use ($attributeCollectorId) {
        return $q->where(['EnrollmentAttributes.attribute_collector_id' => $attributeCollectorId]);
      });
    }

    $attrs = $query->toArray();

    // Find the first "cou_id" with a non-empty value
    // NOTE: Attribute Collector configuration can include multiple COU attributes/values, but the
    // Petition record has only a single `cou_id` column. By convention/assumption we therefore
    // select the first non-empty collected `cou_id` and ignore any additional values.
    $couAttr = null;
    foreach ($attrs as $pa) {
      if (
        !empty($pa->enrollment_attribute)
        && ($pa->enrollment_attribute->attribute ?? null) === 'cou_id'
        && ($pa->value ?? null) !== null
        && ($pa->value ?? null) !== ''
      ) {
        $couAttr = $pa;
        break;
      }
    }

    // A COU attached by another component (eg the COU Selector) is never overwritten,
    // it also takes precedence at finalization.
    if(!$couAttr || !empty($lockedCouId)) {
      return;
    }

    $petitionsTable = $this->fetchTable('Petitions');
    $petition = $petitionsTable->get($petitionId);

    if((int)$petition->cou_id === (int)$couAttr->value) {
      return;
    }

    // Not locked: the COU was empty or collected by an earlier step, so the latest wins
    $petition->cou_id = (int)$couAttr->value;
    $petitionsTable->saveOrFail($petition);
  }
}
