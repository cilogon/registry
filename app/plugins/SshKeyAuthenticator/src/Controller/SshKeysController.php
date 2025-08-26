<?php
/**
 * COmanage Registry SSH Keys Controller
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

namespace SshKeyAuthenticator\Controller;

use Cake\ORM\TableRegistry;
use App\Controller\MultipleAuthenticatorController;
use App\Lib\Util\StringUtilities;

class SshKeysController extends MultipleAuthenticatorController {
  public $paginate = [
    'order' => [
      'SshKeys.comment' => 'asc'
    ]
  ];

  /**
   * Handle an add action for an SSH Key.
   *
   * @since  COmanage Registry v5.2.0
   */
  
  public function add() {
    $obj = $this->SshKeys->newEmptyEntity();

    if(empty($this->requestParam('person_id'))) {
      throw new \InvalidArgumentException(__d('error', 'notprov', [__d('controller', 'People', [1])]));
    }
    
    if($this->request->is('post')) {
      try {
        $upload = $this->getRequest()->getData('keyFile')->getStream()->getContents();

        $obj = $this->SshKeys->addFromKeyFile(
          sshKeyAuthenticatorId: (int)$this->requestParam('ssh_key_authenticator_id'),
          personId: (int)$this->requestParam('person_id'),
          contents: $upload
        );

        return $this->generateRedirect($obj);
      }
      catch(\Exception $e) {
        // This throws \Cake\ORM\Exception\RolledbackTransactionException if
        // aborted in afterSave
        
        $this->Flash->error($e->getMessage());
      }
    }

    // Pass $obj as context so the view can render validation errors
    $this->set('vv_obj', $obj);

    // Default title is add new object
    [$title, $supertitle, $subtitle] = StringUtilities::entityAndActionToTitle($obj, 'SshKeys', 'add');
    $this->set('vv_title', $title);
    $this->set('vv_supertitle', $supertitle);
    $this->set('vv_subtitle', $subtitle);

    // Let the view render - see add.php for why we don't currently use the standard view
    // $this->render('/Standard/add-edit-view');
  }
}
