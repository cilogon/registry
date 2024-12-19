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
use App\Lib\Enum\PetitionStatusEnum;
use Cake\Event\EventInterface;
use Cake\Http\Response;

class AttributeCollectorsController extends StandardEnrollerController {
  public $paginate = [
    'order' => [
      'AttributeCollectors.id' => 'asc'
    ]
  ];

  /**
   * Callback run prior to the request render.
   *
   * @param   EventInterface  $event  Cake Event
   *
   * @return Response|void
   * @since  COmanage Registry v5.0.0
   */

  public function beforeRender(EventInterface $event) {
    $link = $this->getPrimaryLink(true);

    if(!empty($link->value)) {
      $this->set('vv_bc_parent_obj', $this->AttributeCollectors->EnrollmentFlowSteps->get($link->value));
      $this->set('vv_bc_parent_displayfield', $this->AttributeCollectors->EnrollmentFlowSteps->getDisplayField());
      $this->set('vv_bc_parent_primarykey', $this->AttributeCollectors->EnrollmentFlowSteps->getPrimaryKey());
    }

    return parent::beforeRender($event);
  }

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
        $this->AttributeCollectors->upsert(
          id:         (int)$id,
          petitionId: $petition->id,
          // Remove form metadata from the set of attributes we try to upsert
          attributes: array_diff_key($this->request->getData(), ['petition_id' => true, 'token' => true])
        );

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
                    ->all());

    $this->render('/Standard/dispatch');
  }

  /**
   * Display information about this Step.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  string  $id    Attribute Collector ID
   */

  public function display(string $id) {
    debug("display something for this petition");
    debug($this->getPetition());
  }
}
