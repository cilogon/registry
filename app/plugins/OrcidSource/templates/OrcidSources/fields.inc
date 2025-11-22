<?php
/**
 * COmanage Registry Orcid Sources Fields
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

use OrcidSource\Lib\Enum\OrcidSourceScopeEnum;

$vv_inherited_scopes = OrcidSourceScopeEnum::DEFAULT_SCOPE;
if (filter_var($vv_obj->scope_inherit, FILTER_VALIDATE_BOOLEAN)) {
  $vv_inherited_scopes = $vv_obj->server?->oauth2_server?->scope ?? OrcidSourceScopeEnum::DEFAULT_SCOPE;
}

$fields = [
  // Render the Redirect URI
  'redirect_uri' => [
    'readonly' => true,
    'default' => $vv_redirect_uri
  ],
  'server_id' => [
    'empty' => false,
    'required' => true
  ],
  'api_type' => [
    'options' => $api_types,
    'type' => 'select',
    'empty' => false,
    'required' => true
  ],
  'api_tier' => [
    'options' => $api_tiers,
    'type' => 'select',
    'empty' => false,
    'required' => true
  ],
  'scope_inherit',
  // Render active scopes
  'Scope' => [
    'readonly' => true,
  ],
  'SUBTITLE' => [
    'subtitle' =>  __d('orcid_source', 'information.OrcidSources.default.types')
  ],
  'address_type_id',
  'default_affiliation_type_id',
  'email_address_type_id',
  'name_type_id'
];
