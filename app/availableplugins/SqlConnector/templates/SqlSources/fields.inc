<?php
/**
 * COmanage Registry SQL Sources Fields
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
?>
<script type="text/javascript">
  // JS specific to these fields

  function updateGadgets(isPageLoad) {
    // Hide or show gadgets according to current state
    var tmode = document.getElementById('table-mode').value;

    const flatTypes = [
      'address-type-id',
      'email-address-type-id',
      'identifier-type-id',
      'name-type-id',
      'pronoun-type-id',
      'telephone-number-type-id',
      'url-type-id'
    ];

    if(tmode != '<?= \SqlConnector\Lib\Enum\SqlSourceTableModeEnum::Flat ?>') {
      hideFields(flatTypes, isPageLoad);
    } else {
      showFields(flatTypes, isPageLoad);
    }
  }

  function jsLocalOnLoad() {
    updateGadgets(true);
  }
</script>
<?php
// This view does not currently support read-only
if($vv_action == 'edit') {
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'server_id'
    ]
  ]);
  
  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'table_mode',
      'fieldOptions' => [
        'onChange' => 'updateGadgets()'
      ],
      'fieldType' => 'select'
    ]
  ]);
  
  foreach([
            'source_table',
            'address_type_id',
            'email_address_type_id',
            'identifier_type_id',
            'name_type_id',
            'pronouns_type_id',
            'telephone_number_type_id',
            'url_type_id'
          ] as $field) {
    print $this->element('form/listItem', [
      'arguments' => [
        'fieldName' => $field,
        'fieldOptions' => [
          'required' => false
        ]
      ]
    ]);
  }

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'threshold_check'
    ]
  ]);

  print $this->element('form/listItem', [
    'arguments' => [
      'fieldName' => 'threshold_override'
    ]
  ]);
}
