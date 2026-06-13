<?php
/**
 * COmanage Registry Mostly Static Resources Controller
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace App\Controller;

// XXX not doing anything with Log yet
use Cake\Log\Log;
use App\Lib\Util\StringUtilities;

class MostlyStaticResourcesController extends StandardController {
  protected array $paginate = [
    'order' => [
      'MostlyStaticResources.title' => 'asc'
    ]
  ];

  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.3.0
   * @param  EventInterface $event Cake Event
   * @return \Cake\Http\Response   HTTP Response
   */

  public function beforeRender(\Cake\Event\EventInterface $event) {
    $this->set('vv_base_url', \Cake\Routing\Router::url(
      // We use the "/media" path to avoid conflicts with Mostly Static Pages
      url: StringUtilities::mediaUrl($this->getCOID(), ""),
      full: true
    ));

    return parent::beforeRender($event);
  }
}