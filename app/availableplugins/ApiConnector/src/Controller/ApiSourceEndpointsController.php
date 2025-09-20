<?php
/**
 * COmanage Registry Api Source Endpoints Controller
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace ApiConnector\Controller;

use Cake\Routing\Router;
use App\Controller\StandardPluginController;

class ApiSourceEndpointsController extends StandardPluginController {
  protected array $paginate = [
    'order' => [
      'ApiSourceEndpoints.id' => 'asc'
    ]
  ];

  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.2.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */

  public function beforeRender(\Cake\Event\EventInterface $event) {
    $vv_obj = $this->viewBuilder()->getVar('vv_obj');

    if(!empty($vv_obj->external_identity_source->api_source->id)) {
      // For consistency with other plugins, the data model points to the External Identity Source
      // but the API points to Api Source.
      
      $this->set(
        'vv_push_endpoint',
        Router::url(
          url: '/api/apisource/' 
                . $vv_obj->external_identity_source->api_source->id
                . '/v2/sorPeople/' 
                . $vv_obj->external_identity_source->sor_label,
          full: true
        )
      );
    }
    
    return parent::beforeRender($event);
  }
}
