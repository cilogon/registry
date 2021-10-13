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
//use \App\Lib\Enum\PermissionEnum;

class PeopleController extends StandardController {
// XXX need to update for couadmin
  protected $permissions = [
    // Actions that operate over an entity (ie: require an $id)
    'entity' => [
/*
We should add a more configurable permissions setting that controls CO Person
visibility, probably via CO Settings. eg:
 CO Admin - Only CO Admins can see CO Person records
 COU Admin - COU Admins can see CO Person records, plus CO Person Role records they manage
 Any Admin - Any CO or COU Admin can see any CO Person and CO Person Role record
 CO Group - (intended for helpdesk, maybe create a special helpdesk group instead?) Any Admin + members of the Group
 
 We might also want to introduce a new "Permission" object to abstract this out here
 and in other places (like Enrollment Flow Authz). Though Permissions would still be
 managed in the relevant UI (eg: CO Settings), the model abstraction would handle
 rendering a View Element and processing the Permission at run time
 
 See also: CO-931, CO-1156, CO-1524
 */
      'canvas' =>   ['platformAdmin', 'coAdmin'],
      'delete' =>   ['platformAdmin', 'coAdmin'],
      'edit' =>     ['platformAdmin', 'coAdmin'],
      'view' =>     ['platformAdmin', 'coAdmin']
    ],
    // Actions that operate over a table (ie: do not require an $id)
    'table' => [
      'add' =>      ['platformAdmin', 'coAdmin'],
      'index' =>    ['platformAdmin', 'coAdmin']
    ]
  ];
  
  public $pagination = [
    'order' => [
// XXX this will sort by family name, but it this universally correct?
// so we need a configuration, or can we do something automagic?
// (ie: what is CJK sort order?)
// C=pinyin, so basically latin; J=KSTNHMYRW/AIUEO; K=hangugl
// so basically a mess... let's just use family name for now and wait for
// (and we haven't even gotten to other languages like Hindi)
// someone to file an RFE
      'PrimaryName.family' => 'asc'
    ],
    'sortableFields' => [
      'PrimaryName.given',
      'PrimaryName.family'
    ]
  ];
  
  /**
   * Handle a canvas request.
   *
   * @since  COmanage Registry v5.0.0
   * @param  integer $id CO Person ID
   */
  // XXX docblock
  
  public function canvas($id) {
    $this->edit($id);
  }
}