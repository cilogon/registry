<?php
/**
 * COmanage Registry MVEA Vue.js Component / Card
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
$htmlId = $htmlId;
$parentId = $parentId;
$mveaType = $mveaType;      // the type of MVEA we will fetch
$entityType = $entityType;  // the type of entity continaing the MVEA (person, person_role, external_identity, external_identity_role)

// Get the camel-case controller name and generate the title  
$mveaController = Cake\Utility\Inflector::camelize($mveaType);
$title = __d('controller', $mveaController, [99]);

// Get the CSRF Token in JavaScript  
$token = $this->request->getAttribute('csrfToken');
// Load my helper functions
$vueHelper = $this->loadHelper('Vue');


// Create an MVEA component - typically this is represented as a card on a canvas page
?>

<script type="module">
  <?php if(Cake\Core\Configure::read('debug')): ?>
    import Mveas from "<?= $this->Url->script('comanage/components/mvea/mveas.js') ?>?time=<?= time() ?>";
    import MveaModal from "<?= $this->Url->script('comanage/components/mvea/mvea-modal.js') ?>?time=<?= time() ?>";
  <?php else: ?>
    import Mveas from "<?= $this->Url->script('comanage/components/mvea/mveas.js') ?>";
    import MveaModal from "<?= $this->Url->script('comanage/components/mvea/mvea-modal.js') ?>";
  <?php endif; ?>
  
  const app = Vue.createApp({
    data() {
      return {
        error: '',
        mveas: '',
        core: {
          parentId: '<?= $parentId ?>',
          mveaType: '<?= $mveaType ?>',
          mveaController: '<?= Cake\Utility\Inflector::dasherize($mveaController) ?>',
          mveaTitle: '<?= $title ?>',
          webroot: '<?= $this->request->getAttribute('webroot') ?>',
          action:  '<?= $vv_action ?>'
        },
        txt: JSON.parse('<?= json_encode($vueHelper->locales()) ?>'),
        isLoading: true
      }
    },
    components: {
      Mveas
    },
    methods: {
      getMveas(mveaType,entityType) {
        var entityTypeIdRef = entityType + '_id';
        let url = '<?=
          $this->Url->build(['controller' => 'api/ajax', 'action' => 'v2'])
          ?>/' + mveaType + '?' + entityTypeIdRef + '=<?php print $parentId ?>&extended';
        let xhr = callRegistryAPI(
          url, 
          'GET', 
          'json',
          this.setMveas,
          this.generalXhrFailCallback
        );
      },
      setMveas(xhr) {
        this.mveas = xhr.responseJSON;
      },
      setError(txt) {
        this.error = txt;
      },
      generalXhrFailCallback(xhr) {
        stopSpinner();
        this.successTxt = '';
        if(xhr.statusText != undefined && xhr.statusText != '') {
          this.setError(xhr.statusText)
          console.log('Status Code: ', xhr.status)
        } else {
          console.error(xhr);
          this.setError(this.txt.error500);
        }
      },
      launchModal(title,url,componentRef) {
        window.cmMveaModal.launch(title,url,componentRef);
      },
      refreshComponent() {
        this.getMveas('<?= $mveaType ?>', '<?= $entityType ?>');
      }
    },
    created() {
      this.refreshComponent();
    }
  });

  // Mount the component and provide a global reference for this app instance.
  window.mvea<?= $mveaType ?> = app.mount("#<?= $htmlId ?>");
</script>

<div id="<?= $htmlId ?>" class="col cm-mvea-col">
  <div class="card h-100">
    <div class="card-body">
      <h2 class="card-title">
        <?= $title ?>
        <span v-show="isLoading" class="co-loading-mini" role="status">
          <span></span><span></span><span></span>
        </span>
      </h2>
      <div class="card-text">
        <ul v-show="isLoading" class="cm-mvea fields data-list skeleton-list">
          <li><span class="visually-hidden">Loading...</span></li>
        </ul>
        <mveas
          :mveas="this.mveas"
          :core="this.core"
          :txt="this.txt">
        </mveas>
      </div>
    </div>
  </div>
</div>
