<?php
/**
 * COmanage Registry Bulk Actions
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
 * Bulk actions are set for an index view in columns.inc by setting the 
 * $bulkActions array with a simple list of actions.
 *  
 * Full list of available Bulk Actions:
 * $bulkActions = [
 *   'delete'
 * ];
 *
 * @link          https://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v5.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

/*
 * Parameters:
 * $bulkActions                  : array, required
 */

declare(strict_types = 1);

use App\Lib\Enum\BulkActionEnum;
use \Cake\Utility\Inflector;

/** var string $modelsName */
$modelsName = $this->getName();
// $tablename = models
// XXX backport to match?
$tableName = Inflector::tableize($this->name);
$controllersName = Inflector::underscore($this->name);
$tableFK = Inflector::singularize($tableName) . "_id";

// Load my helper functions
$vueHelper = $this->loadHelper('Vue');
// Get the CSRF Token in JavaScript
$token = $this->request->getAttribute('csrfToken');

$label = __d('operation','apply');

?>

<script type="module" nonce="<?= $vv_js_nonce ?>">
  <?php if(Cake\Core\Configure::read('debug')): ?>
  import BulkActions from "<?= $this->Url->script('comanage/components/bulk/bulk-actions.js') ?>?time=<?= time() ?>";
  <?php else: ?>
  import BulkActions from "<?= $this->Url->script('comanage/components/bulk/bulk-actions.js') ?>";
  <?php endif; ?>

  // XXX Probably move this to comanage.js
  const provided = {
    txt: JSON.parse('<?= json_encode($vueHelper->locales()) ?>'),
    app: {
      coId: <?= $vv_cur_co->id ?>,
      model: '<?= $modelsName ?>',
      controller: '<?= $controllersName ?>',
      humanize: '<?= Inflector::humanize($tableName) ?>',
      bulkactionfull: '<?= json_encode(BulkActionEnum::getHumanized()) ?>',
      bulkactionmethods: '<?= json_encode(BulkActionEnum::actionsToMethods()) ?>'
    },
    api: {
      webroot: '<?= $this->request->getAttribute('webroot') ?>',
      token: '<?= $token ?>'
    }
  }

  const app = Vue.createApp({
    data() {
      return {
        label: "<?= $label ?>",
        legend: "<?= __d('operation','bulk.actions') ?>",
        selectprompt: "<?= __d('operation','select.prompt') ?>",
        bulkactions: <?= json_encode($bulkActions) ?>,
      }
    },
    components: {
      BulkActions
    },
    template: `
      <BulkActions :label="label"
                   :legend="legend"
                   :selectprompt="selectprompt"
                   :bulkactions="bulkactions"></BulkActions>
    `
  });

  // Add custom global directives available to all child components.
  // "clickout" allows us to pass a function to a click outside behavior which
  // is registered and destroyed as the component is mounted and unmounted.
  app.directive("clickout", {
    mounted(el, binding, vnode) {
      el.clickOutEvent = function (event) {
        if (!(el === event.target || el.contains(event.target))) {
          binding.value(event, el);
        }
      };
      document.body.addEventListener("click", el.clickOutEvent);
    },
    unmounted(el) {
      document.body.removeEventListener("click", el.clickOutEvent);
    }
  });

  // For core configurations and texts globally
  for (const [key, value] of Object.entries(provided)) {
    app.provide( key, value);
  }
  app.mount("#bulk-actions");
</script>

<fieldset id="bulk-actions"></fieldset>
