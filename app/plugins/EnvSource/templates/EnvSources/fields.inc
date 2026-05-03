<?php
/**
 * COmanage Registry Env Sources Fields
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
 * @since         COmanage Registry v5.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

use EnvSource\Lib\Enum\EnvSourceSpModeEnum;

// Build the delimiter map dynamically from your $spModes array
$phpDelimiterMap = [];
$selectedValue = '';
foreach ($spModes as $key => $label) {
  if ($key === EnvSourceSpModeEnum::Shibboleth) {
    $phpDelimiterMap[$key] = ';';
    if ($vv_obj?->mva_delimiter == ';') {
      $selectedValue = EnvSourceSpModeEnum::Shibboleth;
    }
  } elseif ($key === EnvSourceSpModeEnum::SimpleSamlPhp) {
    $phpDelimiterMap[$key] = ',';
    if ($vv_obj?->mva_delimiter == ',') {
      $selectedValue = EnvSourceSpModeEnum::SimpleSamlPhp;
    }
  } elseif ($key === EnvSourceSpModeEnum::Other) {
    $phpDelimiterMap[$key] = '';
    if (!in_array($vv_obj?->mva_delimiter, ['', ',', ';'])) {
      $phpDelimiterMap[$key] = $vv_obj?->mva_delimiter;
      $selectedValue = EnvSourceSpModeEnum::Other;
    }
  }
}

// Encode the array to JSON before inserting it into the Javascript block
$jsonDelimiterMap = json_encode($phpDelimiterMap);


$afterField = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    var select = document.getElementById('mva_delimiter_default');
    var textbox = document.getElementById('mva_delimiter');

    // Map the dropdown values to actual delimiter characters
    var delimiterMap = $jsonDelimiterMap;

    select.addEventListener('change', function() {
        var selectedValue = select.value;

        // Update the textbox with the corresponding delimiter
        if (delimiterMap[selectedValue] !== undefined) {
            textbox.value = delimiterMap[selectedValue];
        } else {
            // Fallback in case of unexpected values
            textbox.value = selectedValue;
        }

        // Focus the textbox if "Other" is selected so we can immediately type
        if (selectedValue === 'O') {
            textbox.focus();
        }
    });

    if (!textbox.value && select.value) {
        textbox.value = delimiterMap[select.value] || '';
    }
});
</script>
JS;


$fields = [
  // 'duplicate_mode',
  'mva_delimiter' => [
    'fieldLabel' => __d('env_source','field.EnvSources.mva_delimiter'),
    'groupedControls' =>  [
      'mva_delimiter' => [
        'type' => 'text',
        'id' => 'mva_delimiter',
        'label' => false,
        'required' => true,
        'class' => 'form-control mb-1',
        'singleRowItem' => true,
      ],
      'mva_delimiter_default' => [
        'id' => 'mva_delimiter_default',
        'label' => [
          'text' => __d('env_source','field.EnvSources.mva_delimiter_common'),
          'class' => 'mb-2'
        ],
        'name' => 'mva_delimiter_default',
        'required' => false,
        'empty' => false,
        'type' => 'select',
        'value' => $selectedValue,
        'options' => $spModes,
      ],
    ],
    'afterField' => $afterField
  ],
  'sync_on_login',
  'address_type_id',
  'default_affiliation_type_id',
  'email_address_type_id',
  'name_type_id',
  'telephone_number_type_id',
  'redirect_on_duplicate',
  'lookaside_file',
  'SUBTITLE' => [
    'subtitle' => __d('env_source', 'information.header.map')
  ]
];

$defaultNames = [
  'env_identifier_sourcekey' => 'ENV_SOURCE_KEY',
  'env_address_street' => 'ENV_STREET',
  'env_address_locality' => 'ENV_LOCALITY',
  'env_address_state' => 'ENV_STATE',
  'env_address_postalcode' => 'ENV_POSTALCODE',
  'env_address_country' => 'ENV_COUNTRY',
  'env_affiliation' => 'ENV_AFFILIATION',
  'env_department' => 'ENV_DEPARTMENT',
  'env_identifier_eppn' => 'ENV_EPPN',
  'env_identifier_eptid' => 'ENV_EPTID',
  'env_identifier_epuid' => 'ENV_EPUID',
  'env_identifier_network' => 'ENV_NETWORK',
  'env_identifier_oidcsub' => 'ENV_OIDCSUB',
  'env_identifier_samlpairwiseid' => 'ENV_SAMLPAIRWISE',
  'env_identifier_samlsubjectid' => 'ENV_SAMLSUBJECT',
  'env_mail' => 'ENV_MAIL',
  'env_name_honorific' => 'ENV_HONORIFIC',
  'env_name_given' => 'ENV_GIVEN',
  'env_name_middle' => 'ENV_MIDDLE',
  'env_name_family' => 'ENV_FAMILY',
  'env_name_suffix' => 'ENV_SUFFIX',
  'env_organization' => 'ENV_ORGANIZATION',
  'env_telephone_number' => 'ENV_TELEPHONE',
  'env_title' => 'ENV_TITLE'
];

foreach($defaultNames as $field => $envName) {
  $fields[$field] = [
    'default' => $envName
  ];
}

$subnav = [
  'tabs' => ['ExternalIdentitySources', 'EnvSource.EnvSources', 'ExternalIdentitySources@action.search'],
  'action' => [
    'ExternalIdentitySources' => ['edit', 'view', 'search'],
    'EnvSource.EnvSources' => ['edit'],
    'ExternalIdentitySources@action.search' => [],
  ],
];
