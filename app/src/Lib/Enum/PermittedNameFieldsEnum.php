<?php
/**
 * COmanage Registry Permitted Name Fields Enum
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

namespace App\Lib\Enum;

class PermittedNameFieldsEnum extends StandardEnum {
//  const Given       = "given";  Not currently allowed due to potential conflict with RequiredNameFieldsEnum
  const GF    = "given,family";
  const GMF   = "given,middle,family";
  const GFS   = "given,family,suffix";
  const GMFS  = "given,middle,family,suffix";
  const HGF   = "honorific,given,family";
  const HGMF  = "honorific,given,middle,family";
  const HGFS  = "honorific,given,family,suffix";
  const HGMFS = "honorific,given,middle,family,suffix";
}