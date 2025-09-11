<?php
/**
 * COmanage Registry People Controller
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
use Cake\ORM\TableRegistry;
use http\QueryString;

class PeopleController extends StandardController {
  protected array $paginate = [
    'order' => [
// XXX this will sort by family name, but it this universally correct?
// so we need a configuration, or can we do something automagic?
// (ie: what is CJK sort order?)
// C=pinyin, so basically latin; J=KSTNHMYRW/AIUEO; K=hangugl
// so basically a mess... let's just use family name for now and wait for
// (and we haven't even gotten to other languages like Hindi)
// someone to file an RFE
      'PrimaryName.family' => 'asc',
      'PrimaryName.given' => 'asc'
    ],
    'sortableFields' => [
      'PrimaryName.given',
      'PrimaryName.family'
    ],
    'finder' => 'indexed'
  ];

  /**
   * Perform Cake Controller initialization.
   *
   * @since  COmanage Registry v5.2.0
   */
  public function initialize(): void
  {
    parent::initialize();

    if (!$this->request->is('restful') && !$this->request->is('ajax')) {
      unset($this->paginate['finder']);
    }
  }
  
  /**
   * Callback run prior to the request render.
   *
   * @since  COmanage Registry v5.0.0
   * @param  EventInterface $event Cake Event
   */
  
  public function beforeRender(\Cake\Event\EventInterface $event) {
    if(!$this->request->is('restful') && $this->request->getParam('action') == 'add') {
      // Get the set of permitted and required name fields to pass to the view.
      
      // We need to pull a few settings for default enrollment.
// XXX maybe $CoSettings should be available via AppController, like $this->getCOID()?
      $CoSettings = TableRegistry::getTableLocator()->get('CoSettings');
      
      $settings = $CoSettings->find()->where(['co_id' => $this->getCOID()])->firstOrFail();
      
      $this->set('vv_permitted_name_fields', $settings->name_permitted_fields_array());
      $this->set('vv_required_name_fields', $settings->name_required_fields_array());
      $this->set('vv_default_name_type', $settings->default_name_type_id);
    }
    
    return parent::beforeRender($event);
  }
}