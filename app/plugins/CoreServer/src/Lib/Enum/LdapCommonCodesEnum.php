<?php
/**
 * COmanage Registry LDAP Common Codes Enum
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

namespace CoreServer\Lib\Enum;

use App\Lib\Enum\StandardEnum;

class LdapCommonCodesEnum extends StandardEnum
{
  /**
   * The requested LDAP entry (DN) does not exist in the directory.
   */
  const int LDAP_NO_SUCH_OBJECT = 0x20; // 32

  /**
   * The entry violates schema definitions (e.g. missing required attributes or disallowed attributes for the object class).
   */
  const int LDAP_OBJECT_CLASS_VIOLATION = 0x41; // 65

  /**
   * The add or rename operation failed because an entry with the target DN already exists.
   */
  const int LDAP_ENTRY_ALREADY_EXISTS = 0x44; // 68

  /**
   * The modification of the entry's objectClass attribute is prohibited by the directory server.
   */
  const int LDAP_OBJECT_CLASS_MODS_PROHIBITED = 0x45; // 69

  /**
   * Connection to the LDAP server could not be established (internal application code, not from ldap_errno()).
   */
  const int LDAP_CONNECT_ERROR = 0x5b; // 91 (internal)
}
