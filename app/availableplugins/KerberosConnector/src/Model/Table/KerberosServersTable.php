<?php
/**
 * COmanage Registry Kerberos Servers Table
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

namespace KerberosConnector\Model\Table;

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use \App\Lib\Enum\SuspendableStatusEnum;

class KerberosServersTable extends Table {
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.2.0
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
   * Establish a connection to the specified Kerberos server.
   *
   * @since  COmanage Registry v5.2.0
   * @param  int    $serverId Server ID (NOT KerberosServer ID)
   * @param  bool   $admin    If true, establish a kadmin connetion using the Admin Principal and Keytab
   * @return mixed            KADM5 object if $admin is true
   * @throws Exception
   */

  public function connect(int $serverId, bool $admin): \KADM5 {
    // Pull our configuration via the parent Server object.
    $server = $this->Servers->get($serverId, contain: ['KerberosServers']);

    if($server->status != SuspendableStatusEnum::Active) {
      throw new \InvalidArgumentException(__d('error', 'inactive', [__d('controller', 'Servers', [1]), $serverId]));
    }

    if(empty($server->kerberos_server->admin_principal)
       || empty($server->kerberos_server->keytab_path)) {
      throw new \InvalidArgumentException(__d('kerberos_connector', 'error.KerberosServers.admin.cfg'));
    }

    // If we omit this configuration, the local krb5.conf values will be used,
    // but that would be confusing so we require the settings and check for them above.
    $config = [
      'realm' => $server->kerberos_server->realm,
      'admin_server' => $server->kerberos_server->hostname
    ];

    if(!empty($server->kerberos_server->port) && (int)$server->kerberos_server->port > 0) {
      $config['admin_port'] = $server->kerberos_server->port;
    }

    if(!is_readable($server->kerberos_server->keytab_path)) {
      throw new \InvalidArgumentException(__d('error', 'file', [$server->kerberos_server->keytab_path]));
    }

    return new \KADM5(
      principal: $server->kerberos_server->admin_principal,
      credentials: $server->kerberos_server->keytab_path,
      use_keytab: true,
      config: $config
    );
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
    
    $this->registerStringValidation($validator, $schema, 'hostname', true);

    $validator->add('port', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->allowEmptyString('port');

    $this->registerStringValidation($validator, $schema, 'realm', true);
    
    $this->registerStringValidation($validator, $schema, 'admin_principal', false);

    $this->registerStringValidation($validator, $schema, 'keytab_path', false);
    
    return $validator;
  }
}
