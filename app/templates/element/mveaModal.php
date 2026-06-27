<?php
/**
 * COmanage Registry MVEA Vue.js Modal Component
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

// Get the CSRF Token in JavaScript  
$token = $this->request->getAttribute('csrfToken');
// Load my helper functions
$vueHelper = $this->loadHelper('Vue');


// Create a modal for handling MVEA adds/edits via iframe. XXX We may rename to a generic modal at some point.
?>

<script type="module" nonce="<?= $vv_js_nonce ?>">
  <?php if(Cake\Core\Configure::read('debug')): ?>
    import Modal from "<?= $this->Url->script('comanage/components/common/modal.js') ?>?time=<?= time() ?>";
  <?php else: ?>
    import Modal from "<?= $this->Url->script('comanage/components/common/modal.js') ?>";
  <?php endif; ?>
  
  const mveaModalApp = Vue.createApp({
    data() {
      return {
        componentRef: null,
        mveaModal: null,
        modalElement: null,
        core: {
          webroot: '<?= $this->request->getAttribute('webroot') ?>'
        },
        isLoading: true,
        modal: {
          title: '<?= __d('information','global.attribute.modal') ?>',
          url: ''
        },
        txt: JSON.parse('<?= json_encode($vueHelper->locales()) ?>')
      }
    },
    components: {
      Modal
    },
    provide() {
      return {
        txt: this.txt
      }
    },
    methods: {
      launch(title, url, componentRef) {
        this.modal.title = title;
        this.modal.url = url;
        this.componentRef = componentRef;
        this.mveaModal.show(); // this is a call to Bootstrap dialog set up in mounted()
      },
      refreshMveaComponent() {
        // Get a reference to the MVEA component we're updating
        const ref = window[this.componentRef];
        if (typeof (ref) === "undefined") {
          // The component doesn't exist, so reload the page to construct the new 
          // MVEA component on the canvas
          location.reload();
        } else {
          // refresh the existing component via ajax
          ref.refreshComponent();
        }
        // Clear the modal after a refresh
        this.modal.title = '';
        this.modal.url = '';
      },
      hide() {
        // Can be called from inner iframe with window.parent.cmMveaModal.hide()
        this.mveaModal.hide();
      }
    },
    mounted() {
      this.modalElement = document.getElementById('mvea-modal');
      this.mveaModal = new bootstrap.Modal(this.modalElement);
      const thisComponent = this;
      // When the modal is hidden, refresh the relevant component
      this.modalElement.addEventListener('hide.bs.modal', function (e) {
        thisComponent.refreshMveaComponent();
      })
    },
    template: `
      <Modal id="mvea":title="this.modal.title">
        <template v-slot:body="body">
          <iframe :src="this.modal.url" :aria-label="this.txt['menu.aria.label.lightbox']"/>
        </template>
      </Modal>
    `
  });
  
  // Mount the component and provide a global reference for this app instance.
  window.cmMveaModal = mveaModalApp.mount("#mvea-modal-container");
</script>

<div id="mvea-modal-container"></div>
