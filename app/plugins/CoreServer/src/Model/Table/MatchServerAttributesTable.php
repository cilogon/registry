<?php
/**
 * COmanage Registry Math Server Attributes Table
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

use Cake\Core\Plugin;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Utility\Hash;
use Cake\Validation\Validator;
use App\Lib\Enum\RequiredEnum;

class MatchServerAttributesTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
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
    $this->belongsTo('CoreServer.MatchServers');
    $this->belongsTo('Types');

    $this->setDisplayField('attribute');

    $this->setPrimaryLink('CoreServer.match_server_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal('index');

    $this->setAutoViewVars([
      'attributes' => [
        'type' => 'hash',
        'hash' => Hash::combine($this->supportedAttributes(), '{s}.wire', '{s}.label')
      ],
      // Because of the way autoviewvars calculates variable names, "requireds" is correct
      'requireds' => [
        'type'  => 'enum',
        'class' => 'RequiredEnum'
      ],
      'addressTypes' => [
        'type' => 'type',
        'attribute' => 'Addresses.type'
      ],
      'emailAddressTypes' => [
        'type' => 'type',
        'attribute' => 'EmailAddresses.type'
      ],
      'identifierTypes' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ],
      'nameTypes' => [
        'type' => 'type',
        'attribute' => 'Names.type'
      ],
      'telephoneNumberTypes' => [
        'type' => 'type',
        'attribute' => 'TelephoneNumbers.type'
      ],
      'types' => [
        'type' => 'type'
      ]
    ]);

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
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
   * Obtain the set of attributes supported for Match requests.
   *
   * @since  COmanage Registry v5.2.0
   * @return array Array of supported attributes
   */

  public function supportedAttributes(): array {
    return [
      // The array key should match the wire name
      'addresses' => [
        'label' => __d('controller', 'Addresses', [1]),
        'model' => 'Addresses',
        // array values are "registry name" => "attribute dictionary name"
        'attributes' => [
          // We'll use the virtual attribute to get a formatted address.
          // We could provide individual attributes (similar to Name) but
          // we don't have any actual use cases for this yet.
          'formatted_address' => 'formatted'
        ],
        'type' => 'Addresses.type',
        'wire' => 'addresses'
      ],
      'dateOfBirth' => [
        'label' => __d('field', 'date_of_birth'),
        'model' => 'ExternalIdentities',
        // Note the use of singular "attribute", vs "attributes" for MVPAs
        'attribute' => 'date_of_birth',
        'type' => false,
        'wire' => 'dateOfBirth'
      ],
      'emailAddresses' => [
        'label' => __d('field', 'mail'),
        'model' => 'EmailAddresses',
        'attributes' => [
          'mail' => 'address'
        ],
        // type corresponds to Types::$supportedAttributes, and will automatically
        // be injected into the wire representation (ie: do not list it under
        // "attributes")
        'type' => 'EmailAddresses.type',
        'wire' => 'emailAddresses'
      ],
      'identifiers' => [
        'label' => __d('field', 'identifier'),
        'model' => 'Identifiers',
        'attributes' => [
          'identifier' => 'identifier'
        ],
        'type' => 'Identifiers.type',
        'wire' => 'identifiers'
      ],
      'names' => [
        'label' => __d('field', 'name'),
        'model' => 'Names',
        'attributes' => [
          'honorific' => 'prefix',
          'given' => 'given',
          'middle' => 'middle',
          'family' => 'family',
          'suffix' => 'suffix'
        ],
        'type' => 'Names.type',
        'wire' => 'names'
      ],
      'telephoneNumbers' => [
        'label' => __d('field', 'TelephoneNumbers.number'),
        'model' => 'TelephoneNumbers',
        'attributes' => [
          // We'll use the virtual attribute to get a formatted number
          'formatted_number' => 'number'
        ],
        'type' => 'TelephoneNumbers.type',
        'wire' => 'telephoneNumbers'
      ]
    ];
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

    $validator->add('match_server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('match_server_id');

    $validator->add('attribute', [
      'content' => ['rule' => ['inList', Hash::extract($this->supportedAttributes(), '{s}.wire')]]
    ]);
    $validator->notEmptyString('attribute');

    $validator->add('type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    // Required really depends on attribute
    $validator->allowEmptyString('type_id');

    $validator->add('required', [
      'content' => ['rule' => ['inList', RequiredEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('required');

    return $validator;
  }
}
