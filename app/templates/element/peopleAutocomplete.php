<?php
  /**
   * COmanage Registry People Autocomplete Vue.js component field for forms
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

  // autocomplete person picker type, defaults to 'stand-alone':
  // - 'stand-alone', used when we want to open a modal that will use the person
  // - 'search', used when we find a person and display the fullname along with the ID
  // - 'field', used for model records. It has a postfix with a link to the person canvas
  $type = $type ?? 'stand-alone';

  // For a frozen field, print the referenced person (if one exists) and link to the person canvas.
  if($type == 'field' && isset($vv_obj) && $vv_obj['frozen']) {
    $personId = $vv_obj[$vv_field_arguments['fieldName']];
    if(!empty($personId)) {
      $personRecord = $this->Petition->getRecordForId(
        'person_id', $personId, ['PrimaryName', 'EmailAddresses']
      );
      print $this->Html->link(
        $personRecord['primary_name']['full_name'], 
        ['controller' => 'people', 'action' => 'edit', $personId]
      );
    }
  // Otherwise, build the people picker.  
  } else {
    // In the context of a type=field we will pass vv_field_arguments
    // In the context of a stand-alone field we will have vv_autocomplete_arguments
    $vv_field_arguments = $vv_field_arguments ?? $vv_autocomplete_arguments ?? [];
    $label = $label ?? $vv_field_arguments["fieldLabel"] ?? __d('operation', 'autocomplete.people.label');
    $fieldName = $fieldName ?? 'person_id';
    // Used by the SearchFilter Configuration
    $personType = $personType ?? 'person';
    $htmlId = $htmlId ?? 'person-id-picker';

    // DOM ID and Form Name separation:
    // - Display Label: Assigned for="field-3" (matching $htmlId).
    // - Interactive AutoComplete Input: Assigned id="field-3" (via $htmlId). Clicking the visible label natively
    //   focuses the interactive AutoComplete input, satisfying browser accessibility standards with zero ambiguity.
    // - Hidden Submission Input: Assigned id="field-3-target" (via $targetId).
    // Result: Every ID in the DOM is globally unique.
    $targetId = $targetId ?? ($htmlId === $fieldName ? $fieldName . '-target' : $fieldName);
    $isRequired = $isRequired ?? $vv_field_arguments["fieldOptions"]["required"] ?? $vv_field_arguments["required"] ?? false;
    // Does it have a value already. Default or stored
    // CAKEPHP automatically generates a select element if the value is an integer. This is not helpful here.
    $inputValue = $inputValue ?? $vv_field_arguments["fieldOptions"]["default"] ?? $vv_field_arguments["fieldOptions"]["value"] ?? '';

    // Mainly required for the Group Members people picker since this is placed as an action url
    $actionUrl = $actionUrl ?? []; // the url of the page to launch on select for a stand-alone picker
    $containerClasses = $containerClasses ?? 'cm-autocomplete-container';
    $viewConfigParameters = $viewConfigParameters ?? [];

    // By default, the Person Picker selects People, meaning the target entity is the CO itself.
    // However, some Pickers have a different context, eg a Group or a Petition, and those are
    // the target entity (which the Picker will use to determine the current CO).
    $targetKey = $targetKey ?? 'co_id';
    $targetValue = $targetValue ?? ($targetKey == 'co_id' ? $vv_cur_co->id : 0);

    // Load my helper functions
    $vueHelper = $this->loadHelper('Vue');

    // If we have the $actionUrl array, construct the URL
    $constructedActionUrl = '';
    if(!empty($actionUrl)) {
      $constructedActionUrl = $this->Url->build($actionUrl);
    }

    // This is the peopleAutocomplete element. If we have the id we need to self construct the
    // - the person canvas link
    // - Get the person record for view or edit
    if(!empty($inputValue)) {
      $personRecord = $this->Petition->getRecordForId('person_id', $inputValue, ['PrimaryName', 'EmailAddresses']);
      $canvasUrl = $this->Url->build(['controller' => 'people', 'action' => 'edit', $inputValue]);
    }

    $searchPeople = $this->request->getAttribute('webroot') . 'api/ajax/v2/people/pick?' . $targetKey . '=' . $targetValue;

    // Form Submission name Attributes:
    // - Hidden Submission Input: Rendered as <input type="hidden" name="field-3" id="field-3-target" value="...">
    //   This is the actual field that will be submitted on form POST.
    // - Interactive AutoComplete Input: Rendered without a native form name="field-3" attribute (it uses internal
    //   Vue props and dataName), preventing duplicate/conflicting payload submissions.
    print $this->Form->hidden($fieldName, [
        'id' => $targetId,
        'value' => '',
      ]
    );
  }
?>

<?php if(empty($vv_obj['frozen'])): // skip for frozen fields ?>
  
  <script type="module" nonce="<?= $vv_js_nonce ?>">
    <?php if(Cake\Core\Configure::read('debug')): ?>
    import AutocompletePeople
      from "<?= $this->Url->script('comanage/components/autocomplete/cm-autocomplete-people.js') ?>?time=<?= time() ?>";
    <?php else: ?>
    import AutocompletePeople
      from "<?= $this->Url->script('comanage/components/autocomplete/cm-autocomplete-people.js') ?>";
    <?php endif; ?>

    // XXX Probably move this to comanage.js
    const provided = {
      txt: JSON.parse('<?= json_encode($vueHelper->locales()) ?>'),
      app: {
        coId: <?= $vv_cur_co->id ?>,
        types: <?= json_encode($types) ?>,
        cosettings: <?= json_encode($cosettings) ?>
      },
      api: {
        viewConfigParameters: <?= json_encode($viewConfigParameters) ?>,
        webroot: '<?= $this->request->getAttribute('webroot') ?>',
        searchPeople: `<?= $searchPeople ?>`
      }
    };

    const app = Vue.createApp({
      data() {
        return {
          autocompleteOptions: {
            label: '<?= $label ?>',
            fieldName: '<?= $fieldName ?>', // This property holds the form input name
            targetId: '<?= $targetId ?>', // This property holds the hidden input id
            type: '<?= $type ?>',
            personType: '<?= $personType ?>',
            // XXX probably should be set by config, note the backend (ApiV2Controller::pick)
            // has a hard coded minimum of 2
            minLength: 3,
            htmlId: '<?= $htmlId ?>', // This property holds the vuejs input id
            actionUrl: '<?= $constructedActionUrl ?>',
            inputValue: '<?= $inputValue ?>',
            inputProps: {
              // We need to skip the name since we do not want to submit the vue input field
              //name: '<?php //= $htmlId ?>//',
              dataName: '<?= $htmlId ?>',
              // This is not translated to data-personid but to datapersonid.
              dataPersonid: '<?= $inputValue ?>',
              <?php if(!empty($isRequired)): ?>
              required: true,
              <?php endif; ?>
              <?php if($type === 'field'): ?>
              'aria-describedby': '<?= $htmlId ?>-desc',
              <?php endif; ?>
            },
            personRecord: <?= json_encode($personRecord ?? [])?>,
            canvasUrl: '<?= $canvasUrl ?? '' ?>'
          },
          error: '',
          core: {
            webroot: '<?= $this->request->getAttribute('webroot') ?>'
          }
        };
      },
      components: {
        AutocompletePeople
      },
      inject: ['txt', 'api', 'app'],
      methods: {
        setError(txt) {
          this.error = txt;
        },
        handleNetworkError() {
          this.successTxt = '';
          this.setError('Network Error');
          console.log('Status Code: 400');
        },
        handleError(res) {
          this.successTxt = '';
          this.setError(res?.message ?? this.txt.error500);
          console.error(res);
          console.log('Status Code: ', res.status);
        }
      },
      template: `
        <autocomplete-people
          :options="this.autocompleteOptions"
          :core="this.core"
          appendTo="self"
          :txt="this.txt">
        </autocomplete-people>
    `
    });


    app.use(primevue.config.default, {unstyled: true});
    // For core configurations and texts globally
    for (const [key, value] of Object.entries(provided)) {
      app.provide(key, value);
    }

    // Mount the component and provide a global reference for this app instance.
    window.<?= str_replace('-', '', $htmlId) ?> = app.mount("#<?= $htmlId ?>-container");
  </script>

  <div id="<?= $htmlId ?>-container" class="<?= $containerClasses ?>"></div>
  
<?php endif; ?>
