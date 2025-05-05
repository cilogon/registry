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

// This view does not currently support read-only
if($vv_action !== 'edit') {
    return;
}

// Render the Redirect URI
print $this->element('form/listItem', [
    'arguments' => [
        'fieldName' => 'redirect_uri',
        'fieldOptions' => [
            'readOnly' => true,
            'default' => $vv_redirect_uri
        ]
    ]]);

print $this->element('form/listItem', [
    'arguments' => [
        'fieldName' => 'server_id',
        'fieldOptions' => [
            'empty' => false,
            'required' => true,
        ]
    ]]);

print $this->element('form/listItem', [
    'arguments' => [
        'fieldName' => 'api_type',
        'fieldSelectOptions' => $api_types,
        'fieldType' => 'select',
        'fieldOptions' => [
            'empty' => false,
            'required' => true,
        ]
    ]]);

print $this->element('form/listItem', [
    'arguments' => [
        'fieldName' => 'api_tier',
        'fieldSelectOptions' => $api_tiers,
        'fieldType' => 'select',
        'fieldOptions' => [
            'empty' => false,
            'required' => true,
        ]
    ]]);


print $this->element('form/listItem', [
    'arguments' => [
        'fieldName' => 'scope_inherit',
    ]]);

$vv_inherited_scopes = OrcidSourceScopeEnum::DEFAULT_SCOPE;
if (filter_var($vv_obj->scope_inherit, FILTER_VALIDATE_BOOLEAN)) {
    $vv_inherited_scopes = $vv_obj->server?->oauth2_server?->scope ?? OrcidSourceScopeEnum::DEFAULT_SCOPE;
}

// Render active scopes
print $this->element('form/listItem', [
    'arguments' => [
        'fieldName' => 'Scope',
        'fieldOptions' => [
            'readOnly' => true,
            'default' => $vv_inherited_scopes
        ]
    ]]);

// Print a title
$title = __d('orcid_source', 'information.OrcidSources.default.types');
print $this->element('form/h3Inject', compact('title'));

foreach([
            'address_type_id',
            'default_affiliation_type_id',
            'email_address_type_id',
            'name_type_id',
        ] as $field) {
    print $this->element('form/listItem', [
        'arguments' => [
            'fieldName' => $field
        ]
    ]);
}