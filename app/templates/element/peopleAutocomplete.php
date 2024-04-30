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

  // Get parameters
  $type = $type ?? 'stand-alone'; // autocomplete person picker type: 'stand-alone' or 'field', defaults to 'stand-alone'.
  $label = $label ?? __d('operation','autocomplete.people.label');
  $fieldName = $fieldName ?? 'person_id';
  $personType = $personType ?? 'coperson';
  $htmlId = $htmlId ?? 'cmPersonPickerId';
  $actionUrl = $actionUrl ?? []; // the url of the page to launch on select for a stand-alone picker
  $viewConfigParameters = $viewConfigParameters ?? [];

  // Get the CSRF Token in JavaScript  
  $token = $this->request->getAttribute('csrfToken');
  // Load my helper functions
  $vueHelper = $this->loadHelper('Vue');
  
  // If we have the $actionUrl array, construct the URL
  $constructedActionUrl = '';
  if(!empty($actionUrl)) {
    $constructedActionUrl = $this->Url->build($actionUrl);
  }
    
  // Create a people autocomplete text input.
?>

<script type="module">
  <?php if(Cake\Core\Configure::read('debug')): ?>
    import AutocompletePeople from "<?= $this->Url->script('comanage/components/autocomplete/cm-autocomplete-people.js') ?>?time=<?= time() ?>";
  <?php else: ?>
    import AutocompletePeople from "<?= $this->Url->script('comanage/components/autocomplete/cm-autocomplete-people.js') ?>";
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
      searchPeople: `<?= $this->request->getAttribute('webroot') ?>api/ajax/v2/people?co_id=<?= $vv_cur_co->id ?>&picker=on&for=<?= $viewConfigParameters['for'] ?>`
    }
  }

  const app = Vue.createApp({
    data() {
      return {
        autocompleteOptions: {
          label: '<?= $label ?>',
          fieldName: '<?= $fieldName ?>',
          type: '<?= $type ?>',
          personType: '<?= $personType ?>',
          minLength: 2, // XXX probably should be set by config and default to 3
          htmlId: '<?= $htmlId ?>',
          actionUrl: '<?= $constructedActionUrl ?>'
        },
        error: ''
      }
    },
    components: {
      AutocompletePeople
    },
    inject: ['txt', 'api', 'app'],
    methods: {
      setError(txt) {
        this.error = txt;
      },
      handleNetworkError () {
        this.successTxt = '';
        this.setError('Network Error')
        console.log('Status Code: 400')
      },
      handleError (res) {
        this.successTxt = '';
        this.setError(res?.message ?? this.txt.error500)
        console.error(res);
        console.log('Status Code: ', res.status)
      }
    },
    template: `
        <autocomplete-people
          :options="this.autocompleteOptions"
          :core="this.core"
          appendTo='self'
          :txt="this.txt">
        </autocomplete-people>
    `
  });


  app.use(primevue.config.default, {unstyled: true});
  // For core configurations and texts globally
  for (const [key, value] of Object.entries(provided)) {
    app.provide( key, value);
  }

  // Mount the component and provide a global reference for this app instance.
  window.<?= $htmlId ?> = app.mount("#<?= $htmlId ?>-container");
</script>

<div id="<?= $htmlId ?>-container" class="cm-autocomplete-container"></div>
