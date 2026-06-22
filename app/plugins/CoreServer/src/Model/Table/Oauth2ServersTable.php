<?php
/**
 * COmanage Registry Oauth2 Servers Table
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

namespace CoreServer\Model\Table;

use Cake\Routing\Router;
use Cake\Validation\Validator;
use CoreServer\Lib\Enum\GrantTypesEnum;

class Oauth2ServersTable extends HttpServersTable {
  use \App\Lib\Traits\PrimaryLinkTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.1.0
   * @param  array  $config Configuration options passed to constructor
   */

  public function initialize(array $config): void {
    parent::initialize($config);

    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Timestamp');

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);

    // Define associations
    // this is defined in HttpServersTable
    // $this->belongsTo('Servers');

    $this->setDisplayField('hostname');

    $this->setPrimaryLink('server_id');
    $this->setAllowLookupPrimaryLink(['token', 'callback']);

    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'types' => [
        'type' => 'enum',
        'class' => 'CoreServer.GrantTypesEnum'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin'],
        'token' =>    ['platformAdmin', 'coAdmin'],
        'callback' => true,
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin'],
      ]
    ]);
  }

  /**
   * Exchange an authorization code for an access and refresh token.
   *
   * @param  int|string $id          Oauth2Server ID
   * @param  string     $code        Access code returned by call to /oauth/authorize
   * @param  string     $redirectUri Callback URL used for initial request
   * @return mixed Object of data as returned by server, including access and refresh token
   * @throws RuntimeException
   *@since  COmanage Registry v5.2.0
   */

  public function exchangeCode(int|string $id, string $code, string $redirectUri, $store=true): mixed
  {
    return $this->obtainToken((int)$id, 'authorization_code', $code, $redirectUri, $store);
  }

  /**
   * Obtain an OAuth token.
   *
   * @param int $id          Oauth2Server ID
   * @param string $grantType   OAuth grant type
   * @param  string|null  $code        Access code returned by call to /oauth/authorize, for authorization_code grant
   * @param string|null $redirectUri Callback URL used for initial request, for authorization_code grant
   * @param bool $store       If true, store the retrieved tokens in the Oauth2Server configuration
   * @return mixed               Object of data as returned by server, including access and refresh token
   * @throws RuntimeException
   *@since  COmanage Registry v5.2.0
   */

  public function obtainToken(
    int $id,
    string $grantType,
    ?string $code=null,
    ?string $redirectUri=null,
    bool $store=true)
  : mixed {
    // Pull our configuration
    $srvr = $this->get($id);

    $httpClient = $this->createHttpClient($id);

    $postData = [
      'client_id'     => $srvr->clientid,
      'client_secret' => $srvr->client_secret,
      'grant_type'    => $grantType
    ];

    if($grantType == 'refresh_token') {
      $postData['refresh_token'] = $srvr->refresh_token;
      $postData['format'] = 'json';
    } elseif($grantType == 'authorization_code' && $code) {
      $postData['code'] = $code;
      $postData['redirect_uri'] = $redirectUri;
    } else {
      $postData['scope'] = str_replace(' ', '%20', $srvr->scope);
    }

    $postUrl = $srvr->url . "/token";

    $results = $httpClient->post($postUrl, $postData);

    $json = json_decode($results->getStringBody());

    if($results->getStatusCode() != 200) {
      // There should be an error in the response
      throw new \RuntimeException(__d('core_server', 'error.Oauth2Servers.token', [$json->error . ": " . $json->error_description]));
    }

    if($store) {
      // Save the fields we want to keep
      $data = [
        'id' => $id,
        'access_token' => $json->access_token,
        // Store the raw result in case the server has added some custom attributes
        'token_response' => json_encode($json)
      ];

      // We shouldn't have a new refresh token on a refresh_token grant
      // (which just gets us a new access token). Additionally, section
      // 4.4.3 of RFC 6749 explains that the server should NOT return
      // a refresh token for a client credentials grant.
      if($grantType != 'refresh_token' && property_exists($json, 'refresh_token')) {
        $data['refresh_token'] = $json->refresh_token;
      }

      // If the Oauth2 server returned `expires_in` use it to set the
      // access token expiration time. See section 5.1 of RFC 6749.
      if(property_exists($json, 'expires_in')) {
        $data['access_token_exp'] = time() + $json->expires_in;
      }

      // Update the dataset
      $srvr = $this->patchEntity($srvr, $data);
      if (!$this->save($srvr)) {
        throw new \RuntimeException(__d('error', 'save' [__d('core_server', 'field.Oauth2Servers.access_token')]));
      }
    }

    return $json;
  }


  /**
   * Generate a redirect URI for the given server ID.
   *
   * @param int|string $id The unique identifier of the OAuth2 server
   * @return string         The full URL of the redirect URI
   */
  public function redirectUri(int|string $id): string
  {
    $callback = [
      'plugin' => 'CoreServer',
      'controller' => 'Oauth2Servers',
      'action' => 'callback',
      $id
    ];

    return Router::url($callback, true);
  }


  /**
   * Refresh the OAuth access token using the stored refresh token.
   *
   * @param int|string $id The unique identifier of the OAuth2 server
   * @return string The new access token
   * @throws RuntimeException
   * @since COmanage Registry v5.2.0
   */
  public function refreshToken(int|string $id):string
  {
    $json = $this->obtainToken((int)$id, 'refresh_token');

    return $json->access_token;
  }


  /**
   * Set validation rules.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */

  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('server_id');

    $validator->add('access_grant_type', [
      'content' => ['rule' => ['inList', GrantTypesEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('access_grant_type');

    $validator->add('url', ['content' => ['rule' => 'url']]);
    $validator->notEmptyString('url');
    
    $validator->notEmptyString('clientid');
    $validator->notEmptyString('client_secret');

    $validator->add('scope', [
      'content' => [
        'rule' => 'validateNotBlank',
        'provider' => 'table'
      ]
    ]);
    $validator->notEmptyString('scope');

    $validator->add('refresh_token', [
      'content' => [
        'rule' => 'validateNotBlank',
        'provider' => 'table'
      ]
    ]);
    $validator->allowEmptyString('refresh_token');
    
    $validator->add('access_token', [
      'content' => [
        'rule' => 'validateNotBlank',
        'provider' => 'table'
      ]
    ]);
    $validator->allowEmptyString('access_token');

    $validator->add('token_response', [
      'content' => [
        'rule' => 'validateNotBlank',
        'provider' => 'table'
      ]
    ]);
    $validator->allowEmptyString('token_response');

    $validator->integer('access_token_exp')
              ->allowEmptyString('access_token_exp');

    return $validator;
  }
}
