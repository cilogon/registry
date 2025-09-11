<?php
/**
 * COmanage Registry Oauth2 Servers Controller
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

namespace CoreServer\Controller;

use App\Controller\StandardPluginController;
use Cake\Event\EventInterface;
use CoreServer\Lib\Enum\GrantTypesEnum;

class Oauth2ServersController extends StandardPluginController
{
  protected array $paginate = [
    'order' => [
      'OauthServers.url' => 'asc'
    ]
  ];


  /**
   * Callback run prior to the request render.
   *
   * @param EventInterface $event Cake Event
   *
   * @since  COmanage Registry v5.2.0
   */

  public function beforeRender(EventInterface $event)
  {
    // Generate the Redirect URI

    if ($this->getRequest()->getParam('action') === 'edit') {
      $id = $this->getRequest()->getParam('pass')[0] ?? null; // Assuming $id comes from passed arguments
      $this->set('vv_redirect_uri', $this->Oauth2Servers->redirectUri($id));
    }

    return parent::beforeRender($event);
  }

  /**
   * OAuth callback.
   *
   * @param integer $id Oauth2Server ID
   * @since  COmanage Registry v5.2.0
   */

  public function callback($id): void
  {
    $this->autoRender = false;
    try {
      $request = $this->getRequest();
      $code = $request->getQuery('code');
      $state = $request->getQuery('state');

      if (empty($code) || empty($state)) {
        throw new \RuntimeException(__d('core_server', 'error.Oauth2Servers.callback'));
      }

      // Verify that state is our hashed session ID, as per RFC6749 §10.12
      // recommendations to prevent CSRF.
      // https://tools.ietf.org/html/rfc6749#section-10.12

      // Access session from the request object
      $sessionId = $request->getSession()->id();

      if ($state != hash('sha256', $sessionId)) {
        throw new \RuntimeException(__d('core_server', 'error.Oauth2Servers.state'));
      }

      $response = $this->Oauth2Servers->exchangeCode(
        $id,
        $code,
        $this->Oauth2Servers->redirectUri((int)$id),
      );

      $this->Flash->success(__d('core_server', 'info.Oauth2Servers.access_token.ok'));
    } catch (\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    $this->performRedirect();
  }

  /**
   * Obtain an access token for a Oauth2Server.
   *
   * @since  COmanage Registry v5.2.0
   * @param  integer $id Oauth2Server ID
   */

  public function token($id): void
  {
    // Pull our configuration, initially to find out what type of grant type we need
    $osrvr = $this->Oauth2Servers->get($id);

    if(!$osrvr) {
      $this->Flash->error(__d('error', 'notfound', [__d('core_server', 'controller.Oauth2Servers')]));
      $this->performRedirect();
    }

    try {
      switch($osrvr->access_grant_type) {
        case GrantTypesEnum::AuthorizationCode:
          // Issue a redirect to the server
          $targetUrl = $osrvr->url
            . '/authorize?response_type=code'
            . '&client_id=' . $osrvr->clientid
            . '&redirect_uri=' . urlencode($this->Oauth2Servers->redirectUri($id))
            . '&state=' . hash('sha256', session_id());
          // Scope is optional
          if(!empty($osrvr->scope)) {
            $targetUrl .= '&scope='. str_replace(' ', '%20', $osrvr->scope);
          }

          $this->redirect($targetUrl);
          break;
        case GrantTypesEnum::ClientCredentials:
          // Make a direct call to the server
          $this->Oauth2Servers->obtainToken((int)$id, 'client_credentials');
          $this->Flash->success(__d('core_server', 'info.Oauth2Servers.access_token.ok'));
          break;
        default:
          // No other flows currently supported
          throw new \LogicException('Not implemented yet.');
      }
    } catch(\Exception $e) {
      $this->Flash->error($e->getMessage());
    }

    $this->performRedirect();
  }

  /**
   * Perform a redirect back to the controller's default view.
   *
   * @since  COmanage Registry v5.2.0
   */

  function performRedirect(): void
  {
    $target = [];
    $target['plugin'] = null;

    if (!empty($this->getRequest()->getParam('pass')[0])) {
      $target['plugin'] = 'CoreServer';
      $target['controller'] = 'Oauth2Servers';
      $target['action'] = 'edit';
      $target[] = filter_var($this->getRequest()->getParam('pass')[0], FILTER_SANITIZE_SPECIAL_CHARS);
    } else {
      $target['controller'] = 'Servers';
      $target['action'] = 'index';
      $target['?'] = [
        'co_id' => $this->getCOID()
      ];
    }

    $this->redirect($target);
  }
}
