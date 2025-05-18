<?php
/**
 * COmanage Registry Authentication Events Table
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

namespace App\Model\Table;

use \Cake\Http\Exception\UnauthorizedException;
use \Cake\ORM\Table;
use \Cake\ORM\TableRegistry;
use \Cake\Validation\Validator;
use \App\Lib\Enum\AuthenticationEventEnum;
use \App\Lib\Util\StringUtilities;

class AuthenticationEventsTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\QueryModificationTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\TypeTrait;
  use \App\Lib\Traits\ValidationTrait;
// XXX do not enable
//  use \App\Lib\Traits\SearchFilterTrait;
  
  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.0.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Artifact);
    
    // Define associations
    // Technically, Authentication Events do not directly foreign key since
    // we store the actual identifier, not the target object. This implies
    // authentication events will not be purged if a CO is deleted, because they
    // are not technically part of the CO's tree of data. (AR-AuthenticationEvent-4)
    
    $this->setDisplayField('authenticated_identifier');
    
    $this->setRequiresCO(false);
    
    $this->setAutoViewVars([
      'authenticationEvents' => [
        'type' => 'enum',
        'class' => 'AuthenticationEventEnum'
      ]
    ]);

    $this->setPermissions(function (\Cake\Http\ServerRequest $r, \App\Controller\Component\RegistryAuthComponent $auth, ?int $id): array {
      // We're going to be called a bunch of times (once per row on the index view)
      // so we want to cache the result of the permission check on the requested identifier
      // (since all rows will be for the same identifier, at least for now).
      
      $targetIdentifier = $r->getQuery('authenticated_identifier');
      $manages = false;

      if($targetIdentifier) {
        $targetIdentifier = \App\Lib\Util\StringUtilities::urlbase64decode($targetIdentifier);
        
        $manages = $auth->isPlatformAdmin() || $auth->isAdminForIdentifier($targetIdentifier);
      } else {
        // We set $manages = true here because we need to return index
        // permission for related models calculation in identifiers?person_id=X
        // (so the link to Authentication Events renders). However, in
        // getIndexFilter below we will reject requests without a $targetIdentifier
        // for non-platform admins.
        
        $manages = true;
      }
      
      return [
        'entity' => [
          'delete'  => false,
          'edit'    => false,
          'view'    => false
        ],
        'table' => [
          'add'     => false,
          'index'   => $manages
        ]
      ];
    });
  }
  
  /**
   * Record an authentication event.
   *
   * @since  COmanage Registry v5.0.0
   * @param  string                  $identifier Authenticated identifier
   * @param  AuthenticationEventEnum $eventType  AuthenticationEventEnum
   * @param  string                  $remoteIp   Remote IP address, if known
   * @return int                                 Authentication Event Record ID
   */

  public function record(string $identifier, string $eventType, ?string $remoteIp=null): int {
    $record = [
      'authenticated_identifier'  => $identifier,
      'authentication_event'      => $eventType,
      'remote_ip'                 => $remoteIp
    ];
    
    $obj = $this->newEntity($record);
    
    $this->saveOrFail($obj);
    
    return $obj->id;
  }
  
  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.0.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $this->registerStringValidation($validator, $schema, 'authenticated_identifier', true);
    
    $validator->add('authentication_event', [
      'content' => ['rule' => ['inList', AuthenticationEventEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('authentication_event');
    
    $this->registerStringValidation($validator, $schema, 'remote_ip', false);
    
    return $validator; 
  }
}