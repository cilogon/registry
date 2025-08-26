<?php
/**
 * COmanage Registry SSH Keys Table
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

namespace SshKeyAuthenticator\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Lib\Enum\AuthenticatorStatusEnum;
use App\Lib\Enum\ProvisioningContextEnum;
use SshKeyAuthenticator\Lib\Enum\SshKeyActionEnum;
use SshKeyAuthenticator\Lib\Enum\SshKeyTypeEnum;
use SshKeyAuthenticator\Model\Entity\SshKey;

class SshKeysTable extends Table {
  use \App\Lib\Traits\AuthenticatorTrait;
  use \App\Lib\Traits\AutoViewVarsTrait;
  use \App\Lib\Traits\CoLinkTrait;
  use \App\Lib\Traits\LabeledLogTrait;
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

    $this->setTableType(\App\Lib\Enum\TableTypeEnum::Secondary);

    // Define associations
    $this->belongsTo('SshKeyAuthenticator.SshKeyAuthenticators');
    $this->belongsTo('People');
    
    $this->setDisplayField('comment');

    $this->setPrimaryLink('SshKeyAuthenticator.ssh_key_authenticator_id');
    $this->setRequiresCO(true);
    $this->setRedirectGoal('index');

    $this->setPermissions([
      // Actions that operate over an entity (ie: require an $id)
      'entity' => [
        'delete' =>   ['platformAdmin', 'coAdmin'],
        // Following the v4 pattern, SSH Keys cannot be edited
        'edit' =>     false,
        'view' =>     ['platformAdmin', 'coAdmin']
      ],
      // Actions that are permitted on readonly entities (besides view)
      // SSH Entities are readOnly but permit delete, so we need to re-enable the action
      'readOnly' =>   ['delete'],
      // Actions that operate over a table (ie: do not require an $id)
      'table' => [
        'add' =>      ['platformAdmin', 'coAdmin'],
        'index' =>    ['platformAdmin', 'coAdmin']
      ]
    ]);
  }
  
  /**
   * Add an SSH Key from a string parsed from a file.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  int    $sshKeyAuthenticatorId  SSH Key Authenticator ID
   * @param  int    $personId               Person ID
   * @param  string $contents               Contents of SSH key file (not parsed)
   */

  public function addFromKeyFile(
    int $sshKeyAuthenticatorId,
    int $personId,
    string $contents
  ): SshKey {
    // GMR-2 will handle checking that $personId and $sshKeyAuthenticatorId are in the
    // same CO, so we don't have to.

    // Process the key file
    $keyFileString = rtrim($contents);

    if(empty($keyFileString) || ctype_space($keyFileString)) {
      throw new \InvalidArgumentException(__d('ssh_key_authenticator', 'error.SshKeys.empty'));
    }

    if(preg_match("/-----BEGIN.*PRIVATE.*/", $keyFileString) == 1) {
      // This is the private key, not the public key
      throw new \InvalidArgumentException(__d('ssh_key_authenticator', 'error.SshKeys.private'));
    }

    // We currently only support OpenSSH format, which is a triple of type/key/comment,
    // and RFC 4716 Secure Shell (SSH) Public Key File format.

    $newEntityData = [
      'ssh_key_authenticator_id'  => $sshKeyAuthenticatorId,
      'person_id'                 => $personId
    ];

    // Currently, we only support one key per file regardless of format.

    if(preg_match("/---- BEGIN SSH2 PUBLIC KEY ----.*/", $keyFileString) == 1) {
      // RFC4716 format
      $newEntityData = array_merge($newEntityData, $this->parseRfc4716($keyFileString));
    } else {
      // OpenSSH format

      $sshKeyLine = explode("\n", $keyFileString);
      $bits = explode(' ', $sshKeyLine[0], 3);

      $newEntityData['type'] = $bits[0];
      $newEntityData['skey'] = $bits[1];
      $newEntityData['comment'] = $bits[2];
    }

    if(empty($newEntityData['skey'])) {
      throw new \InvalidArgumentException(__d('ssh_key_authenticator', 'error.SshKeys.private'));
    }

    $sshkey = $this->newEntity($newEntityData);

    $this->saveOrFail($sshkey);

    // Record History and trigger provisioning

    $this->People->recordHistory(
      $sshkey,
      SshKeyActionEnum::SshKeyUploaded,
      __d('ssh_key_authenticator', 'result.uploaded', [$sshkey->comment])
    );

    $this->People->requestProvisioning($personId, ProvisioningContextEnum::Automatic);

    return $sshkey;
  }

  /**
   * Parse an RFC 4716 SSH Public Key File.
   * 
   * @since  COmanage Registry v5.2.0
   * @param  string $keyFileString  SSH Key File contents
   * @return array                  Array of 'type', 'skey', and 'comment'
   */
  
  public function parseRfc4716(string $keyFileString) {
    // RFC 4716 format is line based.
    $lines = explode("\n", $keyFileString);

    $firstLineFound = false;
    $keyFileHeaders = [];
    $lineContinuationInProgress = false;
    $base64EncodedBody = "";

    foreach ($lines as $line) {
      // A Conforming key file would begin immediately with the begin marker
      // but try to be liberal in what we accept so skip any initial lines.
      if(!$firstLineFound) {
        if(preg_match("/^---- BEGIN SSH2 PUBLIC KEY ----.*/", $line) === 1) {
          $firstLineFound = true;
        }
        continue;
      }

      // Parse key file headers with possible continuation lines
      // until the base64-encoded body begins.
      if(empty($base64EncodedBody)) {
        if(!$lineContinuationInProgress) {
          $headerLineParts = preg_split("/:/u", $line, 2);
          if(count($headerLineParts) == 1) {
            $base64EncodedBody .= $line;
          } elseif(count($headerLineParts) == 2) {
            $headerTag = $headerLineParts[0];
            if(substr($headerLineParts[1], -1) == '\\') {
              $lineContinuationInProgress = true;
              $headerValue = substr($headerLineParts[1], 0, -1);
            } else {
              $headerValue = $headerLineParts[1];
              $keyFileHeaders[$headerTag] = $headerValue;
            }
            continue;
          }
        } else {
          if(substr($line, -1) == '\\') {
            $headerValue = $headerValue . substr($line, 0, -1);
          } else {
            $headerValue = $headerValue . $line;
            $lineContinuationInProgress = false;
            $keyFileHeaders[$headerTag] = $headerValue;
          }
          continue;
        }
      } else {
        // Stop parsing when we find the end marker and so ignore any
        // non-conforming end material.
        if(preg_match("/^---- END SSH2 PUBLIC KEY ----.*/", $line) === 1) {
          break;
        }
        $base64EncodedBody .= $line;
        continue;
      }
    }

    // Base-64 decode the body. The resulting binary string has the format
    // 3 null bytes, key type string, 3 null bytes, public key. The key type
    // string needs to further be trimmed to remove non-ascii characters.
    $bodyBinaryString = base64_decode($base64EncodedBody);
    $keyTypeString = trim(explode("\x00\x00\x00", $bodyBinaryString)[1], "\x00..\x1F");

    // An empty comment is allowed.
    $comment = "";

    if(array_key_exists("Comment", $keyFileHeaders)) {
        $comment = trim($keyFileHeaders["Comment"]);
    } elseif (array_key_exists("Subject", $keyFileHeaders)) {
      $comment = trim($keyFileHeaders["Subject"]);
    }

    return [
      'type'    => $keyTypeString,
      'skey'    => $base64EncodedBody,
      'comment' => $comment
    ];
  }

  /**
   * Obtain the current Authenticator status for a Person.
   *
   * @since  COmanage Registry v5.2.0
   * @param  Authenticator  $cfg      Authenticator Configuration
   * @param  int            $personId Person ID
   * @return array                    Array with values
   *                                  status: AuthenticatorStatusEnum
   *                                  comment: Human readable string, visible to the CO Person
   */

  public function status(\App\Model\Entity\Authenticator $cfg, int $personId): array {
    // Are there any SSH Keys for this person?

    $count = $this->find()
                  ->where([
                    'ssh_key_authenticator_id' => $cfg->ssh_key_authenticator->id,
                    'person_id' => $personId
                  ])
                  ->count();

    if($count > 0) {
      return [
        'status'  => AuthenticatorStatusEnum::Active,
        'comment' => __d('ssh_key_authenticator', 'result.registered', [$count, __d('ssh_key_authenticator', 'controller.SshKeys', [$count])])
      ];
    }

    return [
      'status'  => AuthenticatorStatusEnum::NotSet,
      'comment' => __d('result', 'set.not')
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

    $validator->add('ssh_key_authenticator_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('ssh_keyauthenticator_id');

    $validator->add('person_id', [
      'content' => ['rule' => 'isInteger']
    ]);
    $validator->notEmptyString('person_id');

    $validator->add('skey', [
      'filter'  => ['rule'     => ['validateInput'],
                    'provider' => 'table']
    ]);
    $validator->notEmptyString('skey');

    $this->registerStringValidation($validator, $schema, 'comment', false);

    $validator->add('type', [
      'content' => ['rule' => ['inList', SshKeyTypeEnum::getConstValues()]]
    ]);
    $validator->notEmptyString('type');
    
    return $validator;
  }
}
