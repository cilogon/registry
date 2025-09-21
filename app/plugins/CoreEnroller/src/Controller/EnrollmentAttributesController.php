<?php
/**
 * COmanage Registry Enrollment Attributes Controller
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

use Cake\ORM\TableRegistry;
use \App\Controller\StandardEnrollerController;
use \App\Lib\Util\StringUtilities;

class EnrollmentAttributesController extends StandardEnrollerController {
  protected array $paginate = [
    'order' => [
      'EnrollmentAttributes.ordr' => 'asc'
    ]
  ];
  
  /**
   * Callback run prior to the request rendering.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   * @return EventInterface
   */

  public function beforeRender(\Cake\Event\EventInterface $event) {
    $this->set('vv_supported_attributes', $this->EnrollmentAttributes->supportedAttributes());

    $ret = parent::beforeRender($event);

    $attributes = $this->viewBuilder()->getVar('attributes');

    // Override the auto-generated title
    switch($this->request->getParam('action')) {
      case 'add':
        $this->set(
          'vv_title',
          __d('operation', 'add.a-1', [
            __d('core_enroller', 'controller.EnrollmentAttributes', [1]),
            $attributes[$this->request->getQuery('attribute_type')]
          ])
        );
        break;
      case 'edit':
        $vv_obj = $this->viewBuilder()->getVar('vv_obj');
        $this->set(
          'vv_title',
          __d('operation', 'edit.a-1', [
            __d('core_enroller', 'controller.EnrollmentAttributes', [1]),
            $attributes[ $vv_obj['attribute'] ]
          ])
        );
        break;
      case 'index':
        $this->set('vv_title', __d('core_enroller', 'controller.EnrollmentAttributes', [99]));
        break;
    }

    return $ret;
  }
}
