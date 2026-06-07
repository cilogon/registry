<?php
/**
 * COmanage Registry Information Renderers Controller
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreEnroller\Controller;

use Cake\ORM\TableRegistry;
use App\Controller\StandardEnrollerController;

class InformationRenderersController extends StandardEnrollerController {
  protected array $paginate = [
    'order' => [
      'InformationRenderers.id' => 'asc'
    ]
  ];

  /**
   * Dispatch an Enrollment Flow Step.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  string  $id    Invitation Accepter ID
   */

  public function dispatch(string $id) {
    $petition = $this->getPetition();

    $cfg = $this->InformationRenderers->get($id);

    if($this->getRequest()->is('post')) {
      // Record that we displayed the splash page and finish the step

      return $this->finishStep(
        enrollmentFlowStepId: $cfg->enrollment_flow_step_id,
        petitionId:           $petition->id,
        comment:              __d('core_enroller', 'result.InformationRenderers.rendered')
      );
    }

    $this->set('vv_config', $cfg);

    $this->render('/Standard/dispatch');
  }
}