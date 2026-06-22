<?php
/**
 * COmanage Registry Password Authenticator Trait
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
 * @since         COmanage Registry v5.3.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace PasswordAuthenticator\Lib\Traits;

use PasswordAuthenticator\Lib\Enum\PasswordEncodingEnum;
use PasswordAuthenticator\Lib\Enum\PasswordSourceEnum;

trait PasswordAuthenticatorTrait {
  /**
   * Encode the provided password in accordance with the requested algorithm.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  string $password Password to encode
   * @param  string $encoding PasswordEncodingEnum
   * @return string           Encoded string
   */

  protected function encode(string $password, string $encoding): string {
    switch($encoding) {
      case PasswordEncodingEnum::Crypt:
        // We use password_hash, which due to various portability issues with crypt
        // is really only useful with password_verify.

        return password_hash($password, PASSWORD_DEFAULT);
        break;
      case PasswordEncodingEnum::SSHA:
        // Salted SHA1 isn't really a great algorithm (and our salt generation
        // could probably be better), but OpenLDAP doesn't support a better option
        // out of the box.

        $salt = substr(bin2hex(random_bytes(8)),0,4);
        return base64_encode(sha1($password.$salt, true) . $salt);
        break;
      default:
        throw new \InvalidArgumentException("Unknown Password Encoding " . $encoding);
        break;
    }
  }

  /**
   * Validate a password change request according to the configuration.
   * 
   * @since  COmanage Registry v5.3.0
   * @param  Authenticator  $cfg      Authenticator configuration
   * @param  array          $data     Array of data from fields.inc
   * @throws InvalidArgumentException
   */

  protected function validateRequest(
    \App\Model\Entity\Authenticator $cfg,
    array $data
  ) {
    // Perform sanity checks on Self Selected passwords only
    if($cfg->password_authenticator->source_mode == PasswordSourceEnum::SelfSelect) {
      $minlen = $cfg->password_authenticator->min_length ?: 8;
      $maxlen = $cfg->password_authenticator->max_length ?: 64;

      // Check minimum length
      if(strlen($data['password']) < $minlen) {
        throw new \InvalidArgumentException(__d('password_authenticator', 'error.Passwords.len.min', [$minlen]));
      }

      // Check maximum length
      if(strlen($data['password']) > $maxlen) {
        throw new \InvalidArgumentException(__d('password_authenticator', 'error.Passwords.len.max', [$minlen]));
      }

      // Check that passwords match
      if($data['password'] != $data['password2']) {
        throw new \InvalidArgumentException(__d('password_authenticator', 'error.Passwords.match'));
      }
    }

    // If Password Reuse Prevention is enabled (and we're not using hard delete or PTP)
    // check the Password against the previous ones
    if($cfg->password_authenticator->prevent_reuse
       && $cfg->password_authenticator->format_crypt_php
       && !$cfg->password_authenticator->use_hard_delete
       && !$cfg->enable_ptp
       // During Enrollment, we may not have a person_id yet. If not, that's ok since
       // by definition we're collecting a new Password, so reuse shouldn't be possible.
       && !empty($data['person_id'])) {
      $passwords = $this->find('all', archived: true)
                        ->where([
                          'password_authenticator_id' => $cfg->password_authenticator->id,
                          'person_id' => $data['person_id'],
                          'type' => PasswordEncodingEnum::Crypt
                        ])
                        ->all();

      foreach($passwords as $p) {
        if(password_verify($data['password'], $p->password)) {
          // The passwords match, throw a validation error
          throw new \InvalidArgumentException(__d('password_authenticator', 'error.Passwords.reuse'));
        }
      }
    }
  }
}
