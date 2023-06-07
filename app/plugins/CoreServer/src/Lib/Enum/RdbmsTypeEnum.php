<?php
/**
 * COmanage Registry RDBMS Type Enum
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
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

declare(strict_types = 1);

namespace CoreServer\Lib\Enum;

use App\Lib\Enum\StandardEnum;

// We call this "RdbmsType" rather than "SqlServerTyoe" (the name in v4)
// to avoid conflation with MS Sql Server.
class RdbmsTypeEnum extends StandardEnum {
  // This currently aligns with Cake supported servers
  const MariaDB   = 'MA';
  const MySQL     = 'MY';
// XXX Oracle requires a community plugin: https://github.com/CakeDC/cakephp-oracle-driver
// We could maybe ship with this?
  // const Oracle    = 'OR';
  const Postgres  = 'PG';
  const SQLite    = 'LT';
  const SqlServer = 'MS';
}
