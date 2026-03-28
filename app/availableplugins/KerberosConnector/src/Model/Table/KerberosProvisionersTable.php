<?php
/**
 * COmanage Registry Kerberos Provisioners Table
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
 * @since         COmanage Registry v5.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace KerberosConnector\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\I18n\FrozenTime;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;
use PasswordAuthenticator\Lib\Enum\PasswordEncodingEnum;

use App\Lib\Enum\ProvisioningEligibilityEnum;
use App\Lib\Enum\ProvisioningStatusEnum;

class KerberosProvisionersTable extends Table {
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\ChangelogBehaviorTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\HistoryTrait;
  use \App\Lib\Traits\LabeledLogTrait;
  use \App\Lib\Traits\PermissionsTrait;
  use \App\Lib\Traits\PrimaryLinkTrait;
  use \App\Lib\Traits\ProvisionerTrait;
  use \App\Lib\Traits\TableMetaTrait;
  use \App\Lib\Traits\ValidationTrait;

  /**
   * Perform Cake Model initialization.
   *
   * @since  COmanage Registry v5.2.0
   * @param  array  $config Configuration options passed to constructor
   */
  
  public function initialize(array $config): void {
    // Timestamp behavior handles created/modified updates
    $this->addBehavior('Changelog');
    $this->addBehavior('Log');
    $this->addBehavior('Timestamp');
    
    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Configuration);
    
    // Define associations
    $this->belongsTo('Authenticators');
    $this->belongsTo('ProvisioningTargets');
    $this->belongsTo('Servers');
    $this->belongsTo('Types');
    
    $this->setDisplayField('server_id');
    
    $this->setPrimaryLink(['provisioning_target_id']);
    $this->setRequiresCO(true);
    
    $this->setAutoViewVars([
      'authenticators' => [
        'type' => 'plugin',
        'model' => 'PasswordAuthenticator.PasswordAuthenticators'
      ],
      'servers' => [
        'type' => 'plugin',
        'model' => 'KerberosConnector.KerberosServers'
      ],
      'types' => [
        'type' => 'type',
        'attribute' => 'Identifiers.type'
      ]
    ]);
    
    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   false, // Delete the pluggable object instead
        'edit' =>     ['platformAdmin', 'coAdmin'],
        'reapply' =>  ['platformAdmin', 'coAdmin'],
        'resync' =>   ['platformAdmin', 'coAdmin'],
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      false, //['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);

    $this->setProvisionableModels([
      'People'
    ]);
  }

  /**
   * Provision object data to the provisioning target.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  ProvisioningTarget           $provisioningTarget KerberosProvisioner configuration
   * @param  string                       $className          Class name of primary object being provisioned
   * @param  object                       $data               Provisioning data in Entity format (eg: \App\Model\Entity\Person)
   * @param  ProvisioningEligibilityEnum  $eligibility        Provisioning Eligibility Enum
   * @return array                                            Array of status, comment, and optional identifier
   */

  public function provision(
    \App\Model\Entity\ProvisioningTarget $provisioningTarget,
    string $className,
    object $data,       // $data is currently only \App\Model\Entity\Person, but that might change
    string $eligibility
  ): array {
    // We need to have an Identifier of the configured type and a Password associated
    // with the configured Authenticator.

    // We need the Kerberos Server configuration for the realm
    $server = $this->Servers->get(
      $provisioningTarget->kerberos_provisioner->server_id,
      contain: ['KerberosServers']
    );

    // Establish a connection to kadmin

    $cxn = $this->Servers->KerberosServers->connect(
      serverId: $provisioningTarget->kerberos_provisioner->server_id,
      admin: true
    );

    // First look for an Identifier of our configured Type. If we can't find one, we can't
    // do anything at all (including deprovisioning).

    $identifier = null;

    if(!empty($data->identifiers)) {
      $identifier = Hash::extract($data->identifiers, '{n}[type_id='.$provisioningTarget->kerberos_provisioner->type_id.']');
    }

    if(empty($identifier)) {
      throw new \RuntimeException(__d('kerberos_connector', 'error.principal.identifier'));
    }

    // We construct a principal with the realm for completeness and predictability,
    // but in general the KDC would just append the default realm if we only sent
    // the lefthand side

    $principal = $identifier[0]->identifier . "@" . $server->kerberos_server->realm;

    // Map the Authenticator ID (configured on the Provisioning Target in line with the
    // pattern of configurations pointing to the Pluggable Model) to the Password
    // Authenticator ID (the foreign key on the Password entity).

    $authenticator = $this->Authenticators->get(
      $provisioningTarget->kerberos_provisioner->authenticator_id,
      contain: ['PasswordAuthenticators']
    );

    // We always take an action here in order to ensure the KDC is in sync, even though
    // in many cases (ie: when called because some other data changed) we'll just be
    // confirming the current state.

    $action = 'unknown';
    $password = null;

    if($eligibility == ProvisioningEligibilityEnum::Eligible) {
      // Next try to find a Password entity that matches our configured Authenticotor.
      // We also need a Password of type PasswordEncodingEnum::Plain, since that's what
      // the Kerberos protocol requires.
      
      if(!empty($data->passwords)) {
        $password = Hash::extract($data->passwords, '{n}[type='.PasswordEncodingEnum::Plain.'][password_authenticator_id='.$authenticator->password_authenticator->id.']');
      }

      if(!empty($password)) {
        $action = 'update';
      } else {
        // If Pass Through Provisioning is enabled, we may have various scenarios where
        // we will get a blank password for an Eligible Person, including reprovisioning
        // or Authenticator lock/unlock. We'll need to look at the Authenticator Status
        // for more information, but we'll default to locking.

        $action = 'lock';

        // There should be at least one entry with an authenticator status

        if(!empty($data->passwords[0]->authenticator_status)
          && !$data->passwords[0]->authenticator_status->locked) {
          $action = 'unlock';
        }
      }
    } elseif($eligibility == ProvisioningEligibilityEnum::Ineligible) {
      // Check to see if the principal exists in the KDC, and if so lock it

      $action = 'lock';
    } elseif($eligibility == ProvisioningEligibilityEnum::Deleted) {
      // Check to see if the principal exists in the KDC, and if so lock it.
      // It's plausible we should remove it instead, but for now we'll start with
      // the "safer" operation.

      $action = 'lock';
      // $action = 'remove';
    }

    // Before we perform any action, retrieve the current state of the principal
    // (if any) from the KDC.

    $curprinc = null;

    try {
      $curprinc = $cxn->getPrincipal($principal);
    }
    catch(\Exception $e) {
      // This is most likely that the principal does not exist on the KDC.
    }

    if($curprinc) {
      // We have an existing principal, ensure it is in sync

      if($action == 'lock') {
        // We lock the principal by adding 64 to the attribute mask. This isn't
        // documented anywhere, but DISALLOW_ALL_TIX = 64, and is the setting that
        // will prevent authentication.

        $attributes = $curprinc->getAttributes();

        if(!($attributes & 64)) {
          // Add the locked bit
          $curprinc->setAttributes($curprinc->getAttributes() | 64);
          $curprinc->save();
        }
        // else the principal is already locked

        return [
          'status' => ProvisioningStatusEnum::Provisioned,
          'comment' => __d('kerberos_connector', 'result.locked-p', [$principal]),
          'identifier' => $principal
        ];
      } elseif($action == 'unlock') {
        // We only end up here if Pass Through Provisioning is enabled, in which case
        // we have an authenticator with no Password (but presumably a disabled password
        // in the KDC).

        $attributes = $curprinc->getAttributes();

        if($attributes & 64) {
          // Remove the locked bit
          $curprinc->setAttributes($curprinc->getAttributes() ^ 64);
          $curprinc->save();
        }
        // else the principal is already unlocked

        return [
          'status' => ProvisioningStatusEnum::Provisioned,
          'comment' => __d('kerberos_connector', 'result.unlocked-p', [$principal]),
          'identifier' => $principal
        ];
      } elseif($action == 'update') {
        // Make sure we aren't currently DISALLOWING_ALL_TIX -- if we are clear the flag.

        $attributes = $curprinc->getAttributes();

        if($attributes & 64) {
          // Remove the locked bit
          $curprinc->setAttributes($curprinc->getAttributes() ^ 64);
          $curprinc->save();
        }
        // else the principal is already unlocked

        // We submit a change password request even though the password might not have changed.
        // This will show up as a password change on the KDC, which may or may not be OK
        // depending on what policies the deploying site might have. Use Pass Through
        // Provisioning to avoid this, or maybe set a default policy of -history 1 (though
        // that will cause this call will fail, creating "Cannot reuse password" noise in
        // Provisioning History Records).
        $curprinc->changePassword($password[0]->password);

        return [
          'status' => ProvisioningStatusEnum::Provisioned,
          'comment' => __d('kerberos_connector', 'result.synced', [$principal]),
          'identifier' => $principal
        ];
      }
    } else {
      // No existing principal, the only operation we'll perform is 'update'

      if($action == 'update') {
        // From here, we'll just let errors bubble up

        $curprinc = new \KADM5Principal($principal);
        
        $cxn->createPrincipal(principal: $curprinc, password: $password[0]->password);

        return [
          'status' => ProvisioningStatusEnum::Provisioned,
          'comment' => __d('kerberos_connector', 'result.created', [$principal]),
          'identifier' => $principal
        ];
      }

      return [
        'status' => ProvisioningStatusEnum::NotProvisioned,
        'comment' => __d('kerberos_connector', 'result.notprov', [$principal])
      ];
    }
  }

  /**
   * Obtain status information for the requested provisioned subject.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  ProvisioningTarget $cfg      Provisioning Target configuration
   * @param  int                $groupId  Group ID to retrieve status for
   * @param  int                $personId Person ID to retrieve status for
   * @return array                        Array of status information: status, comment, timestamp
   */

  public function status(
    \App\Model\Entity\ProvisioningTarget $cfg,
    ?int $groupId,
    ?int $personId
  ): array {
    $ret = [
      'status'    => ProvisioningStatusEnum::NotProvisioned,
      'comment'   => __d('enumeration', 'ProvisioningStatusEnum.N'),
      'timestamp' => null
    ];

    // We only support provisioning People. We won't treat a $groupId as an error,
    // we can simply return (accurately) that the record is Not Provisioned.

    if($personId) {
      // Rather than reconstruct the Principal, we'll just pull it from the Identifiers table
      $Identifiers = TableRegistry::getTableLocator()->get('Identifiers');

      $typeId = $Identifiers->Types->getTypeId(
        coId: $cfg->co_id,
        attribute: 'Identifiers.type',
        // Although we now call these "Provisioning Keys", we reuse the database value from v4
        value: 'provisioningtarget'
      );

      $principal = $Identifiers->find()
                               ->where([
                                'person_id' => $personId,
                                'type_id' => $typeId,
                                'provisioning_target_id' => $cfg->id
                               ])
                               ->firstOrFail();

      // Establish a connection to kadmin

      $cxn = $this->Servers->KerberosServers->connect(
        serverId: $cfg->kerberos_provisioner->server_id,
        admin: true
      );

      // Look for the principal
      
      try {
        $princ = $cxn->getPrincipal($principal->identifier);

        // Construct status based on both the principal and password expiration times (if set)

        $expiry = __d('kerberos_connector', 'result.never');
        $pwexpiry = __d('kerberos_connector', 'result.never');
        $status = 'active';

        if($princ->getPasswordExpiryTime() > 0) {
          $pwExpiryTime = FrozenTime::createFromTimestamp($princ->getPasswordExpiryTime());
          $pwexpiry = $pwExpiryTime->nice();

          if($pwExpiryTime->isPast()) {
            $status = 'pwexpired';
          }
        }

        if($princ->getExpiryTime() > 0) {
          $expiryTime = FrozenTime::createFromTimestamp($princ->getExpiryTime());
          $expiry = $expiryTime->nice();

          if($expiryTime->isPast()) {
            $status = 'expired';
          }
        }

        // Test for locked status last to populate the correct comment
        $attributes = $princ->getAttributes();

        if($attributes & 64) {
          $status = 'locked';
        }

        $ret['status'] = ProvisioningStatusEnum::Provisioned;
        $ret['comment'] = __d('kerberos_connector', 'result.'.$status, [$expiry, $pwexpiry]);
        $ret['timestamp'] = FrozenTime::createFromTimestamp($princ->getLastModificationDate());
      }
      catch(\Exception $e) {
        // We'll get an Exception on principal not found. We should only get here
        // on edge case error conditions, eg a Provisioning Key exists in the Identifiers
        // table but we didn't successfully provision (or an admin deleted the entry
        // from the KDC).

        $ret['comment'] = __d('kerberos_connector', 'result.notprov', [$principal->identifier]);
      }
    }

    return $ret;
  }

  /**
   * Set validation rules.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  Validator $validator Validator
   * @return Validator            Validator
   * @throws InvalidArgumentException
   * @throws RecordNotFoundException
   */
  
  public function validationDefault(Validator $validator): Validator {
    $schema = $this->getSchema();
    
    $validator->add('provisioning_target_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('provisioning_target_id');
    
    $validator->add('server_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('server_id');

    $validator->add('type_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('type_id');

    $validator->add('authenticator_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('authenticator_id');
    
    return $validator; 
  }
}