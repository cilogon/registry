<?php
/**
 * COmanage Registry HTTP Servers Table
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types=1);

namespace CoreServer\Model\Table;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Client;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use CoreServer\Lib\Enum\HttpAuthTypeEnum;

class HttpServersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

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
    $this->belongsTo('Servers');

    $this->setDisplayField('hostname');

    $this->setPrimaryLink('server_id');
    $this->setRequiresCO(true);

    $this->setAutoViewVars([
      'authTypes' => [
        'type' => 'enum',
        'class' => 'CoreServer.HttpAuthTypeEnum'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }

  /**
   * Create an HTTP Client from the HttpServer configuration.
   * 
   * @since  COmanage Registry v5.1.0
   * @param  int  $id   HttpServer ID
   * @return Client     Cake Http Client
   */

  public function createHttpClient(int $id): Client {
    // Pull the requested configuration

    $httpServer = $this->get($id);
    
    // In order to simplify our code (ie: to avoiding parsing the HttpServer URL)
    // we create the client via URL, then update the configuration for additional options.

    $Client = Client::createFromUrl($httpServer->url);

    if($httpServer->auth_type == HttpAuthTypeEnum::Basic) {
      if(!empty($httpServer->username)) {
        $Client->setConfig('username', $httpServer->username);
      }

      if(!empty($httpServer->password)) {
        $Client->setConfig('password', $httpServer->password);
      }
    }

    if(!empty($httpServer->skip_ssl_verification) && $httpServer->skip_ssl_verification) {
      $Client->setConfig('ssl_verify_peer', false);
    }

    return $Client;
  }

  /**
   * Set validation rules.
   *
   * @since  COmanage Registry v5.1.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   */

  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();

    $validator->add('server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('server_id');
    
    $validator->add('url', [
      'content' => ['rule'     => ['url'],
                    'message'  => __d('error', 'input.invalid.url')]
    ]);

    $this->registerStringValidation($validator, $schema, 'url', true);

    $this->registerStringValidation($validator, $schema, 'username', false);

    $this->registerStringValidation($validator, $schema, 'password', false);

    $validator->add('auth_type', [
      'content' => ['rule' => ['inList', HttpAuthTypeEnum::getConstValues()]]
    ]);
    $validator->allowEmptyString('auth_type');

    $validator->add('skip_ssl_verification', [
      'content' => ['rule' => ['boolean']]
    ]);
    $validator->allowEmptyString('skip_ssl_verification');
    
    return $validator;
  }
}
