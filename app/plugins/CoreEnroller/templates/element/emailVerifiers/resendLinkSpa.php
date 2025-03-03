<?php
/**
 * COmanage Registry Resend Confirmation Link Vue.js component
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


declare(strict_types = 1);

/*
 * Required:
 * - $htmlId
 * - $containerClasses
 * - $vv_config
 * - $m (encoded email)
 * - $petitionId
 * */

// Load my helper functions
$vueHelper = $this->loadHelper('Vue');

$relativeUrl = "core-enroller/email-verifiers/resend/$vv_config->id"
  . '?'
  . "petition_id=$petitionId"
  . "&m=$emailAddress"

?>

<script type="module">
  import MiniLoader from "<?= $this->Url->script('comanage/components/common/mini-loader.js')?>?time=<?= time() ?>";
  
  const app = Vue.createApp({
    data() {
      return {
        error: '',
        message: '', // Message to show (success or error)
        loading: false, // Loading state for the fetch request
        controller: null, // AbortController instance,
        txt: JSON.parse('<?= json_encode($vueHelper->locales()) ?>'),
        app: {
          coId: <?= $vv_cur_co->id ?>,
          types: <?= json_encode($types) ?>,
          cosettings: <?= json_encode($cosettings) ?>
        },
        api: {
          webroot: '<?= $this->request->getAttribute('webroot') ?>',
          // co_id query parameter is required since it is the People's primary link
          resendCode: `<?= $this->request->getAttribute('webroot') . $relativeUrl ?>`
        },
        txtPlugin: {
          'resendPreText': "<?= __d('core_enroller','information.EmailVerifiers.resend-pre-text') ?>",
          'resend': '<?= __d('core_enroller','information.EmailVerifiers.resend') ?>',
          'abort': '<?= __d('core_enroller','information.EmailVerifiers.abort') ?>',
          'sending': '<?= __d('core_enroller','information.EmailVerifiers.sending') ?>',
          'success': '<?= __d('core_enroller','information.EmailVerifiers.success') ?>',
        }
      }
    },
    components: {
      MiniLoader
    },
    methods: {
      async fetchData() {
        this.message = ""; // Clear any previous messages/errors
        this.error = ''; 
        this.loading = true; // Set loading state to true

        // Create a new AbortController instance
        this.controller = new AbortController();

        const apiUrl = this.api.resendCode; // Replace this URL with your API endpoint

        let request_init = {
          headers: new Headers({
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          }),
          method: 'GET',
          signal: this.controller.signal,
        }
        // AJAX Request
        let requestObj = new Request(apiUrl, request_init);
        
        try {
          // Perform the fetch request with signal
          const response = await fetch(requestObj);

          // Check if the response is successful
          if (!response.ok) {
            throw new Error(`Error: ${response.status} - ${response.statusText}`);
          }
          
          const data = await response.json();
          this.message = this.txtPlugin.success;
        } catch (error) {
          // Handle fetch errors
          if (error.name === "AbortError") {
            // If fetch was aborted
            this.error = "Request aborted.";
          } else {
            this.error = `Request Failed: ${error.message}`;
          }
        } finally {
          this.loading = false; // Reset the loading state
        }
      },
      abortRequest() {
        // Abort the ongoing fetch request, if any
        if (this.controller) {
          this.controller.abort();
          this.controller = null;
        }
      },
    },
    computed: {
      getMiniLoaderClasses: function() {
        return "co-loading-mini-container d-inline ms-1"
      },
      getAlertClasses: function() {
        if (this.error) {
          return "alert alert-danger alert-dismissible co-alert"
        } else if (this.message) {
          return "alert alert-success alert-dismissible co-alert"
        } else {
          return "alert alert-info alert-dismissible co-alert"
        }
      }
    },
    template: `
        <!-- Display Success or Error Message -->
        <div v-if="message || error"  class="alert-container mb-3" id="flash-messages">
            <div :class="getAlertClasses" role="alert">
                <div class="alert-body d-flex align-items-center">
                    <span class="alert-title d-flex align-items-center">
                        <span v-if="error" class="material-symbols-outlined alert-icon">report_problem</span>
                        <span v-if="message" class="material-symbols-outlined alert-icon">check_circle</span>
                    </span>
                    <span v-if="error" class="alert-message">{{ error }}</span>
                    <span  v-if="message" class="alert-message">{{ message }}</span>
                    <span class="alert-button">
                        <button type="button" class="btn-close nospin" data-bs-dismiss="alert" aria-label="Close"></button>
                    </span>
                </div>
            </div>
        </div>
        <div v-if="!loading">
            <span class="me-1">{{ txtPlugin.resendPreText }}</span>
            <a href="#" class="spin" @click="fetchData">{{ txtPlugin.resend }}</a>
        </div>
        <div v-if="loading">
            <span class="me-1">{{ txtPlugin.sending }}</span>
            <MiniLoader :isLoading="loading" :classes="getMiniLoaderClasses"/>
        </div>
    `
  });


  app.use(primevue.config.default, {unstyled: true});

  // Mount the component and provide a global reference for this app instance.
  window.<?= str_replace('-', '', $htmlId) ?> = app.mount("#<?= $htmlId ?>-container");
</script>

<div id="<?= $htmlId ?>-container"  class="<?= $containerClasses ?>"></div>
